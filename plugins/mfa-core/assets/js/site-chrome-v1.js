/* mfa-core site-chrome.js — behaviour for the public site shell.

   Merged from header-v3.js, share-button-v13.js and sofia-button-v1.js on
   2026-08-19, in their original enqueue order. They were three requests on
   every public page and always travelled together, matching site-chrome-v1.css.

   Safe to concatenate: each was already self-contained - two IIFEs and a
   DOMContentLoaded callback - with no top-level declarations, so nothing here
   shares scope with anything else. Keep it that way when editing: wrap any
   addition rather than introducing a top-level name. */

/* ===================== header: mobile menu + active links ===================== */
// header.js - [mfa_site_header] mobile menu toggle + active-page
// highlighting, same pattern as the footer's is-active-footer-item
// (see share-button.js) - detecting "which page am I on" needs the
// actual browser URL, not anything available at PHP render time.
(function () {
  function highlightActiveLinks() {
    var path = window.location.pathname.replace(/\/+$/, '') || '/';
    var links = document.querySelectorAll('.mfa-header-nav-link, .mfa-header-mobile-link');
    links.forEach(function (link) {
      var hrefAttr = link.getAttribute('href');
      if (hrefAttr === null) return; // the "Tools" trigger is a <button>, not a link
      var href = hrefAttr.replace(/\/+$/, '') || '/';
      if (href === path) {
        link.classList.add('is-active');
      }
    });
  }

  function initMobileMenu() {
    var burger = document.getElementById('mfa-header-burger');
    var moreTrigger = document.getElementById('mfa-header-more-trigger');
    var closeBtn = document.getElementById('mfa-header-mobile-close');
    var menu = document.getElementById('mfa-header-mobile-menu');
    var overlay = document.getElementById('mfa-header-mobile-overlay');
    if (!menu || (!burger && !moreTrigger)) return;

    function openMenu() {
      menu.classList.add('is-open');
      if (overlay) overlay.classList.add('is-open');
      document.body.classList.add('mfa-header-menu-open');
      menu.setAttribute('aria-hidden', 'false');
      menu.removeAttribute('inert');
      if (burger) burger.setAttribute('aria-expanded', 'true');
    }

    function closeMenu() {
      menu.classList.remove('is-open');
      if (overlay) overlay.classList.remove('is-open');
      document.body.classList.remove('mfa-header-menu-open');
      menu.setAttribute('aria-hidden', 'true');
      menu.setAttribute('inert', '');
      if (burger) burger.setAttribute('aria-expanded', 'false');
    }

    if (burger) burger.addEventListener('click', openMenu);
    if (moreTrigger) moreTrigger.addEventListener('click', openMenu);
    if (closeBtn) closeBtn.addEventListener('click', closeMenu);
    if (overlay) overlay.addEventListener('click', closeMenu);
  }

  function init() {
    highlightActiveLinks();
    initMobileMenu();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();


/* ===================== share button + footer nav ===================== */
// assets/js/share-button.js - floating footer share button, plus footer
// nav-bar icon cleanup and active-page highlighting (added later - the
// footer element has a pre-existing bug where Kadence's own per-block
// rendering doesn't run, so this file also patches a few footer symptoms
// alongside the actual share button).

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

  // Replaces the footer nav-bar icons with a clean, consistent Feather-style
  // set (the originals mix an old custom icon library with a couple of
  // Feather icons - inconsistent weight/style). Retried a few times since
  // Kadence's own client-side icon renderer (which resolves data-name into
  // inline SVG) runs asynchronously and would otherwise overwrite this.
  var FOOTER_ICONS = {
    '83_68cf95-cd': '<path d="M3 9.5L12 3l9 6.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1V9.5z"></path>',
    '83_b1c1f8-1a': '<path d="M12 3c-1.5 1-2.5 2.5-2.5 4 0 1 .5 2 .5 2H8a2 2 0 0 0-2 2v1h12v-1a2 2 0 0 0-2-2h-2s.5-1 .5-2c0-1.5-1-3-2.5-4z"></path><path d="M4 20v-7a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v7"></path><path d="M2 20h20"></path><path d="M10 20v-4a2 2 0 0 1 4 0v4"></path>',
    '83_ce2726-3c': '<rect x="2" y="7" width="20" height="13" rx="2"></rect><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="2" y1="13" x2="22" y2="13"></line>',
    '83_280044-0b': '<circle cx="12" cy="12" r="9"></circle><line x1="3" y1="12" x2="21" y2="12"></line><path d="M12 3c2.5 2.5 4 6 4 9s-1.5 6.5-4 9c-2.5-2.5-4-6-4-9s1.5-6.5 4-9z"></path>',
    '83_e0b60b-a8': '<path d="M4 4.5A1.5 1.5 0 0 1 5.5 3H11v18H5.5A1.5 1.5 0 0 1 4 19.5v-15z"></path><path d="M20 4.5A1.5 1.5 0 0 0 18.5 3H13v18h5.5a1.5 1.5 0 0 0 1.5-1.5v-15z"></path>'
  };

  function replaceFooterIcons() {
    Object.keys(FOOTER_ICONS).forEach(function (id) {
      var wrapper = document.querySelector('.kt-svg-item-' + id);
      var svg = wrapper ? wrapper.querySelector('svg') : null;
      if (!svg) return;
      svg.setAttribute('viewBox', '0 0 24 24');
      svg.setAttribute('fill', 'none');
      svg.setAttribute('stroke', 'currentColor');
      svg.setAttribute('stroke-width', '1.8');
      svg.setAttribute('stroke-linecap', 'round');
      svg.setAttribute('stroke-linejoin', 'round');
      svg.innerHTML = FOOTER_ICONS[id];
    });
  }

  // Marks the footer item matching the current page with an "is-active"
  // class so users can see which section they're on.
  function highlightActiveFooterItem() {
    var footer = document.querySelector('.kadence-column83_641fa4-f4');
    if (!footer) return;
    var path = window.location.pathname.replace(/\/+$/, '') || '/';
    var links = footer.querySelectorAll('a[href]');
    links.forEach(function (link) {
      var href = link.getAttribute('href').replace(/\/+$/, '') || '/';
      if (href === path) {
        var item = link.closest('.wp-block-kadence-column');
        if (item) item.classList.add('is-active-footer-item');
      }
    });
  }

  // Position is plain static CSS now (see share-button.css) - it used to
  // be recomputed here from #kt-scroll-up's live position so it could
  // stack above it, but that made the button visibly jump/move during
  // scroll. No positioning JS needed anymore.

  function init() {
    var btn = document.getElementById('mfa-share-btn');
    if (btn) {
      btn.addEventListener('click', onShareClick);
    }
    replaceFooterIcons();
    highlightActiveFooterItem();
    setTimeout(replaceFooterIcons, 400);
    setTimeout(replaceFooterIcons, 1200);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();


/* ===================== Sofia button ===================== */
document.addEventListener('DOMContentLoaded', function () {
	var trigger = document.getElementById('mfa-sofia-btn');
	var modal = document.getElementById('mfa-sofia-modal');
	var overlay = document.getElementById('mfa-sofia-overlay');
	var closeBtn = document.getElementById('mfa-sofia-modal-close');
	if (!trigger || !modal || !overlay) return;

	function openModal() {
		modal.classList.add('is-open');
		overlay.classList.add('is-open');
		modal.setAttribute('aria-hidden', 'false');
	}
	function closeModal() {
		modal.classList.remove('is-open');
		overlay.classList.remove('is-open');
		modal.setAttribute('aria-hidden', 'true');
	}

	trigger.addEventListener('click', function () {
		if (modal.classList.contains('is-open')) {
			closeModal();
		} else {
			openModal();
		}
	});
	if (closeBtn) closeBtn.addEventListener('click', closeModal);
	overlay.addEventListener('click', closeModal);
	document.addEventListener('keydown', function (e) {
		if (e.key === 'Escape' && modal.classList.contains('is-open')) closeModal();
	});
});

