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
