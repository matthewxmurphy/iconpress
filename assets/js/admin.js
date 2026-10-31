/**
 * IconPress admin: media library picker for the slot edit form.
 * Vanilla JS against the wp.media API that WordPress provides.
 */
(function () {
	'use strict';

	var pickButton = document.getElementById('ip_icon_pick');
	var clearButton = document.getElementById('ip_icon_clear');
	var idField = document.getElementById('ip_icon_id');
	var urlField = document.getElementById('ip_icon_url');
	var preview = document.getElementById('ip_icon_preview');

	if (!pickButton || !window.wp || !wp.media) {
		return;
	}

	var frame = wp.media({
		title: 'Select or upload an icon',
		button: { text: 'Use this icon' },
		multiple: false,
		library: { type: 'image' }
	});

	frame.on('select', function () {
		var attachment = frame.state().get('selection').first().toJSON();
		if (idField) {
			idField.value = attachment.id;
		}
		if (urlField) {
			urlField.value = '';
		}
		if (preview) {
			preview.src = (attachment.sizes && attachment.sizes.thumbnail)
				? attachment.sizes.thumbnail.url
				: attachment.url;
		}
	});

	pickButton.addEventListener('click', function (event) {
		event.preventDefault();
		frame.open();
	});

	if (clearButton) {
		clearButton.addEventListener('click', function (event) {
			event.preventDefault();
			if (idField) {
				idField.value = '0';
			}
			if (preview) {
				preview.removeAttribute('src');
			}
		});
	}
})();
