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
