/**
 * Skrypty ekranów wtyczki w panelu WordPressa:
 * galeria produktu oparta o bibliotekę mediów, próbnik kolorów i potwierdzenie resetu.
 */
( function () {
	'use strict';

	var data = window.modohomeAdminData || {};
	var strings = data.strings || {};

	/**
	 * Obsługa galerii dodatkowych zdjęć na ekranie edycji produktu.
	 */
	function initGalleryBox() {
		var box = document.querySelector( '[data-modohome-gallery-box]' );

		if ( ! box || ! window.wp || ! window.wp.media ) {
			return;
		}

		var input = box.querySelector( '[data-modohome-gallery-input]' );
		var list = box.querySelector( '[data-modohome-gallery-list]' );
		var addButton = box.querySelector( '[data-modohome-gallery-add]' );
		var frame = null;

		/**
		 * Zapisuje bieżącą kolejność identyfikatorów w polu ukrytym.
		 */
		function syncInput() {
			var ids = Array.prototype.map.call( list.querySelectorAll( '[data-id]' ), function ( item ) {
				return item.getAttribute( 'data-id' );
			} );

			input.value = ids.join( ',' );
		}

		/**
		 * Dodaje miniaturę do listy.
		 *
		 * @param {number} id  Identyfikator załącznika.
		 * @param {string} url Adres miniatury.
		 */
		function addItem( id, url ) {
			if ( list.querySelector( '[data-id="' + id + '"]' ) ) {
				return;
			}

			var item = document.createElement( 'li' );
			item.className = 'modohome-admin-gallery-item';
			item.setAttribute( 'data-id', id );

			var image = document.createElement( 'img' );
			image.setAttribute( 'src', url );
			image.setAttribute( 'alt', '' );

			var remove = document.createElement( 'button' );
			remove.type = 'button';
			remove.className = 'modohome-admin-gallery-remove';
			remove.setAttribute( 'data-modohome-gallery-remove', '' );
			remove.setAttribute( 'aria-label', strings.remove || 'Usuń' );
			remove.innerHTML = '&times;';

			item.appendChild( image );
			item.appendChild( remove );
			list.appendChild( item );
		}

		if ( addButton ) {
			addButton.addEventListener( 'click', function ( event ) {
				event.preventDefault();

				if ( frame ) {
					frame.open();
					return;
				}

				frame = window.wp.media( {
					title: strings.selectGallery || 'Wybierz zdjęcia',
					button: { text: strings.useGallery || 'Dodaj' },
					library: { type: 'image' },
					multiple: 'add'
				} );

				frame.on( 'select', function () {
					var selection = frame.state().get( 'selection' );

					selection.each( function ( attachment ) {
						var item = attachment.toJSON();
						var url = ( item.sizes && item.sizes.thumbnail ) ? item.sizes.thumbnail.url : item.url;

						addItem( item.id, url );
					} );

					syncInput();
				} );

				frame.open();
			} );
		}

		list.addEventListener( 'click', function ( event ) {
			var button = event.target.closest( '[data-modohome-gallery-remove]' );

			if ( ! button ) {
				return;
			}

			event.preventDefault();

			var item = button.closest( '[data-id]' );

			if ( item ) {
				item.remove();
				syncInput();
			}
		} );
	}

	/**
	 * Próbnik kolorów i potwierdzenie przywrócenia ustawień domyślnych.
	 */
	function initSettings() {
		var reset = document.querySelector( '[data-modohome-reset]' );

		if ( reset ) {
			reset.addEventListener( 'click', function ( event ) {
				if ( ! window.confirm( strings.confirmReset || 'Na pewno?' ) ) {
					event.preventDefault();
				}
			} );
		}

		// wp-color-picker jest wtyczką jQuery — używamy go tylko na ekranie ustawień.
		if ( window.jQuery && typeof window.jQuery.fn.wpColorPicker === 'function' ) {
			window.jQuery( '.modohome-color-field' ).wpColorPicker();
		}
	}

	/**
	 * Uruchamia skrypty ekranów wtyczki.
	 */
	function init() {
		initGalleryBox();
		initSettings();
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
