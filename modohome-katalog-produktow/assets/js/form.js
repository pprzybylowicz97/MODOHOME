/**
 * Formularz dodawania produktu i panel „Moje produkty”.
 * Wysyłka przez AJAX, bez przeładowania strony.
 */
( function () {
	'use strict';

	var data = window.modohomeFormData || {};
	var strings = data.strings || {};

	if ( ! data.ajaxUrl ) {
		return;
	}

	/**
	 * Wysyła FormData do admin-ajax.php.
	 *
	 * @param {FormData} body Dane formularza.
	 * @return {Promise<Object>} Odpowiedź serwera.
	 */
	function send( body ) {
		return fetch( data.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: body
		} ).then( function ( response ) {
			return response.json().catch( function () {
				throw new Error( 'bad-json' );
			} );
		} );
	}

	/**
	 * Pokazuje komunikat w kontenerze.
	 *
	 * @param {HTMLElement} box     Element komunikatu.
	 * @param {string}      message Treść.
	 * @param {string}      type    „success” lub „error”.
	 */
	function showMessage( box, message, type ) {
		if ( ! box ) {
			return;
		}

		box.textContent = message;
		box.className = 'modohome-catalog-message modohome-catalog-message--' + type;
		box.hidden = false;
	}

	/**
	 * Ukrywa komunikat.
	 *
	 * @param {HTMLElement} box Element komunikatu.
	 */
	function hideMessage( box ) {
		if ( box ) {
			box.hidden = true;
			box.textContent = '';
		}
	}

	/**
	 * Czyści komunikaty błędów pól.
	 *
	 * @param {HTMLElement} form Formularz.
	 */
	function clearFieldErrors( form ) {
		Array.prototype.forEach.call( form.querySelectorAll( '[data-modohome-error]' ), function ( node ) {
			node.hidden = true;
			node.textContent = '';
		} );

		Array.prototype.forEach.call( form.querySelectorAll( '.has-error' ), function ( node ) {
			node.classList.remove( 'has-error' );
		} );
	}

	/**
	 * Pokazuje błąd przy konkretnym polu.
	 *
	 * @param {HTMLElement} form    Formularz.
	 * @param {string}      field   Nazwa pola.
	 * @param {string}      message Treść błędu.
	 */
	function showFieldError( form, field, message ) {
		var node = form.querySelector( '[data-modohome-error="' + field + '"]' );

		if ( node ) {
			node.textContent = message;
			node.hidden = false;
		}

		var map = { title: 'title', price: 'price', category: 'categories[]' };
		var input = form.querySelector( '[name="' + ( map[ field ] || field ) + '"]' );

		if ( input ) {
			input.classList.add( 'has-error' );
		}
	}

	/**
	 * Sprawdza plik po stronie przeglądarki, zanim trafi na serwer.
	 *
	 * @param {File} file Wybrany plik.
	 * @return {string} Pusty ciąg, gdy plik jest poprawny.
	 */
	function validateFile( file ) {
		var allowed = data.allowed || [ 'image/jpeg', 'image/png', 'image/webp' ];

		if ( file.type && allowed.indexOf( file.type ) === -1 ) {
			return strings.badType || 'Nieprawidłowy format.';
		}

		if ( data.maxFileSize && file.size > data.maxFileSize ) {
			return ( strings.tooLarge || 'Plik za duży: %s.' ).replace( '%s', data.maxFileText || '' );
		}

		return '';
	}

	/* ---------------------------------------------------------------------
	 * Formularz dodawania produktu.
	 * ------------------------------------------------------------------ */

	/**
	 * Inicjalizuje formularz dodawania.
	 *
	 * @param {HTMLElement} wrap Kontener shortcode’u.
	 */
	function initAddForm( wrap ) {
		var form = wrap.querySelector( '[data-modohome-product-form]' );

		if ( ! form ) {
			return;
		}

		var fileInput = form.querySelector( '[data-modohome-file]' );
		var preview = form.querySelector( '[data-modohome-preview]' );
		var previewImage = form.querySelector( '[data-modohome-preview-image]' );
		var previewRemove = form.querySelector( '[data-modohome-preview-remove]' );
		var submit = form.querySelector( '[data-modohome-submit]' );
		var message = form.querySelector( '[data-modohome-message]' );
		var success = wrap.querySelector( '[data-modohome-success]' );
		var again = wrap.querySelector( '[data-modohome-again]' );
		var objectUrl = null;

		/**
		 * Usuwa podgląd zdjęcia i zwalnia pamięć.
		 */
		function resetPreview() {
			if ( objectUrl ) {
				URL.revokeObjectURL( objectUrl );
				objectUrl = null;
			}

			if ( preview ) {
				preview.hidden = true;
			}

			if ( previewImage ) {
				previewImage.removeAttribute( 'src' );
			}

			if ( fileInput ) {
				fileInput.value = '';
			}
		}

		if ( fileInput ) {
			fileInput.addEventListener( 'change', function () {
				clearFieldErrors( form );

				var file = fileInput.files && fileInput.files[ 0 ];

				if ( ! file ) {
					resetPreview();
					return;
				}

				var error = validateFile( file );

				if ( error ) {
					showFieldError( form, 'image', error );
					resetPreview();
					return;
				}

				if ( objectUrl ) {
					URL.revokeObjectURL( objectUrl );
				}

				objectUrl = URL.createObjectURL( file );

				if ( previewImage ) {
					previewImage.setAttribute( 'src', objectUrl );
					previewImage.setAttribute( 'alt', file.name );
				}

				if ( preview ) {
					preview.hidden = false;
				}
			} );
		}

		if ( previewRemove ) {
			previewRemove.addEventListener( 'click', resetPreview );
		}

		form.addEventListener( 'submit', function ( event ) {
			event.preventDefault();

			clearFieldErrors( form );
			hideMessage( message );

			var title = form.querySelector( '[name="title"]' );
			var price = form.querySelector( '[name="price"]' );
			var category = form.querySelector( '[name="categories[]"]' );
			var valid = true;

			if ( title && ! title.value.trim() ) {
				showFieldError( form, 'title', strings.noTitle || 'Podaj nazwę.' );
				valid = false;
			}

			if ( price && ! price.value.trim() ) {
				showFieldError( form, 'price', strings.noPrice || 'Podaj cenę.' );
				valid = false;
			}

			if ( category && ! category.value ) {
				showFieldError( form, 'category', strings.noCategory || 'Wybierz kategorię.' );
				valid = false;
			}

			if ( fileInput && ( ! fileInput.files || ! fileInput.files.length ) ) {
				showFieldError( form, 'image', strings.noImage || 'Dodaj zdjęcie.' );
				valid = false;
			}

			if ( ! valid ) {
				return;
			}

			var originalLabel = submit ? submit.textContent : '';

			if ( submit ) {
				submit.disabled = true;
				submit.textContent = strings.saving || 'Zapisywanie…';
			}

			send( new FormData( form ) ).then( function ( response ) {
				if ( submit ) {
					submit.disabled = false;
					submit.textContent = originalLabel;
				}

				if ( ! response || ! response.success ) {
					var payload = ( response && response.data ) || {};

					if ( payload.fields ) {
						Object.keys( payload.fields ).forEach( function ( field ) {
							showFieldError( form, field, payload.fields[ field ] );
						} );
					}

					showMessage( message, payload.message || strings.error || 'Błąd.', 'error' );
					return;
				}

				var product = response.data.product || {};

				// Ekran potwierdzenia z miniaturą, nazwą i ceną.
				if ( success ) {
					var successMessage = success.querySelector( '[data-modohome-success-message]' );
					var successImage = success.querySelector( '[data-modohome-success-image]' );
					var successName = success.querySelector( '[data-modohome-success-name]' );
					var successPrice = success.querySelector( '[data-modohome-success-price]' );

					if ( successMessage ) {
						successMessage.textContent = response.data.message || strings.added || '';
					}

					if ( successImage ) {
						if ( product.thumbnail ) {
							successImage.setAttribute( 'src', product.thumbnail );
							successImage.setAttribute( 'alt', product.title || '' );
							successImage.hidden = false;
						} else {
							successImage.hidden = true;
						}
					}

					if ( successName ) {
						successName.textContent = product.title || '';
					}

					if ( successPrice ) {
						successPrice.textContent = product.price_formatted || '';
					}

					success.hidden = false;
				}

				form.reset();
				resetPreview();
				form.hidden = true;

				if ( success && typeof success.scrollIntoView === 'function' ) {
					success.scrollIntoView( { behavior: 'smooth', block: 'center' } );
				}
			} ).catch( function () {
				if ( submit ) {
					submit.disabled = false;
					submit.textContent = originalLabel;
				}

				showMessage( message, strings.error || 'Błąd.', 'error' );
			} );
		} );

		if ( again ) {
			again.addEventListener( 'click', function () {
				if ( success ) {
					success.hidden = true;
				}

				form.hidden = false;
				hideMessage( message );
				clearFieldErrors( form );

				var firstField = form.querySelector( '[name="title"]' );

				if ( firstField ) {
					firstField.focus();
				}

				if ( typeof form.scrollIntoView === 'function' ) {
					form.scrollIntoView( { behavior: 'smooth', block: 'start' } );
				}
			} );
		}
	}

	/* ---------------------------------------------------------------------
	 * Panel „Moje produkty”.
	 * ------------------------------------------------------------------ */

	/**
	 * Inicjalizuje panel pracownika.
	 *
	 * @param {HTMLElement} panel Kontener panelu.
	 */
	function initMyProducts( panel ) {
		var nonce = panel.getAttribute( 'data-nonce' );
		var message = panel.querySelector( '[data-modohome-message]' );

		/**
		 * Wywołuje prostą akcję na produkcie.
		 *
		 * @param {HTMLElement} item   Element listy.
		 * @param {string}      action Nazwa akcji AJAX.
		 * @param {Object}      extra  Dodatkowe pola.
		 */
		function runAction( item, action, extra ) {
			var body = new FormData();

			body.append( 'action', action );
			body.append( 'nonce', nonce );
			body.append( 'product_id', item.getAttribute( 'data-product-id' ) );

			Object.keys( extra || {} ).forEach( function ( key ) {
				body.append( key, extra[ key ] );
			} );

			item.classList.add( 'is-busy' );

			send( body ).then( function ( response ) {
				item.classList.remove( 'is-busy' );

				if ( ! response || ! response.success ) {
					showMessage( message, ( response && response.data && response.data.message ) || strings.error, 'error' );
					return;
				}

				showMessage( message, response.data.message || '', 'success' );

				if ( action === 'modohome_catalog_trash_product' ) {
					item.remove();
					return;
				}

				if ( action === 'modohome_catalog_set_status' ) {
					var statusNode = item.querySelector( '[data-modohome-item-status]' );

					if ( statusNode ) {
						statusNode.textContent = response.data.label || '';
						statusNode.className = 'modohome-catalog-mine-status modohome-catalog-mine-status--' + ( response.data.status || '' );
					}
				}

				if ( action === 'modohome_catalog_duplicate_product' ) {
					window.setTimeout( function () {
						window.location.reload();
					}, 900 );
				}
			} ).catch( function () {
				item.classList.remove( 'is-busy' );
				showMessage( message, strings.error || 'Błąd.', 'error' );
			} );
		}

		Array.prototype.forEach.call( panel.querySelectorAll( '[data-modohome-item]' ), function ( item ) {
			var toggle = item.querySelector( '[data-modohome-toggle]' );
			var editor = item.querySelector( '.modohome-catalog-mine-editor' );
			var editForm = item.querySelector( '[data-modohome-edit-form]' );

			if ( toggle && editor ) {
				toggle.addEventListener( 'click', function () {
					var open = editor.hidden;

					editor.hidden = ! open;
					toggle.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
				} );
			}

			if ( editForm ) {
				editForm.addEventListener( 'submit', function ( event ) {
					event.preventDefault();

					var fileField = editForm.querySelector( '[type="file"]' );

					if ( fileField && fileField.files && fileField.files.length ) {
						var fileError = validateFile( fileField.files[ 0 ] );

						if ( fileError ) {
							showMessage( message, fileError, 'error' );
							return;
						}
					}

					var button = editForm.querySelector( '[type="submit"]' );
					var label = button ? button.textContent : '';

					if ( button ) {
						button.disabled = true;
						button.textContent = strings.saving || 'Zapisywanie…';
					}

					item.classList.add( 'is-busy' );

					send( new FormData( editForm ) ).then( function ( response ) {
						item.classList.remove( 'is-busy' );

						if ( button ) {
							button.disabled = false;
							button.textContent = label;
						}

						if ( ! response || ! response.success ) {
							showMessage( message, ( response && response.data && response.data.message ) || strings.error, 'error' );
							return;
						}

						var product = response.data.product || {};

						var nameNode = item.querySelector( '[data-modohome-item-name]' );
						var priceNode = item.querySelector( '[data-modohome-item-price]' );
						var statusNode = item.querySelector( '[data-modohome-item-status]' );
						var thumbNode = item.querySelector( '[data-modohome-item-thumb]' );

						if ( nameNode ) {
							nameNode.textContent = product.title || '';
						}

						if ( priceNode ) {
							priceNode.textContent = product.price_formatted || '';
						}

						if ( statusNode ) {
							statusNode.textContent = product.availability_label || '';
							statusNode.className = 'modohome-catalog-mine-status modohome-catalog-mine-status--' + ( product.availability || '' );
						}

						if ( thumbNode && product.thumbnail ) {
							thumbNode.setAttribute( 'src', product.thumbnail );
						}

						if ( fileField ) {
							fileField.value = '';
						}

						showMessage( message, response.data.message || strings.updated || '', 'success' );
					} ).catch( function () {
						item.classList.remove( 'is-busy' );

						if ( button ) {
							button.disabled = false;
							button.textContent = label;
						}

						showMessage( message, strings.error || 'Błąd.', 'error' );
					} );
				} );
			}

			var markSold = item.querySelector( '[data-modohome-mark-sold]' );

			if ( markSold ) {
				markSold.addEventListener( 'click', function () {
					runAction( item, 'modohome_catalog_set_status', { status: 'sold' } );
				} );
			}

			var duplicate = item.querySelector( '[data-modohome-duplicate]' );

			if ( duplicate ) {
				duplicate.addEventListener( 'click', function () {
					runAction( item, 'modohome_catalog_duplicate_product', {} );
				} );
			}

			var trash = item.querySelector( '[data-modohome-trash]' );

			if ( trash ) {
				trash.addEventListener( 'click', function () {
					if ( ! window.confirm( strings.confirmTrash || 'Na pewno?' ) ) {
						return;
					}

					runAction( item, 'modohome_catalog_trash_product', {} );
				} );
			}
		} );
	}

	/**
	 * Uruchamia oba panele.
	 */
	function init() {
		Array.prototype.forEach.call( document.querySelectorAll( '[data-modohome-form-wrap]' ), initAddForm );
		Array.prototype.forEach.call( document.querySelectorAll( '[data-modohome-my-products]' ), initMyProducts );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
