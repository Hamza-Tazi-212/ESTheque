export function createRequestId(provider = globalThis.crypto) {
    if (typeof provider.randomUUID === 'function') return provider.randomUUID();
    const bytes = provider.getRandomValues(new Uint8Array(16));
    bytes[6] = (bytes[6] & 15) | 64;
    bytes[8] = (bytes[8] & 63) | 128;
    const hex = Array.from(bytes, (byte) => byte.toString(16).padStart(2, '0')).join('');
    return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
}

export class ScanSession {
    constructor(createId = createRequestId) {
        this.createId = createId;
        this.codes = [];
        this.pending = null;
        this.result = null;
    }

    get blocked() {
        return this.pending !== null || this.result !== null || this.codes.length === 2;
    }

    has(qrCode) {
        return this.codes.some((code) => code.qr_code === qrCode);
    }

    add(code) {
        if (this.blocked || this.has(code.qr_code)) return false;
        if (!['copy', 'student'].includes(code.type)) throw new Error('Ce QR code n’est pas reconnu.');
        if (this.codes.some((item) => item.type === code.type)) {
            throw new Error('Ce type de QR est déjà scanné. Présentez maintenant l’autre QR, ou recommencez les scans.');
        }
        this.codes.push({ ...code });
        if (this.codes.length === 2) {
            this.pending = { kind: 'scan', request_id: this.createId(), qr_codes: this.codes.map((item) => item.qr_code) };
        }
        return true;
    }

    cancelRequest() {
        if (this.pending || this.result?.operation !== 'borrowed') {
            throw new Error('Aucun emprunt à annuler.');
        }
        this.pending = { kind: 'cancel', request_id: this.createId(), loan_id: this.result.loan_id };
    }

    complete(result) {
        this.pending = null;
        this.result = result;
    }

    reject() {
        this.pending = null;
        this.result = null;
    }

    reset() {
        if (this.pending) throw new Error('Vérifiez d’abord le résultat de l’opération en attente.');
        this.codes = [];
        this.result = null;
    }

    restore(value) {
        if (!value || !/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i.test(value.request_id ?? '')) return false;
        const validScan = value.kind === 'scan' && Array.isArray(value.qr_codes) && value.qr_codes.length === 2
            && value.qr_codes.every((code) => typeof code === 'string' && code.length > 0 && [...code].length <= 191)
            && value.qr_codes[0] !== value.qr_codes[1];
        const validCancel = value.kind === 'cancel' && Number.isSafeInteger(value.loan_id) && value.loan_id > 0;
        if (!validScan && !validCancel) return false;
        this.pending = validScan
            ? { kind: 'scan', request_id: value.request_id, qr_codes: [...value.qr_codes] }
            : { kind: 'cancel', request_id: value.request_id, loan_id: value.loan_id };
        return true;
    }
}
