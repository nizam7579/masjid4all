/**
 * Ad tracking: clicks, and viewable impressions.
 *
 * Replaces ads-v1.js, which only did clicks and — because it was enqueued
 * on three post types while ads render in eleven places — only recorded
 * them on the single mosque/business/web templates. It is now enqueued by
 * the shortcode itself, so it ships wherever an ad appears.
 *
 * A "viewable" impression follows the usual display-advertising rule:
 * at least 50% of the ad visible for one continuous second. The server's
 * own `impressions` column keeps counting renders, so the two together
 * show what share of served ads were actually seen.
 */
(function () {
	'use strict';

	var DWELL_MS = 1000;
	var RATIO = 0.5;

	var reported = {};
	var queue = [];
	var flushTimer = null;

	function post(body) {
		if (typeof mfaAdsAjax === 'undefined' || !mfaAdsAjax.url) {
			return;
		}
		fetch(mfaAdsAjax.url, {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body,
			// Survives the request if the user navigates away mid-flush.
			keepalive: true
		}).catch(function () { /* tracking must never break the page */ });
	}

	function flush() {
		flushTimer = null;
		if (!queue.length) {
			return;
		}
		var ids = queue.splice(0, queue.length);
		post('action=enaizi_impression&ids=' + encodeURIComponent(ids.join(',')));
	}

	function report(id) {
		if (!id || reported[id]) {
			return;
		}
		reported[id] = true;
		queue.push(id);
		// Batched so a four-slot column is one request, not four.
		if (!flushTimer) {
			flushTimer = setTimeout(flush, 500);
		}
	}

	function init() {
		var ads = document.querySelectorAll('.enaizi-click');
		if (!ads.length) {
			return;
		}

		ads.forEach(function (el) {
			var clicked = false;
			el.addEventListener('click', function () {
				if (clicked) {
					return;
				}
				clicked = true;
				post('action=enaizi_click&id=' + encodeURIComponent(el.dataset.id));
			});
		});

		// No IntersectionObserver: count nothing rather than guess. An
		// undercount is honest; counting unseen ads is what we are fixing.
		if (!('IntersectionObserver' in window)) {
			return;
		}

		var timers = {};

		var io = new IntersectionObserver(function (entries) {
			entries.forEach(function (entry) {
				var id = entry.target.dataset.id;
				if (!id) {
					return;
				}

				if (entry.isIntersecting && entry.intersectionRatio >= RATIO) {
					if (timers[id]) {
						return;
					}
					timers[id] = setTimeout(function () {
						timers[id] = null;
						report(id);
						io.unobserve(entry.target);
					}, DWELL_MS);
				} else if (timers[id]) {
					// Scrolled away before the dwell completed - not a view.
					clearTimeout(timers[id]);
					timers[id] = null;
				}
			});
		}, { threshold: [0, RATIO, 1] });

		ads.forEach(function (el) {
			io.observe(el);
		});
	}

	// Send anything still queued when the page goes away.
	document.addEventListener('visibilitychange', function () {
		if (document.visibilityState === 'hidden') {
			flush();
		}
	});
	window.addEventListener('pagehide', flush);

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
}());
