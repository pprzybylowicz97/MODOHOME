<?php
/**
 * Rejestracja i warunkowe ładowanie zasobów CSS/JS.
 *
 * @package MODOhome\Catalog
 */

declare( strict_types = 1 );

namespace MODOhome\Catalog;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Zasoby frontendu ładują się wyłącznie na podstronach z shortcode’em wtyczki.
 */
class Assets {

	public const HANDLE_CATALOG = 'modohome-catalog';
	public const HANDLE_FORM    = 'modohome-catalog-form';
	public const HANDLE_ADMIN   = 'modohome-catalog-admin';

	/**
	 * Czy zasoby katalogu zostały już zakolejkowane.
	 */
	private bool $catalog_enqueued = false;

	/**
	 * Czy zasoby formularza zostały już zakolejkowane.
	 */
	private bool $form_enqueued = false;

	/**
	 * Shortcode’y wykryte w treści bieżącej strony.
	 *
	 * @var array<string,bool>
	 */
	private array $detected = array(
		'catalog' => false,
		'form'    => false,
	);

	/**
	 * Podpina rejestrację zasobów.
	 */
	public function register(): void {
		add_action( 'wp', array( $this, 'detect_shortcodes' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_frontend' ), 5 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_detected' ), 10 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin' ) );
	}

	/**
	 * Sprawdza, czy bieżąca treść zawiera shortcode wtyczki.
	 *
	 * Dzięki temu style trafiają do sekcji <head>, a nie dopiero do stopki —
	 * i nie ładują się na podstronach, które katalogu nie używają.
	 */
	public function detect_shortcodes(): void {
		if ( is_admin() ) {
			return;
		}

		$post = get_post();

		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		$content = (string) $post->post_content;

		if ( has_shortcode( $content, 'modohome_catalog' ) ) {
			$this->detected['catalog'] = true;
		}

		if ( has_shortcode( $content, 'modohome_product_form' ) ) {
			$this->detected['form'] = true;
		}

		if ( has_shortcode( $content, 'modohome_my_products' ) ) {
			$this->detected['form']    = true;
			$this->detected['catalog'] = true;
		}
	}

	/**
	 * Kolejkuje zasoby wykryte w treści strony.
	 */
	public function enqueue_detected(): void {
		if ( $this->detected['catalog'] ) {
			$this->enqueue_catalog();
		}

		if ( $this->detected['form'] ) {
			$this->enqueue_form();
		}
	}

	/**
	 * Rejestruje (bez kolejkowania) zasoby frontendu.
	 */
	public function register_frontend(): void {
		wp_register_style(
			self::HANDLE_CATALOG,
			MODOHOME_CATALOG_URL . 'assets/css/catalog.css',
			array(),
			MODOHOME_CATALOG_VERSION
		);

		wp_register_script(
			self::HANDLE_CATALOG,
			MODOHOME_CATALOG_URL . 'assets/js/catalog.js',
			array(),
			MODOHOME_CATALOG_VERSION,
			true
		);

		wp_register_style(
			self::HANDLE_FORM,
			MODOHOME_CATALOG_URL . 'assets/css/form.css',
			array(),
			MODOHOME_CATALOG_VERSION
		);

		wp_register_script(
			self::HANDLE_FORM,
			MODOHOME_CATALOG_URL . 'assets/js/form.js',
			array(),
			MODOHOME_CATALOG_VERSION,
			true
		);
	}

	/**
	 * Kolejkuje zasoby katalogu (wywoływane z shortcode’u).
	 */
	public function enqueue_catalog(): void {
		if ( $this->catalog_enqueued ) {
			return;
		}

		$this->catalog_enqueued = true;

		wp_enqueue_style( self::HANDLE_CATALOG );
		wp_add_inline_style( self::HANDLE_CATALOG, $this->build_inline_css() );

		wp_enqueue_script( self::HANDLE_CATALOG );
		wp_localize_script( self::HANDLE_CATALOG, 'modohomeCatalogData', $this->catalog_script_data() );
	}

	/**
	 * Kolejkuje zasoby formularza (wywoływane z shortcode’ów).
	 */
	public function enqueue_form(): void {
		if ( $this->form_enqueued ) {
			return;
		}

		$this->form_enqueued = true;

		wp_enqueue_style( self::HANDLE_FORM );
		wp_add_inline_style( self::HANDLE_FORM, $this->build_inline_css() );

		wp_enqueue_script( self::HANDLE_FORM );
		wp_localize_script( self::HANDLE_FORM, 'modohomeFormData', $this->form_script_data() );
	}

	/**
	 * Dane przekazywane do skryptu katalogu.
	 *
	 * @return array<string,mixed>
	 */
	private function catalog_script_data(): array {
		return array(
			'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( Ajax::NONCE_PUBLIC ),
			'strings'  => array(
				'loading'   => __( 'Wczytywanie…', 'modohome-katalog-produktow' ),
				'loadMore'  => __( 'Pokaż więcej', 'modohome-katalog-produktow' ),
				'noResults' => __( 'Nie znaleziono produktów.', 'modohome-katalog-produktow' ),
				'error'     => __( 'Coś poszło nie tak. Odśwież stronę i spróbuj ponownie.', 'modohome-katalog-produktow' ),
				'close'     => __( 'Zamknij', 'modohome-katalog-produktow' ),
				'prev'      => __( 'Poprzednie zdjęcie', 'modohome-katalog-produktow' ),
				'next'      => __( 'Następne zdjęcie', 'modohome-katalog-produktow' ),
			),
		);
	}

	/**
	 * Dane przekazywane do skryptu formularzy.
	 *
	 * @return array<string,mixed>
	 */
	private function form_script_data(): array {
		return array(
			'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
			'nonce'       => wp_create_nonce( Ajax::NONCE_PRIVATE ),
			'maxFileSize' => Image_Handler::max_upload_bytes(),
			'maxFileText' => size_format( Image_Handler::max_upload_bytes() ),
			'allowed'     => array( 'image/jpeg', 'image/png', 'image/webp' ),
			'strings'     => array(
				'saving'      => __( 'Zapisywanie…', 'modohome-katalog-produktow' ),
				'added'       => __( 'Produkt został dodany', 'modohome-katalog-produktow' ),
				'updated'     => __( 'Zmiany zapisane', 'modohome-katalog-produktow' ),
				'addAnother'  => __( 'Dodaj kolejny produkt', 'modohome-katalog-produktow' ),
				'error'       => __( 'Nie udało się zapisać produktu. Spróbuj ponownie.', 'modohome-katalog-produktow' ),
				'tooLarge'    => __( 'Zdjęcie jest za duże. Maksymalny rozmiar to %s.', 'modohome-katalog-produktow' ),
				'badType'     => __( 'Dozwolone formaty zdjęć to JPG, PNG i WebP.', 'modohome-katalog-produktow' ),
				'noImage'     => __( 'Dodaj zdjęcie produktu.', 'modohome-katalog-produktow' ),
				'noTitle'     => __( 'Podaj nazwę produktu.', 'modohome-katalog-produktow' ),
				'noPrice'     => __( 'Podaj cenę produktu.', 'modohome-katalog-produktow' ),
				'noCategory'  => __( 'Wybierz kategorię produktu.', 'modohome-katalog-produktow' ),
				'confirmTrash' => __( 'Przenieść ten produkt do kosza?', 'modohome-katalog-produktow' ),
				'trashed'     => __( 'Produkt przeniesiony do kosza', 'modohome-katalog-produktow' ),
				'duplicated'  => __( 'Produkt zduplikowany', 'modohome-katalog-produktow' ),
			),
		);
	}

	/**
	 * Zmienne CSS zbudowane z ustawień wtyczki plus własny CSS administratora.
	 */
	private function build_inline_css(): string {
		$radius = Settings::int( 'border_radius' );
		$gap    = Settings::int( 'grid_gap' );
		$height = Settings::int( 'card_min_height' );

		$vars = array(
			'--modohome-catalog-primary'    => (string) Settings::get( 'primary_color', '#ef1616' ),
			'--modohome-catalog-text'       => (string) Settings::get( 'text_color', '#111111' ),
			'--modohome-catalog-bg'         => (string) Settings::get( 'bg_color', '#ffffff' ),
			'--modohome-catalog-card'       => (string) Settings::get( 'card_color', '#ffffff' ),
			'--modohome-catalog-badge'      => (string) Settings::get( 'badge_color', '#ef1616' ),
			'--modohome-catalog-radius'     => $radius . 'px',
			'--modohome-catalog-gap'        => $gap . 'px',
			'--modohome-catalog-ratio'      => Settings::ratio_to_css( (string) Settings::get( 'image_ratio', '3:4' ) ),
			'--modohome-catalog-fit'        => (string) Settings::get( 'image_fit', 'cover' ),
			'--modohome-catalog-cols-desktop' => (string) Settings::int( 'columns_desktop' ),
			'--modohome-catalog-cols-tablet'  => (string) Settings::int( 'columns_tablet' ),
			'--modohome-catalog-cols-mobile'  => (string) Settings::int( 'columns_mobile' ),
			'--modohome-catalog-card-height'  => $height > 0 ? $height . 'px' : 'auto',
			'--modohome-catalog-shadow'       => Settings::bool( 'card_shadow' ) ? '0 2px 10px rgba(0,0,0,.10)' : 'none',
		);

		$css = ':root{';

		foreach ( $vars as $name => $value ) {
			$css .= $name . ':' . $value . ';';
		}

		$css .= '}';

		$custom = (string) Settings::get( 'custom_css', '' );

		if ( '' !== trim( $custom ) ) {
			$css .= "\n" . Settings::sanitize_css( $custom );
		}

		return $css;
	}

	/**
	 * Zasoby panelu administracyjnego — tylko na ekranach wtyczki.
	 *
	 * @param string $hook_suffix Identyfikator bieżącego ekranu.
	 */
	public function enqueue_admin( string $hook_suffix ): void {
		$screen     = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$is_product = $screen instanceof \WP_Screen && (
			Post_Type::SLUG === $screen->post_type || Taxonomy::SLUG === $screen->taxonomy
		);

		$plugin_pages = array( 'modohome-catalog-settings', 'modohome-catalog-tools', 'modohome-catalog-log' );
		$is_page      = false;

		foreach ( $plugin_pages as $page ) {
			if ( str_contains( $hook_suffix, $page ) ) {
				$is_page = true;
				break;
			}
		}

		if ( ! $is_product && ! $is_page ) {
			return;
		}

		wp_enqueue_style(
			self::HANDLE_ADMIN,
			MODOHOME_CATALOG_URL . 'assets/css/admin.css',
			array(),
			MODOHOME_CATALOG_VERSION
		);

		// Wybór zdjęć korzysta z biblioteki mediów WordPressa.
		if ( $is_product && in_array( $screen?->base, array( 'post' ), true ) ) {
			wp_enqueue_media();
		}

		if ( $is_page ) {
			wp_enqueue_style( 'wp-color-picker' );
		}

		wp_enqueue_script(
			self::HANDLE_ADMIN,
			MODOHOME_CATALOG_URL . 'assets/js/admin.js',
			$is_page ? array( 'wp-color-picker', 'jquery' ) : array(),
			MODOHOME_CATALOG_VERSION,
			true
		);

		wp_localize_script(
			self::HANDLE_ADMIN,
			'modohomeAdminData',
			array(
				'strings' => array(
					'selectImage'  => __( 'Wybierz zdjęcie', 'modohome-katalog-produktow' ),
					'useImage'     => __( 'Użyj tego zdjęcia', 'modohome-katalog-produktow' ),
					'selectGallery' => __( 'Wybierz zdjęcia do galerii', 'modohome-katalog-produktow' ),
					'useGallery'   => __( 'Dodaj do galerii', 'modohome-katalog-produktow' ),
					'confirmReset' => __( 'Przywrócić wszystkie ustawienia domyślne? Tej operacji nie można cofnąć.', 'modohome-katalog-produktow' ),
					'remove'       => __( 'Usuń', 'modohome-katalog-produktow' ),
				),
			)
		);
	}
}
