/**
 * Staff actions on the member detail page: Update Info, Send Email,
 * Send WhatsApp, Send Template, and the prepared-message pickers.
 *
 * The confirm() before a send is a courtesy to whoever is clicking, not a
 * security control - every handler re-checks the capability, the nonce and
 * the 24-hour window server-side, because the window can close between the
 * page rendering and the button being pressed.
 */
(function () {
	'use strict';

	// Prepared-message picker: fills the fields, never sends. Staff still edit
	// and press the real submit, so a canned message cannot leave unread.
	// Typed text is protected - once the box has content, switching asks first.
	document.addEventListener('change', function (e) {
		var select = e.target.closest('[data-mact-preset]');
		if (!select) { return; }

		var form = select.closest('.mfa-mact-form');
		if (!form) { return; }

		var option = select.options[select.selectedIndex];
		if (!option || !option.value) { return; } // Free-form: leave it alone.

		var body = form.querySelector('textarea[name="body"]');
		var subject = form.querySelector('input[name="subject"]');

		if (body && body.value.trim() &&
			!window.confirm('Replace what you have already written?')) {
			select.selectedIndex = 0;
			return;
		}

		if (subject && option.dataset.subject) { subject.value = option.dataset.subject; }
		if (body && option.dataset.body) { body.value = option.dataset.body; body.focus(); }
	});

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

/**
 * Activity tab type filter. Client-side because the whole list is already
 * in the DOM (capped at 100 rows by mfa_get_member_activity), so filtering
 * server-side would cost a round trip to hide rows already downloaded.
 */
(function () {
	'use strict';

	document.addEventListener('change', function (e) {
		var select = e.target.closest('[data-activity-filter]');
		if (!select) { return; }

		var panel = select.closest('.mfa-admin-tabpanel');
		if (!panel) { return; }

		var wanted = select.value;
		panel.querySelectorAll('tr[data-activity-type]').forEach(function (row) {
			row.hidden = wanted !== '' && row.getAttribute('data-activity-type') !== wanted;
		});

		// The "no activity" row belongs to the unfiltered list; a filter that
		// matches nothing needs to say so itself.
		var visible = panel.querySelectorAll('tr[data-activity-type]:not([hidden])').length;
		var empty = panel.querySelector('[data-activity-empty]');
		if (!empty) {
			var tbody = panel.querySelector('tbody');
			if (tbody) {
				empty = document.createElement('tr');
				empty.setAttribute('data-activity-empty', '');
				empty.innerHTML = '<td colspan="3" class="mfa-admin-member-empty">Nothing of that type.</td>';
				tbody.appendChild(empty);
			}
		}
		if (empty) { empty.hidden = visible > 0; }
	});
})();
