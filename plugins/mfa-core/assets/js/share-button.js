// assets/js/share-button.js - floating footer share button.
// Uses the native Web Share API (navigator.share) so the OS shows its
// own share sheet (copy link, send to devices, WhatsApp, etc.) instead
// of a custom in-page popup. Falls back to copy-to-clipboard on browsers
// without Web Share support (most desktop browsers).
(function () {
  function getCookie(name) {
    var match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
    return match ? decodeURIComponent(match[2]) : null;
  }

  function buildShareUrl() {
    var affiliateId = getCookie('affiliateid');
    var url = window.location.origin + window.location.pathname;
    if (affiliateId) {
      url += '?id=' + encodeURIComponent(affiliateId);
    }
    return url;
  }

  function showToast(message) {
    var toast = document.getElementById('mfa-share-toast');
    if (!toast) return;
    toast.textContent = message;
    toast.classList.add('is-visible');
    setTimeout(function () {
      toast.classList.remove('is-visible');
    }, 2200);
  }

  function fallbackCopy(url) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(url).then(function () {
        showToast('Link copied!');
      }).catch(function () {
        showToast(url);
      });
    } else {
      showToast(url);
    }
  }

  function onShareClick() {
    var url = buildShareUrl();
    var shareData = {
      title: 'Masjid4All',
      text: "Discover nearby mosques, connect with your local Muslim community, support Muslim-friendly businesses, and access trusted Islamic resources — all in one place.",
      url: url
    };

    if (navigator.share) {
      navigator.share(shareData).catch(function (err) {
        if (err && err.name === 'AbortError') return;
        fallbackCopy(url);
      });
    } else {
      fallbackCopy(url);
    }
  }

  function init() {
    var btn = document.getElementById('mfa-share-btn');
    if (btn) {
      btn.addEventListener('click', onShareClick);
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
