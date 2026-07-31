(function() {
    if (!enaiziPWAAnalytics || !enaiziPWAAnalytics.measurementId) return;

    function sendEvent(eventName, eventParams = {}) {
        fetch(enaiziPWAAnalytics.restUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': enaiziPWAAnalytics.nonce
            },
            body: JSON.stringify({
                event_name: eventName,
                event_params: eventParams
            })
        }).catch(e => console.warn('Analytics error', e));
    }

    // 1. Track PWA install prompt & install
    window.addEventListener('beforeinstallprompt', (e) => {
        sendEvent('pwa_install_prompt_shown');
        e.userChoice.then(choice => {
            if (choice.outcome === 'accepted') {
                sendEvent('pwa_install_clicked');
            }
        });
    });

    window.addEventListener('appinstalled', () => {
        sendEvent('pwa_installed');
    });

    // 2. Track any custom bar (you can adjust selectors)
    function observeBar() {
        const bar = document.getElementById('enaizi-pwa-global-bar');
        if (bar && !bar.hasAttribute('data-analytics-tracked')) {
            bar.setAttribute('data-analytics-tracked', 'true');
            let type = 'unknown';
            if (bar.innerHTML.includes('Install app')) type = 'pwa';
            else if (bar.innerHTML.includes('Join')) type = 'login';
            else if (bar.innerHTML.includes('Business')) type = 'business';
            else if (bar.innerHTML.includes('Knowledge')) type = 'knowledge';
            sendEvent('pwa_bar_shown', { bar_type: type });

            const actionBtn = bar.querySelector('#enaizi-pwa-action');
            if (actionBtn) {
                actionBtn.addEventListener('click', () => {
                    sendEvent('pwa_bar_clicked', { bar_type: type });
                });
            }
        }
    }

    const observer = new MutationObserver(() => observeBar());
    observer.observe(document.body, { childList: true, subtree: true });
    observeBar();

    // 3. Track offline page views
    if (navigator.serviceWorker) {
        navigator.serviceWorker.ready.then(reg => {
            navigator.serviceWorker.addEventListener('message', event => {
                if (event.data && event.data.type === 'offline_view') {
                    sendEvent('pwa_offline_view');
                }
            });
        });
    }
})();