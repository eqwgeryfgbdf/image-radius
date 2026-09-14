(function (wp) {
	'use strict';

	if (!wp || !wp.customize) {
		return;
	}

	function ensureStyleEl() {
		let styleEl = document.getElementById('image-radius-css');
		if (styleEl) {
			return styleEl;
		}
		styleEl = document.createElement('style');
		styleEl.id = 'image-radius-css';
		document.head.appendChild(styleEl);
		return styleEl;
	}

	function applyRadius(px) {
		document.documentElement.style.setProperty('--image-radius', px + 'px');
		const styleEl = ensureStyleEl();
		if (styleEl && styleEl.textContent.indexOf('--image-radius') !== -1) {
			styleEl.textContent = styleEl.textContent.replace(
				/--image-radius:\s*[\d.]+px/,
				'--image-radius:' + px + 'px'
			);
		}
	}

	wp.customize('image_radius[radiusPx]', function (value) {
		value.bind(function (next) {
			applyRadius(Number(next) || 0);
		});
	});
})(window.wp);
