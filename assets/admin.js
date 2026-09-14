(function () {
	'use strict';

	function bindPair(rangeId, numberId, onChange) {
		const rangeInput = document.getElementById(rangeId);
		const numberInput = document.getElementById(numberId);
		if (!rangeInput || !numberInput) {
			return;
		}

		function syncFrom(source, target) {
			target.value = source.value;
			onChange(Number(source.value));
		}

		rangeInput.addEventListener('input', function () {
			syncFrom(rangeInput, numberInput);
		});
		numberInput.addEventListener('input', function () {
			syncFrom(numberInput, rangeInput);
		});
		onChange(Number(rangeInput.value));
	}

	function setPreviewRadius(previewName, radiusCss) {
		const preview = document.querySelector('[data-preview="' + previewName + '"] img');
		if (!preview) {
			return;
		}
		preview.style.borderRadius = radiusCss;
	}

	bindPair('image-radius-px', 'image-radius-px-number', function (px) {
		setPreviewRadius('content', Math.max(0, px) + 'px');
	});

	bindPair('image-radius-icon', 'image-radius-icon-number', function (percent) {
		setPreviewRadius('icon', Math.max(0, percent) + '%');
	});
})();
