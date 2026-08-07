document.addEventListener('DOMContentLoaded', function () {
	var root = document.getElementById('mfa-member-auth');
	if (!root) return;

	var tabs = root.querySelectorAll('[data-mfa-auth-tab]');
	var panels = root.querySelectorAll('[data-mfa-auth-panel]');

	function showTab(name) {
		tabs.forEach(function (tab) {
			var isActive = tab.getAttribute('data-mfa-auth-tab') === name;
			tab.classList.toggle('is-active', isActive);
			tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
		});
		panels.forEach(function (panel) {
			panel.hidden = panel.getAttribute('data-mfa-auth-panel') !== name;
		});
	}

	tabs.forEach(function (tab) {
		tab.addEventListener('click', function () {
			showTab(tab.getAttribute('data-mfa-auth-tab'));
		});
	});

	// The reused [niz_login]/[niz_register] shortcodes each carry a cross-
	// link to the other flow (login's "Register" -> /register/, register's
	// "Login" -> /member/) since that's where they normally live standalone.
	// /register/ is still closed site-wide, so intercept those specific
	// links here and switch tabs in place instead of navigating away.
	root.addEventListener('click', function (e) {
		var link = e.target.closest('a');
		if (!link) return;
		var href = link.getAttribute('href') || '';
		if (href.indexOf('/register/') !== -1) {
			e.preventDefault();
			showTab('register');
		} else if (href.indexOf('/member') !== -1) {
			e.preventDefault();
			showTab('login');
		}
	});
});
