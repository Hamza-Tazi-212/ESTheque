import test from 'node:test';
import assert from 'node:assert/strict';
import { CameraSession } from '../../resources/js/camera-session.js';

function deferred() {
    let resolve;
    let reject;
    const promise = new Promise((yes, no) => { resolve = yes; reject = no; });
    return { promise, resolve, reject };
}

test('hiding the page while permission is pending releases the camera once it opens', async () => {
    const permission = deferred();
    let stops = 0;
    const camera = new CameraSession({ start: () => permission.promise, stop: async () => { stops++; } });
    const opening = camera.start();
    assert.equal(camera.starting, true);
    await camera.stop();
    assert.equal(stops, 0);
    permission.resolve();
    assert.equal(await opening, false);
    assert.equal(stops, 1);
    assert.equal(camera.running, false);
    assert.equal(camera.busy, false);
});

test('repeated clicks cannot start a second camera while opening or already running', async () => {
    const permission = deferred();
    let starts = 0;
    const camera = new CameraSession({ start: () => { starts++; return permission.promise; }, stop: async () => {} });
    const opening = camera.start();
    assert.equal(await camera.start(), false);
    permission.resolve();
    assert.equal(await opening, true);
    assert.equal(await camera.start(), false);
    assert.equal(starts, 1);
    await camera.stop();
});

test('simultaneous background and stop events share one camera shutdown', async () => {
    const closing = deferred();
    let stops = 0;
    const camera = new CameraSession({ start: async () => {}, stop: () => { stops++; return closing.promise; } });
    await camera.start();
    const first = camera.stop();
    const second = camera.stop();
    assert.equal(await camera.start(), false);
    closing.resolve();
    await Promise.all([first, second]);
    assert.equal(stops, 1);
    assert.equal(camera.busy, false);
    assert.equal(camera.running, false);
});

test('permission denial clears the opening state and allows a later retry', async () => {
    let attempts = 0;
    const camera = new CameraSession({
        start: async () => { if (++attempts === 1) throw new Error('NotAllowedError'); },
        stop: async () => {},
    });
    await assert.rejects(camera.start(), /NotAllowedError/);
    assert.equal(camera.busy, false);
    assert.equal(camera.running, false);
    assert.equal(await camera.start(), true);
    await camera.stop();
});

test('a new explicit start clears the stop requested by a previous background event', async () => {
    const camera = new CameraSession({ start: async () => {}, stop: async () => {} });
    await camera.stop();
    assert.equal(await camera.start(), true);
    assert.equal(camera.stopRequested, false);
    await camera.stop();
    assert.equal(camera.running, false);
});
