import test from 'node:test';
import assert from 'node:assert/strict';
import { webcrypto } from 'node:crypto';
import { ScanSession, createRequestId } from '../../resources/js/scan-session.js';

const copy = { type: 'copy', qr_code: 'copy:EX-1', label: 'Algorithmique' };
const student = { type: 'student', qr_code: 'student:ST-1', label: 'Étudiant' };
const id = 'c7079e89-e10f-4c54-8b67-000000000001';

test('one QR cannot register anything; the two kinds work in either order', () => {
    for (const codes of [[copy, student], [student, copy]]) {
        const session = new ScanSession(() => id);
        assert.equal(session.add(codes[0]), true);
        assert.equal(session.pending, null);
        assert.equal(session.add(codes[1]), true);
        assert.equal(session.pending.request_id, id);
        assert.deepEqual(new Set(session.pending.qr_codes), new Set([copy.qr_code, student.qr_code]));
    }
});

test('successive frames of the same QR do not complete or repeat a pair', () => {
    let ids = 0;
    const session = new ScanSession(() => { ids++; return id; });
    session.add(copy);
    for (let i = 0; i < 20; i++) assert.equal(session.add(copy), false);
    assert.equal(ids, 0);
    session.add(student);
    for (let i = 0; i < 20; i++) assert.equal(session.add(student), false);
    assert.equal(ids, 1);
});

test('two different student cards cannot replace the requested copy', () => {
    const session = new ScanSession(() => id);
    session.add(student);
    assert.throws(() => session.add({ ...student, qr_code: 'student:ST-2' }), /déjà scanné/);
    assert.equal(session.codes.length, 1);
    assert.equal(session.pending, null);
});

test('a pending request retains its key and cannot be reset after a network failure', () => {
    const session = new ScanSession(() => id);
    session.add(copy);
    session.add(student);
    const initial = JSON.stringify(session.pending);
    assert.throws(() => session.reset(), /Vérifiez/);
    assert.equal(JSON.stringify(session.pending), initial);
    const restored = new ScanSession(() => { throw new Error('must not create another key'); });
    assert.equal(restored.restore(JSON.parse(initial)), true);
    assert.equal(JSON.stringify(restored.pending), initial);
    assert.equal(restored.add(copy), false);
});

test('a confirmed result requires an explicit new scan before another operation', () => {
    let sequence = 0;
    const session = new ScanSession(() => `request-${++sequence}`);
    session.add(copy);
    session.add(student);
    session.complete({ operation: 'borrowed', loan_id: 1 });
    assert.equal(session.add(copy), false);
    assert.equal(session.add(student), false);
    session.reset();
    session.add(copy);
    session.add(student);
    assert.equal(session.pending.request_id, 'request-2');
});

test('cancellation gets its own persistent key and is unavailable for a return', () => {
    const session = new ScanSession(() => id);
    session.complete({ operation: 'borrowed', loan_id: 7 });
    session.cancelRequest();
    assert.deepEqual(session.pending, { kind: 'cancel', request_id: id, loan_id: 7 });
    assert.throws(() => session.cancelRequest(), /Aucun emprunt/);
    const restored = new ScanSession();
    assert.equal(restored.restore(session.pending), true);
    assert.deepEqual(restored.pending, session.pending);
    session.complete({ operation: 'returned', loan_id: 7 });
    assert.throws(() => session.cancelRequest(), /Aucun emprunt/);
});

test('a known refusal permits starting a fresh pair', () => {
    const session = new ScanSession(() => id);
    session.add(copy);
    session.add(student);
    session.reject();
    assert.equal(session.pending, null);
    assert.equal(session.blocked, true);
    session.reset();
    assert.equal(session.blocked, false);
});

test('corrupt saved state cannot trigger a request', () => {
    for (const value of [null, {}, { kind: 'scan', request_id: id, qr_codes: [copy.qr_code, copy.qr_code] },
        { kind: 'scan', request_id: id, qr_codes: ['only-one'] }, { kind: 'cancel', request_id: id, loan_id: -1 },
        { kind: 'cancel', request_id: 'invalid', loan_id: 1 }, { kind: 'other', request_id: id, loan_id: 1 }]) {
        const session = new ScanSession();
        assert.equal(session.restore(value), false);
        assert.equal(session.pending, null);
    }
});

test('saved Unicode identifiers count characters rather than UTF-16 code units', () => {
    const session = new ScanSession();
    assert.equal(session.restore({ kind: 'scan', request_id: id, qr_codes: ['📚'.repeat(100), 'student:1'] }), true);
    assert.equal(new ScanSession().restore({ kind: 'scan', request_id: id, qr_codes: ['📚'.repeat(192), 'student:1'] }), false);
});

test('the UUID fallback uses cryptographic bytes and produces RFC 4122 version 4 IDs', () => {
    const provider = { getRandomValues: (bytes) => webcrypto.getRandomValues(bytes) };
    const first = createRequestId(provider);
    const second = createRequestId(provider);
    assert.match(first, /^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/);
    assert.notEqual(first, second);
});
