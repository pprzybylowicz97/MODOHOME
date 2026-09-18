/**
 * Katalog produktów MODOhome — filtrowanie, wyszukiwanie, doładowywanie i okno modalne.
 * Czysty JavaScript, bez zewnętrznych bibliotek.
 */
( function () {
	'use strict';

	var data = window.modohomeCatalogData || {};
	var strings = data.strings || {};

	if ( ! data.ajaxUrl ) {
		return;
	}

	var FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

	/**
	 * Wysyła żądanie POST do admin-ajax.php.
	 *
	 * @param {Object} payload Pola żądania.
	 * @return {Promise<Object>} Odpowiedź serwera.
	 */
	function post( payload ) {
		var body = new FormData();

		Object.keys( payload ).forEach( function ( key ) {
			var value = payload[ key ];

			if ( Array.isArray( value ) ) {
				value.forEach( function ( item ) {
					body.append( key + '[]', item );
				} );
				return;
			}

			if ( value !== null && value !== undefined ) {
				body.append( key, value );
			}
		} );

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

	/* ---------------------------------------------------------------------
	 * Okno modalne — jedno na stronę, współdzielone przez wszystkie katalogi.
	 * ------------------------------------------------------------------ */

	var modal = null;
	var lastFocused = null;
	var galleryImages = [];
	var galleryIndex = 0;

	/**
	 * Tworzy szkielet okna modalnego przy pierwszym użyciu.
	 *
	 * @return {HTMLElement} Element okna.
	 */
	function ensureModal() {
		if ( modal ) {
			return modal;
		}

		modal = document.createElement( 'div' );
		modal.className = 'modohome-catalog modohome-catalog-modal';
		modal.setAttribute( 'role', 'dialog' );
		modal.setAttribute( 'aria-modal', 'true' );
		modal.setAttribute( 'aria-labelledby', 'modohome-catalog-modal-title' );
		modal.hidden = true;

		modal.innerHTML =
			'<button type="button" class="modohome-catalog-modal-backdrop" tabindex="-1" aria-hidden="true"></button>' +
			'<div class="modohome-catalog-modal-dialog">' +
			'<button type="button" class="modohome-catalog-modal-close" data-modohome-close aria-label="' + escapeAttr( strings.close || 'Zamknij' ) + '">&times;</button>' +
			'<div class="modohome-catalog-modal-body" data-modohome-modal-body></div>' +
			'</div>';

		document.body.appendChild( modal );

		modal.querySelector( '.modohome-catalog-modal-backdrop' ).addEventListener( 'click', closeModal );
		modal.querySelector( '[data-modohome-close]' ).addEventListener( 'click', closeModal );

		modal.addEventListener( 'keydown', function ( event ) {
			if ( event.key === 'Escape' ) {
				event.preventDefault();
				closeModal();
				return;
			}

			if ( event.key === 'Tab' ) {
				trapFocus( event );
			}
		} );

		return modal;
	}

	/**
	 * Ucieczka znaków w atrybucie.
	 *
	 * @param {string} value Tekst.
	 * @return {string} Tekst bezpieczny w atrybucie.
	 */
	function escapeAttr( value ) {
		return String( value ).replace( /"/g, '&quot;' ).replace( /</g, '&lt;' );
	}

	/**
	 * Zamyka pętlę Tab wewnątrz okna modalnego.
	 *
	 * @param {KeyboardEvent} event Zdarzenie klawiatury.
	 */
	function trapFocus( event ) {
		var focusable = Array.prototype.slice.call( modal.querySelectorAll( FOCUSABLE ) ).filter( function ( el ) {
			return el.offsetParent !== null || el === document.activeElement;
		} );

		if ( ! focusable.length ) {
			return;
		}

		var first = focusable[ 0 ];
		var last = focusable[ focusable.length - 1 ];

		if ( event.shiftKey && document.activeElement === first ) {
			event.preventDefault();
			last.focus();
		} else if ( ! event.shiftKey && document.activeElement === last ) {
			event.preventDefault();
			first.focus();
		}
	}

	/**
	 * Otwiera okno modalne produktu.
	 *
	 * @param {number} productId Identyfikator produktu.
	 */
	function openModal( productId ) {
		var element = ensureModal();
		var body = element.querySelector( '[data-modohome-modal-body]' );

		lastFocused = document.activeElement;

		body.innerHTML = '<p class="modohome-catalog-modal-loading">' + ( strings.loading || 'Wczytywanie…' ) + '</p>';
		element.hidden = false;
		document.body.style.overflow = 'hidden';

		element.querySelector( '[data-modohome-close]' ).focus();

		post( {
			action: 'modohome_catalog_details',
			nonce: data.nonce,
			product_id: productId
		} ).then( function ( response ) {
			if ( ! response || ! response.success ) {
				body.innerHTML = '<p class="modohome-catalog-modal-loading">' + ( strings.error || 'Błąd' ) + '</p>';
				return;
			}

			body.innerHTML = response.data.html;
			initGallery( body );
		} ).catch( function () {
			body.innerHTML = '<p class="modohome-catalog-modal-loading">' + ( strings.error || 'Błąd' ) + '</p>';
		} );
	}

	/**
	 * Zamyka okno modalne i przywraca fokus.
	 */
	function closeModal() {
		if ( ! modal || modal.hidden ) {
			return;
		}

		modal.hidden = true;
		document.body.style.overflow = '';
		galleryImages = [];
		galleryIndex = 0;

		if ( lastFocused && typeof lastFocused.focus === 'function' ) {
			lastFocused.focus();
		}
	}

	/**
	 * Podpina obsługę galerii wewnątrz okna modalnego.
	 *
	 * @param {HTMLElement} scope Kontener zawartości okna.
	 */
	function initGallery( scope ) {
		var main = scope.querySelector( '[data-modohome-gallery-main]' );
		var thumbs = Array.prototype.slice.call( scope.querySelectorAll( '[data-modohome-gallery-thumb]' ) );

		if ( ! main ) {
			return;
		}

		galleryImages = thumbs.length
			? thumbs.map( function ( thumb ) {
				return { url: thumb.getAttribute( 'data-full' ), alt: thumb.getAttribute( 'aria-label' ) || '' };
			} )
			: [ { url: main.getAttribute( 'src' ), alt: main.getAttribute( 'alt' ) || '' } ];

		galleryIndex = 0;

		/**
		 * Pokazuje zdjęcie o podanym indeksie.
		 *
		 * @param {number} index Indeks zdjęcia.
		 */
		function show( index ) {
			if ( ! galleryImages.length ) {
				return;
			}

			galleryIndex = ( index + galleryImages.length ) % galleryImages.length;
			main.setAttribute( 'src', galleryImages[ galleryIndex ].url );

			thumbs.forEach( function ( thumb, position ) {
				thumb.classList.toggle( 'is-active', position === galleryIndex );
			} );
		}

		thumbs.forEach( function ( thumb, position ) {
			thumb.addEventListener( 'click', function () {
				show( position );
			} );
		} );

		var prev = scope.querySelector( '[data-modohome-gallery-prev]' );
		var next = scope.querySelector( '[data-modohome-gallery-next]' );

		if ( prev ) {
			prev.addEventListener( 'click', function () {
				show( galleryIndex - 1 );
			} );
		}

		if ( next ) {
			next.addEventListener( 'click', function () {
				show( galleryIndex + 1 );
			} );
		}
	}

	/* ---------------------------------------------------------------------
	 * Pojedyncza instancja katalogu.
	 * ------------------------------------------------------------------ */

	/**
	 * Inicjalizuje katalog.
	 *
	 * @param {HTMLElement} root Kontener katalogu.
	 */
	function initCatalog( root ) {
		var config = {};

		try {
			config = JSON.parse( root.getAttribute( 'data-config' ) || '{}' );
		} catch ( error ) {
			config = {};
		}

		var grid = root.querySelector( '[data-modohome-grid]' );
		var empty = root.querySelector( '[data-modohome-empty]' );
		var status = root.querySelector( '[data-modohome-status]' );
		var loadMore = root.querySelector( '[data-modohome-load-more]' );
		var searchInput = root.querySelector( '[data-modohome-search]' );
		var sortSelect = root.querySelector( '[data-modohome-sort]' );
		var filterButtons = Array.prototype.slice.call( root.querySelectorAll( '[data-modohome-filter]' ) );

		if ( ! grid ) {
			return;
		}

		var state = {
			page: 1,
			search: '',
			orderby: config.orderby || 'date',
			categories: config.categories || [],
			busy: false
		};

		/**
		 * Wysyła zapytanie o produkty.
		 *
		 * @param {boolean} append Czy dopisać wyniki, czy podmienić.
		 */
		function load( append ) {
			if ( state.busy ) {
				return;
			}

			state.busy = true;
			grid.classList.add( 'is-loading' );

			if ( status ) {
				status.textContent = strings.loading || '';
			}

			if ( loadMore ) {
				loadMore.disabled = true;
			}

			post( {
				action: 'modohome_catalog_filter',
				nonce: data.nonce,
				page: state.page,
				per_page: config.perPage || 12,
				limit: config.limit || 0,
				search: state.search,
				orderby: state.orderby,
				show_sold: config.showSold || 'show',
				categories: state.categories
			} ).then( function ( response ) {
				state.busy = false;
				grid.classList.remove( 'is-loading' );

				if ( loadMore ) {
					loadMore.disabled = false;
				}

				if ( ! response || ! response.success ) {
					if ( status ) {
						status.textContent = strings.error || '';
					}
					return;
				}

				if ( append ) {
					grid.insertAdjacentHTML( 'beforeend', response.data.html );
				} else {
					grid.innerHTML = response.data.html;
				}

				if ( status ) {
					status.textContent = '';
				}

				var isEmpty = ! grid.children.length;

				if ( empty ) {
					empty.hidden = ! isEmpty;
				}

				if ( loadMore ) {
					loadMore.hidden = ! response.data.hasMore || config.loadMore === false;
				}
			} ).catch( function () {
				state.busy = false;
				grid.classList.remove( 'is-loading' );

				if ( loadMore ) {
					loadMore.disabled = false;
				}

				if ( status ) {
					status.textContent = strings.error || '';
				}
			} );
		}

		/**
		 * Wraca na pierwszą stronę i przeładowuje wyniki.
		 */
		function reload() {
			state.page = 1;
			load( false );
		}

		if ( loadMore ) {
			loadMore.addEventListener( 'click', function () {
				state.page += 1;
				load( true );
			} );
		}

		if ( searchInput ) {
			var timer = null;

			searchInput.addEventListener( 'input', function () {
				window.clearTimeout( timer );

				timer = window.setTimeout( function () {
					state.search = searchInput.value.trim();
					reload();
				}, 350 );
			} );

			searchInput.addEventListener( 'keydown', function ( event ) {
				if ( event.key === 'Enter' ) {
					event.preventDefault();
					window.clearTimeout( timer );
					state.search = searchInput.value.trim();
					reload();
				}
			} );
		}

		if ( sortSelect ) {
			sortSelect.addEventListener( 'change', function () {
				state.orderby = sortSelect.value;
				reload();
			} );
		}

		filterButtons.forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				var value = button.getAttribute( 'data-modohome-filter' );

				filterButtons.forEach( function ( other ) {
					var active = other === button;
					other.classList.toggle( 'is-active', active );
					other.setAttribute( 'aria-pressed', active ? 'true' : 'false' );
				} );

				state.categories = value ? [ value ] : ( config.categories || [] );
				reload();
			} );
		} );

		// Delegacja: karty doładowane przez AJAX też otwierają okno modalne.
		grid.addEventListener( 'click', function ( event ) {
			var trigger = event.target.closest( '[data-modohome-open]' );

			if ( ! trigger || ! grid.contains( trigger ) ) {
				return;
			}

			event.preventDefault();
			openModal( trigger.getAttribute( 'data-modohome-open' ) );
		} );
	}

	/**
	 * Uruchamia wszystkie katalogi na stronie.
	 */
	function init() {
		var roots = document.querySelectorAll( '[data-modohome-catalog]' );

		Array.prototype.forEach.call( roots, initCatalog );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
