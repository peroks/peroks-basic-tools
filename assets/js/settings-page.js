/**
 * Settings page image selector.
 *
 * This script enables image selection and removal functionality on a settings page.
 * It uses the WordPress media library to allow users to select images and updates
 * the corresponding input fields and image previews accordingly.
 *
 * Apart from the ready function, the code in this file is vanilla JavaScript.
 */
window.jQuery().ready(() => {
	// Get the WordPress media library frame.
	const wpMedia = wp.media({ multiple: false });

	// Create a new image element with the given attributes.
	const createImage = (attributes, alt) => {
		const image = document.createElement('img');

		if (attributes.width && attributes.height) {
			image.width = attributes.width;
			image.height = attributes.height;
		}
		image.src = attributes.url;
		image.alt = alt;
		return image;
	};

	const initFigure = (figure) => {
		// The hidden input field containing the current image ID.
		const input = figure.previousElementSibling;

		// The select image and remove image buttons.
		const select = figure.querySelector('button.select-image');
		const remove = figure.querySelector('button.remove-image');

		// Select image handler.
		const selectImageHandler = () => {
			// Open the media library frame and get the selected image attributes.
			wpMedia.open().on('select', () => {
				const attachment = wpMedia.state().get('selection').first().toJSON();
				const attributes = attachment.sizes?.[figure.dataset.size] || attachment;
				const image = createImage(attributes, attachment.alt);
				figure.querySelector('img')?.replaceWith(image);
				select.blur();
				remove.style.display = 'inline-block';
				input.value = attachment.id;
			});
		};

		// Remove image handler.
		const removeImageHandler = () => {
			const image = document.createElement('img');
			figure.querySelector('img')?.replaceWith(image);
			remove.style.display = 'none';
			input.value = 0;
		};

		// Add event listeners to the select and remove buttons.
		if (wpMedia && input && select && remove) {
			select.addEventListener('click', selectImageHandler);
			remove.addEventListener('click', removeImageHandler);
		}
	};

	// Initialize all figures on the settings page
	document.querySelectorAll('figure.peroks-tools-settings-page-image').forEach(initFigure);
});
