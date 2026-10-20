(() => {
    'use strict';
    const workerUrl = document.currentScript.dataset.worker;
    const card = document.getElementById('pwa-install');
    const standalone = window.matchMedia('(display-mode: standalone)');
    const mobile = window.matchMedia('(max-width: 767px)');
    const ios = /iPad|iPhone|iPod/.test(navigator.userAgent) ||
        (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    let installPrompt = null;
    let installed = false;
    let dismissed = false;
    try { dismissed = sessionStorage.getItem('jospa-pwa-dismissed') === '1'; } catch (_) {}

    const update = () => {
        if (card) card.hidden = !window.isSecureContext || !mobile.matches ||
            standalone.matches || navigator.standalone === true || installed || dismissed;
    };
    window.addEventListener('beforeinstallprompt', event => {
        // Preserve browser installation UI on pages without our custom button.
        if (!card || !mobile.matches || dismissed) return;
        event.preventDefault();
        installPrompt = event;
        update();
    });
    window.addEventListener('appinstalled', () => {
        installed = true;
        installPrompt = null;
        update();
    });
    standalone.addEventListener('change', update);
    mobile.addEventListener('change', update);

    if ('serviceWorker' in navigator && window.isSecureContext) {
        navigator.serviceWorker.register(workerUrl).catch(error => {
            console.warn('Jospa service worker registration failed:', error);
        });
    }
    if (!card) return;
    const button = card.querySelector('.pwa-install-button');
    const help = card.querySelector('.pwa-help');
    const showHelp = () => {
        help.hidden = false;
        help.querySelector(ios ? '[data-ios]' : '[data-other]').hidden = false;
        button.setAttribute('aria-expanded', 'true');
    };
    card.querySelector('.pwa-dismiss').addEventListener('click', () => {
        dismissed = true;
        try { sessionStorage.setItem('jospa-pwa-dismissed', '1'); } catch (_) {}
        update();
    });
    button.addEventListener('click', async () => {
        if (!installPrompt) {
            showHelp();
            return;
        }
        const prompt = installPrompt;
        installPrompt = null;
        button.disabled = true;
        try {
            await prompt.prompt();
            const choice = await prompt.userChoice;
            if (choice.outcome === 'accepted') {
                installed = true;
                update();
            }
        } catch (_) {
            showHelp();
        } finally {
            button.disabled = false;
        }
    });
    update();
})();
