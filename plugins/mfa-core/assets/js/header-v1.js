// header.js - [mfa_site_header] mobile menu toggle + active-page
// highlighting, same pattern as the footer's is-active-footer-item
// (see share-button.js) - detecting "which page am I on" needs the
// actual browser URL, not anything available at PHP render time.
(function () {
  function highlightActiveLinks() {
    var path = window.location.pathname.replace(/\/+$/, '') || '/';
    var links = document.querySelectorAll('.mfa-header-nav-link, .mfa-header-mobile-link');
    links.forEach(function (link) {
      var href = link.getAttribute('href').replace(/\/+$/, '') || '/';
      if (href === path) {
        link.classList.add('is-active');
      }
    });
  }

  function initMobileMenu() {
    var burger = document.getElementById('mfa-header-burger');
    var closeBtn = document.getElementById('mfa-header-mobile-close');
    var menu = document.getElementById('mfa-header-mobile-menu');
    var overlay = document.getElementById('mfa-header-mobile-overlay');
    if (!burger || !menu) return;

    function openMenu() {
      menu.classList.add('is-open');
      if (overlay) overlay.classList.add('is-open');
      document.body.classList.add('mfa-header-menu-open');
      menu.setAttribute('aria-hidden', 'false');
      burger.setAttribute('aria-expanded', 'true');
    }

    function closeMenu() {
      menu.classList.remove('is-open');
      if (overlay) overlay.classList.remove('is-open');
      document.body.classList.remove('mfa-header-menu-open');
      menu.setAttribute('aria-hidden', 'true');
      burger.setAttribute('aria-expanded', 'false');
    }

    burger.addEventListener('click', openMenu);
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
