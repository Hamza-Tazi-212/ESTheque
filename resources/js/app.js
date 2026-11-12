import './bootstrap';

if (document.querySelector('[data-overdue-chart-data]')) {
    import('./manager-charts');
}

if (document.querySelector('[data-qr-scanner]')) {
    import('./qr-scanner').catch(() => {
        document.querySelector('[data-scan-status]').textContent = 'Le lecteur QR ne peut pas être chargé. Rechargez la page.';
    });
}

document.querySelectorAll('[data-book-cover]').forEach((container) => {
    const image = container.querySelector('[data-cover-image]');
    const fallback = container.querySelector('[data-cover-fallback]');

    if (!image || !fallback) return;

    const showFallback = () => {
        image.classList.add('hidden');
        fallback.classList.remove('hidden');
    };

    image.addEventListener('error', showFallback, { once: true });
    if (image.complete && image.naturalWidth === 0) showFallback();
});
