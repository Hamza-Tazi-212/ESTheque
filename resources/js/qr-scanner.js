import { Html5Qrcode, Html5QrcodeSupportedFormats } from 'html5-qrcode';
import { ScanSession } from './scan-session';
import { CameraSession } from './camera-session';

const root = document.querySelector('[data-qr-scanner]');

if (root) {
    const get = (selector) => root.querySelector(selector);
    const cameraToggle = get('[data-toggle-camera]');
    const next = get('[data-new-scan]');
    const retry = get('[data-retry-operation]');
    const cancel = get('[data-cancel-loan]');
    const status = get('[data-scan-status]');
    const resultBox = get('[data-scan-result]');
    const session = new ScanSession();
    const storageKey = `est-library-pending-scan:${root.dataset.managerId}`;

    let camera = null;
    let cameraSession;
    let paused = false;
    let identifying = false;
    let submitting = false;
    let lastAttemptedQr = null;

    const message = (text) => {
        status.textContent = text;
    };

    const savePending = () => {
        try {
            if (session.pending) {
                sessionStorage.setItem(storageKey, JSON.stringify(session.pending));
            } else {
                sessionStorage.removeItem(storageKey);
            }
        } catch {
            // La requête reste disponible en mémoire.
        }
    };

    const refreshButtons = () => {
        const cameraActive = cameraSession.running || cameraSession.starting;
        cameraToggle.textContent = cameraActive ? 'Arrêter la caméra' : 'Activer la caméra';
        cameraToggle.setAttribute('aria-pressed', String(cameraActive));
        cameraToggle.disabled = submitting
            || cameraSession.stopping !== null
            || (cameraSession.starting && cameraSession.stopRequested)
            || (!cameraActive && (identifying || session.pending !== null));

        for (const className of ['bg-emerald-900', 'hover:bg-emerald-800', 'focus-visible:outline-emerald-800']) {
            cameraToggle.classList.toggle(className, !cameraActive);
        }
        for (const className of ['bg-red-700', 'hover:bg-red-600', 'focus-visible:outline-red-700']) {
            cameraToggle.classList.toggle(className, cameraActive);
        }

        next.disabled = cameraSession.busy
            || submitting
            || identifying
            || session.pending !== null;

        retry.hidden = session.pending === null;
        retry.disabled = submitting;
        cancel.disabled = submitting || session.pending !== null;
        cancel.hidden = session.result?.operation !== 'borrowed';
    };

    cameraSession = new CameraSession({
        start: (...args) => {
            camera ??= new Html5Qrcode('qr-reader', {
                formatsToSupport: [Html5QrcodeSupportedFormats.QR_CODE],
                verbose: false,
            });

            return camera.start(...args);
        },
        stop: () => camera.stop(),
    }, refreshButtons);

    const pause = () => {
        if (cameraSession.running && !cameraSession.busy && !paused) {
            try {
                camera.pause(true);
                paused = true;
            } catch {
                // La fermeture de la caméra ne modifie pas la session de scan.
            }
        }
    };

    const post = async (url, body) => {
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 15000);

        try {
            const response = await fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                signal: controller.signal,
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify(body),
            });

            const data = await response.json().catch(() => null);

            if (response.status === 401 || response.status === 419) {
                throw new Error(
                    'Votre session a expiré. Rechargez la page pour vous reconnecter et vérifier l’opération.'
                );
            }

            if (response.status === 403) {
                throw new Error(
                    'Votre compte n’a plus accès au scan. Revenez à l’accueil pour vérifier vos droits.'
                );
            }

            if (response.status >= 500 || data === null) {
                throw new Error(
                    'Le résultat n’est pas confirmé. Vérifiez l’opération avant de recommencer les scans.'
                );
            }

            if (!response.ok) {
                return { rejected: true, data };
            }

            return { rejected: false, data };
        } finally {
            clearTimeout(timeout);
        }
    };

    const showResult = (data) => {
        resultBox.hidden = false;

        get('[data-result-title]').textContent = data.message;
        get('[data-result-student]').textContent = `Étudiant : ${data.student.name}`;
        get('[data-result-copy]').textContent = `${data.copy.title} · ${data.copy.qr_code}`;

        get('[data-result-due]').textContent = data.operation === 'borrowed'
            ? `À rendre avant le ${new Intl.DateTimeFormat('fr-MA', {
                dateStyle: 'long',
                timeStyle: 'short',
                timeZone: root.dataset.timezone,
            }).format(new Date(data.due_at))}.`
            : 'Cette opération a remis l’exemplaire à disposition dans le catalogue.';
    };

    const submit = async () => {
        if (!session.pending || submitting) return;

        submitting = true;
        savePending();
        pause();
        refreshButtons();

        message('Enregistrement de l’opération en cours…');

        const request = session.pending;

        const url = request.kind === 'scan'
            ? root.dataset.storeUrl
            : root.dataset.cancelUrl.replace('__LOAN__', String(request.loan_id));

        const body = request.kind === 'scan'
            ? {
                request_id: request.request_id,
                qr_codes: request.qr_codes,
            }
            : {
                request_id: request.request_id,
            };

        try {
            const response = await post(url, body);

            if (response.rejected) {
                session.reject();
                resultBox.hidden = true;

                message(
                    response.data.message
                    ?? 'L’opération a été refusée. Recommencez les scans.'
                );
            } else if (
                ['borrowed', 'returned', 'cancelled'].includes(response.data.operation)
            ) {
                session.complete(response.data);
                showResult(response.data);
                message(response.data.message);
                next.textContent = 'Nouveau scan';
                savePending();
                await stopCamera().catch(() => {});
            } else {
                throw new Error(
                    'Le résultat n’est pas confirmé. Vérifiez l’opération avant de recommencer les scans.'
                );
            }

            savePending();
        } catch (error) {
            message(
                error.name === 'AbortError' || error instanceof TypeError
                    ? 'Connexion interrompue : le résultat n’est pas confirmé. Utilisez « Vérifier l’opération ».'
                    : error.message
            );
        } finally {
            submitting = false;
            refreshButtons();
        }
    };

    const decoded = async (qrCode) => {
        if (
            cameraSession.stopRequested
            || identifying
            || submitting
            || session.blocked
            || session.has(qrCode)
            || lastAttemptedQr === qrCode
        ) {
            return;
        }

        lastAttemptedQr = qrCode;

        if (qrCode.length === 0 || [...qrCode].length > 191) {
            message(
                'Ce QR code ne correspond pas à un identifiant de carte ou d’exemplaire.'
            );
            return;
        }

        identifying = true;
        refreshButtons();

        try {
            const response = await post(root.dataset.identifyUrl, {
                qr_code: qrCode,
            });

            if (response.rejected) {
                message(
                    response.data.message ?? 'Ce QR code n’est pas reconnu.'
                );
                return;
            }

            if (session.add(response.data)) {
                const labelSelector = response.data.type === 'copy'
                    ? '[data-copy-label]'
                    : '[data-student-label]';

                get(labelSelector).textContent = response.data.label;

                if (session.pending) {
                    pause();
                    await submit();
                } else {
                    message(
                        response.data.type === 'copy'
                            ? 'Exemplaire reconnu. Scannez la carte étudiante.'
                            : 'Carte reconnue. Scannez le QR de l’exemplaire.'
                    );
                }
            }
        } catch (error) {
            lastAttemptedQr = null;

            message(
                error instanceof TypeError || error.name === 'AbortError'
                    ? 'Connexion indisponible. Présentez à nouveau ce QR code.'
                    : error.message
            );
        } finally {
            identifying = false;
            refreshButtons();
        }
    };

    const stopCamera = async () => {
        try {
            await cameraSession.stop();
        } finally {
            paused = false;
            refreshButtons();
        }
    };

    cameraToggle.addEventListener('click', async () => {
        if (cameraSession.running || cameraSession.starting) {
            await stopCamera().catch(() => message('La caméra est arrêtée.'));
            return;
        }

        if (
            cameraSession.running
            || cameraSession.busy
            || session.pending
        ) {
            return;
        }

        if (
            !window.isSecureContext
            || !navigator.mediaDevices?.getUserMedia
        ) {
            message(
                'La caméra nécessite une connexion HTTPS, ou localhost sur cet appareil, et un navigateur compatible.'
            );
            return;
        }

        if (session.result) next.click();

        cameraToggle.disabled = true;

        try {
            const opened = await cameraSession.start(
                { facingMode: 'environment' },
                {
                    fps: 8,
                    qrbox: (width, height) => {
                        const size = Math.min(
                            240,
                            Math.floor(Math.min(width, height) * 0.8)
                        );

                        return { width: size, height: size };
                    },
                },
                decoded,
                () => {}
            );

            if (!opened || document.hidden) {
                await stopCamera();
                return;
            }

            if (session.blocked) {
                pause();
            }

            message(
                session.result
                    ? 'L’opération est enregistrée. Lancez un nouveau scan pour continuer.'
                    : 'Présentez le QR d’un exemplaire ou d’une carte étudiante.'
            );
        } catch (error) {
            const name = error?.name ?? String(error);

            message(
                name.includes('NotAllowed') || name.includes('Permission')
                    ? 'Accès à la caméra refusé. Autorisez la caméra dans votre navigateur puis réessayez.'
                    : 'Impossible d’ouvrir la caméra. Vérifiez qu’elle est disponible et qu’aucune autre application ne l’utilise.'
            );
        } finally {
            refreshButtons();
        }
    });

    retry.addEventListener('click', submit);

    cancel.addEventListener('click', () => {
        if (submitting || session.pending) return;

        session.cancelRequest();
        submit();
    });

    next.addEventListener('click', () => {
        if (
            cameraSession.busy
            || session.pending
            || identifying
            || submitting
        ) {
            return;
        }

        session.reset();
        lastAttemptedQr = null;
        resultBox.hidden = true;

        get('[data-copy-label]').textContent = 'En attente du QR de l’exemplaire';
        get('[data-student-label]').textContent = 'En attente du QR de la carte';

        next.textContent = 'Recommencer les scans';

        if (cameraSession.running && paused) {
            try {
                camera.resume();
                paused = false;
            } catch {
                stopCamera().catch(() => {});
            }
        }

        message(
            cameraSession.running
                ? 'Présentez les deux QR pour la nouvelle opération.'
                : 'Activez la caméra pour commencer.'
        );

        refreshButtons();
    });

    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            stopCamera().catch(() => {});
        }
    });

    window.addEventListener('pagehide', () => {
        stopCamera().catch(() => {});
    });

    try {
        const stored = JSON.parse(sessionStorage.getItem(storageKey));

        if (stored && !session.restore(stored)) {
            sessionStorage.removeItem(storageKey);
        }
    } catch {
        // Utilisation de la session en mémoire si le stockage est indisponible.
    }

    refreshButtons();

    if (session.pending) {
        submit();
    }
}
