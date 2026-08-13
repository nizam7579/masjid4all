document.addEventListener('DOMContentLoaded', function () {
	document.querySelectorAll('.enaizi-click').forEach(function (el) {
		var clicked = false;

		el.addEventListener('click', function () {
			if (clicked) {
				return;
			}
			clicked = true;

			fetch(mfaAdsAjax.url, {
				method: 'POST',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: 'action=enaizi_click&id=' + this.dataset.id
			});
		});
	});
});
