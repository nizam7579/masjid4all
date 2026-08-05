// assets/js/quran.js - Edge-safe AJAX loader, scan-pattern (multi-instance safe)
(function() {
    function loadQuranWidget(el) {
        if (el.dataset.m4aLoaded) return;
        el.dataset.m4aLoaded = '1';

        var ajaxUrl = el.getAttribute('data-ajaxurl');
        var surahId = el.getAttribute('data-surah');

        var formData = new FormData();
        formData.append('action', 'm4a_load_single_quran_surah');
        formData.append('surah_id', surahId);

        fetch(ajaxUrl, { method: 'POST', body: formData })
            .then(function(res) { return res.text(); })
            .then(function(html) {
                var loading = el.querySelector('.m4a-quran-loading');
                var content = el.querySelector('.m4a-quran-loaded-content');
                if (loading) loading.style.display = 'none';
                if (content) {
                    content.innerHTML = html;
                    content.style.display = 'block';
                }
            })
            .catch(function(err) { console.error('Quran Widget Error:', err); });
    }

    function initQuranWidgets() {
        document.querySelectorAll('.m4a-quran-container').forEach(loadQuranWidget);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initQuranWidgets);
    } else {
        initQuranWidgets();
    }

    document.addEventListener('DOMContentLoaded', function() {
        var dropdown = document.getElementById('m4a-surah-dropdown');
        if (dropdown) {
            dropdown.addEventListener('change', function() {
                var targetUrl = this.value;
                if (targetUrl) {
                    window.location.href = targetUrl;
                }
            });
        }
    });
})();
