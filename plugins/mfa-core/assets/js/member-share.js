// assets/js/member-share.js - scan-pattern (multi-instance safe)
(function() {
    function initShareWidget(wrapper) {
        var copyBtn = wrapper.querySelector('.copy-btn');
        if (!copyBtn || copyBtn.dataset.mfaBound) return;
        copyBtn.dataset.mfaBound = '1';

        var copyBtnText = copyBtn.querySelector('.btn-label');
        var iconDefault = copyBtn.querySelector('.btn-icon-svg:not(.btn-icon-check)');
        var iconCheck = copyBtn.querySelector('.btn-icon-check');

        copyBtn.addEventListener('click', function() {
            var textToCopy = copyBtn.getAttribute('data-copy-text');

            navigator.clipboard.writeText(textToCopy).then(function() {
                if (copyBtnText) copyBtnText.textContent = 'Copied!';
                copyBtn.classList.add('copied');
                if (iconDefault) iconDefault.style.display = 'none';
                if (iconCheck) iconCheck.style.display = 'block';

                setTimeout(function() {
                    if (copyBtnText) copyBtnText.textContent = 'Copy Text & Link';
                    copyBtn.classList.remove('copied');
                    if (iconDefault) iconDefault.style.display = 'block';
                    if (iconCheck) iconCheck.style.display = 'none';
                }, 2000);
            }).catch(function(err) {
                console.error('Could not copy text: ', err);
            });
        });
    }

    function initAllShareWidgets() {
        document.querySelectorAll('.share-inner-layout').forEach(initShareWidget);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAllShareWidgets);
    } else {
        initAllShareWidgets();
    }
})();
