document.addEventListener('DOMContentLoaded', function () {
	var overlay = document.querySelector('[data-mfa-modal-overlay]');
	if (!overlay) return;

	function openModal(id) {
		var modal = document.getElementById(id);
		if (!modal) return;
		modal.classList.add('is-open');
		modal.setAttribute('aria-hidden', 'false');
		overlay.classList.add('is-open');
	}

	function closeAllModals() {
		document.querySelectorAll('.mfa-modal.is-open').forEach(function (modal) {
			modal.classList.remove('is-open');
			modal.setAttribute('aria-hidden', 'true');
		});
		overlay.classList.remove('is-open');
	}

	document.querySelectorAll('[data-mfa-modal-open]').forEach(function (btn) {
		btn.addEventListener('click', function () {
			openModal(btn.getAttribute('data-mfa-modal-open'));
		});
	});
	document.querySelectorAll('[data-mfa-modal-close]').forEach(function (btn) {
		btn.addEventListener('click', closeAllModals);
	});
	overlay.addEventListener('click', closeAllModals);
	document.addEventListener('keydown', function (e) {
		if (e.key === 'Escape') closeAllModals();
	});

	function bindAjaxForm(formId, action) {
		var form = document.getElementById(formId);
		if (!form) return;

		form.addEventListener('submit', function (e) {
			e.preventDefault();
			var msg = form.querySelector('[data-mfa-form-message]');
			var submitBtn = form.querySelector('.mfa-modal-submit');
			var originalText = submitBtn ? submitBtn.textContent : '';

			var data = new FormData(form);
			data.append('action', action);

			if (submitBtn) {
				submitBtn.disabled = true;
				submitBtn.textContent = 'Saving...';
			}
			if (msg) {
				msg.textContent = '';
				msg.classList.remove('is-success');
			}

			fetch(mfaMemberModals.ajaxUrl, {
				method: 'POST',
				body: data,
				credentials: 'same-origin'
			})
				.then(function (response) { return response.json(); })
				.then(function (result) {
					if (result.success) {
						if (msg) {
							msg.textContent = result.data.message;
							msg.classList.add('is-success');
						}
						setTimeout(function () { window.location.reload(); }, 900);
					} else {
						if (msg) msg.textContent = result.data.message || 'Something went wrong.';
					}
				})
				.catch(function () {
					if (msg) msg.textContent = 'Network error. Please try again.';
				})
				.finally(function () {
					if (submitBtn) {
						submitBtn.disabled = false;
						submitBtn.textContent = originalText;
					}
				});
		});
	}

	bindAjaxForm('mfa-edit-profile-form', 'mfa_update_profile');
	bindAjaxForm('mfa-change-password-form', 'mfa_change_password');
});
