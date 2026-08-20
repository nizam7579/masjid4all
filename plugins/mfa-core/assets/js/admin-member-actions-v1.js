/**
 * Staff actions on the member detail page: Update Info, Send Email,
 * Send WhatsApp, Send Template.
 *
 * The confirm() before a send is a courtesy to whoever is clicking, not a
 * security control - every handler re-checks the capability, the nonce and
 * the 24-hour window server-side, because the window can close between the
 * page rendering and the button being pressed.
 */
(function () {
	'use strict';

	var bar = document.querySelector('.mfa-admin-member-actions-bar');
	if (!bar) {
		return;
	}

	var userId = bar.getAttribute('data-user');
	var nonce = bar.getAttribute('data-nonce');
	var ajaxUrl = (typeof mfaMemberActions !== 'undefined' && mfaMemberActions.url)
		? mfaMemberActions.url
		: '/wp-admin/admin-ajax.php';

	function openModal(id) {
		var el = document.getElementById(id);
		if (el) {
			el.classList.add('is-open');
			el.setAttribute('aria-hidden', 'false');
		}
	}

	function closeModal(el) {
		el.classList.remove('is-open');
		el.setAttribute('aria-hidden', 'true');
	}

	document.querySelectorAll('[data-mact-open]').forEach(function (btn) {
		btn.addEventListener('click', function () {
			if (btn.disabled) {
				return;
			}
			openModal(btn.getAttribute('data-mact-open'));
		});
	});

	document.querySelectorAll('[data-mact-close]').forEach(function (btn) {
		btn.addEventListener('click', function () {
			closeModal(btn.closest('.mfa-mact-overlay'));
		});
	});

	document.querySelectorAll('.mfa-mact-overlay').forEach(function (ov) {
		ov.addEventListener('click', function (e) {
			if (e.target === ov) {
				closeModal(ov);
			}
		});
	});

	document.addEventListener('keydown', function (e) {
		if (e.key === 'Escape') {
			document.querySelectorAll('.mfa-mact-overlay.is-open').forEach(closeModal);
		}
	});

	document.querySelectorAll('.mfa-mact-form').forEach(function (form) {
		form.addEventListener('submit', function (e) {
			e.preventDefault();

			var confirmText = form.getAttribute('data-mact-confirm');
			if (confirmText && !window.confirm(confirmText)) {
				return;
			}

			var msg = form.querySelector('[data-mact-msg]');
			var submit = form.querySelector('.mfa-mact-submit');
			var original = submit ? submit.textContent : '';

			if (submit) {
				submit.disabled = true;
				submit.textContent = 'Working…';
			}
			if (msg) {
				msg.textContent = '';
				msg.classList.remove('is-success');
			}

			var data = new FormData(form);
			data.append('action', 'mfa_admin_member_' + form.getAttribute('data-mact-action'));
			data.append('user_id', userId);
			data.append('nonce', nonce);

			fetch(ajaxUrl, { method: 'POST', body: data, credentials: 'same-origin' })
				.then(function (r) { return r.json(); })
				.then(function (res) {
					var ok = res && res.success;
					var text = (res && res.data && res.data.message)
						? res.data.message
						: (ok ? 'Done.' : 'Something went wrong.');
					if (msg) {
						msg.textContent = text;
						msg.classList.toggle('is-success', !!ok);
					}
					if (ok) {
						// Reload so the page reflects the change - the header,
						// the contact state and the activity list all move.
						setTimeout(function () { window.location.reload(); }, 900);
					}
				})
				.catch(function () {
					if (msg) { msg.textContent = 'Network error. Nothing was sent.'; }
				})
				.finally(function () {
					if (submit) {
						submit.disabled = false;
						submit.textContent = original;
					}
				});
		});
	});
}());
