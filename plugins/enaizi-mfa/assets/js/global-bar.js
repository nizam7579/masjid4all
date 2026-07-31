(function() {
    let deferredPrompt;
    let BAR_QUEUE = [];
    let SESSION_CLOSED = false;

    function getFooterHeight() {
        let footer = document.querySelector('.kadence-sticky-footer, .mobile-bottom-nav');
        return footer ? footer.offsetHeight : 0;
    }

    function registerBar(config) {
        config.timestamp = Date.now();
        BAR_QUEUE.push(config);
    }

    function isCurrentPage(url) {
        if (!url) return false;
        // Normalize both paths: remove trailing slash, ignore domain
        let currentPath = window.location.pathname.replace(/\/$/, '');
        let targetPath = url.replace(/\/$/, '');
        // Ensure leading slash consistency
        if (!targetPath.startsWith('/')) targetPath = '/' + targetPath;
        return currentPath === targetPath;
    }

    function processBarQueue() {
        if (SESSION_CLOSED) return;

        // Filter out dismissed bars
        BAR_QUEUE = BAR_QUEUE.filter(bar => {
            let dismissed = localStorage.getItem(bar.id + "_dismissed");
            if (!dismissed) return true;
            let interval = bar.interval || (1000 * 60 * 60 * 24 * 2);
            return (Date.now() - dismissed) > interval;
        });
 
        // Also filter out bars that point to the current page
        BAR_QUEUE = BAR_QUEUE.filter(bar => {
            if (bar.url && isCurrentPage(bar.url)) {
                return false; // don't show bar for current page
            }
            return true;
        });

        // Sort by priority (higher first), then by timestamp
        BAR_QUEUE.sort((a, b) => {
            if (a.priority !== b.priority) return b.priority - a.priority;
            return a.timestamp - b.timestamp;
        });

        let selected = BAR_QUEUE[0];
        if (selected) showBar(selected);
    }

    function showBar(config) {
        if (document.getElementById("enaizi-pwa-global-bar")) return;
        let footerHeight = getFooterHeight();
        let bar = document.createElement("div");
        bar.id = "enaizi-pwa-global-bar";
        bar.style.bottom = (footerHeight + 10) + "px";
        bar.innerHTML = `
            <div class="enaizi-pwa-bar">
                <div class="enaizi-pwa-left">
                    ${config.icon ? `<img src="${config.icon}">` : ""}
                    <div>
                        <strong>${config.title}</strong>
                        <span>${config.text}</span>
                    </div>
                </div>
                <div class="enaizi-pwa-right">
                    ${config.button ? `<button id="enaizi-pwa-action">${config.button}</button>` : ""}
                    <span id="enaizi-pwa-close">✕</span>
                </div>
            </div>
        `;
        document.body.appendChild(bar);

        let actionBtn = document.getElementById("enaizi-pwa-action");
        if (actionBtn && config.onClick) {
            actionBtn.addEventListener("click", function(e) {
                e.stopPropagation();
                e.preventDefault();
                SESSION_CLOSED = true;
                localStorage.setItem(config.id + "_dismissed", Date.now());
                bar.remove();
                config.onClick();
            });
        }

        document.getElementById("enaizi-pwa-close").addEventListener("click", function(e) {
            e.stopPropagation();
            SESSION_CLOSED = true;
            localStorage.setItem(config.id + "_dismissed", Date.now());
            bar.remove();
        });
    }

    // PWA install prompt (no URL)
    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        deferredPrompt = e;
        registerBar({
            id: "pwa",
            priority: 100,
            icon: enaiziPWA.iconUrl,
            title: "Masjid4All",
            text: "Install app for faster access",
            button: "Install",
            interval: 1000 * 60 * 60 * 24 * 3,
            onClick: async () => {
                deferredPrompt.prompt();
                await deferredPrompt.userChoice;
                deferredPrompt = null;
            }
        });
        processBarQueue();
    });

    // Login bar (only if not logged in)
    if (!enaiziPWA.isUserLoggedIn) {
        registerBar({
            id: "login",
            priority: 80,
            title: "Join <b>Masjid4All</b> NOW.<br>",
            text: "Membership is <b>FREE</b>",
            button: "Login",
            interval: 1000 * 60 * 60 * 24,
            url: enaiziPWA.loginUrl,
            onClick: () => { window.location.href = enaiziPWA.loginUrl; }
        });
    }

    // Business prompt

    registerBar({
        id: "business",
        priority: 50,
        title: "Find Nearby Businesses.<br>",
        text: "Support Muslim-friendly businesses",
        button: "Explore",
        interval: 1000 * 60 * 60 * 6,
        url: enaiziPWA.businessUrl,
        onClick: () => { window.location.href = enaiziPWA.businessUrl; }
    });

    // Knowledge Hub prompt
    registerBar({
        id: "knowledge",
        priority: 30,
        title: "Explore our Knowledge Hub.",
        text: "Practical Islamic learning center with clear, structured, and easy-to-apply guidance for everyday life.",
        button: "Explore",
        interval: 1000 * 60 * 60 * 6,
        url: enaiziPWA.knowledgeUrl,
        onClick: () => { window.location.href = enaiziPWA.knowledgeUrl; }
    });
   
    

    window.addEventListener('load', () => {
        setTimeout(() => { processBarQueue(); }, 1500);
    });
})();