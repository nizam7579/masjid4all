document.addEventListener('DOMContentLoaded', function () {
	document.querySelectorAll('.niz-password-toggle').forEach(function (btn) {
		btn.addEventListener('click', function () {
			var targetId = btn.getAttribute('data-niz-toggle-target');
			var input = document.getElementById(targetId);
			if (!input) return;

			var isHidden = input.type === 'password';
			input.type = isHidden ? 'text' : 'password';
			btn.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
			btn.classList.toggle('is-visible', isHidden);
		});
	});
});
