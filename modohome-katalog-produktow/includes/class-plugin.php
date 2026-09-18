<?php
/**
 * Główna klasa wtyczki — spina moduły.
 *
 * @package MODOhome\Catalog
 */

declare( strict_types = 1 );

namespace MODOhome\Catalog;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Każdy moduł jest osobną klasą z metodą register().
 */
class Plugin {

	private Assets $assets;

	/**
	 * Uruchamia wtyczkę.
	 */
	public function boot(): void {
		$this->assets = new Assets();

		add_action( 'init', array( $this, 'load_textdomain' ), 1 );
		add_action( 'init', array( $this, 'maybe_flush_rewrite' ), 99 );

		( new Post_Type() )->register();
		( new Taxonomy() )->register();
		( new Product() )->register();
		( new Roles() )->register();
		( new Activity_Log() )->register();
		( new Cron() )->register();

		$this->assets->register();

		( new Shortcodes( $this->assets ) )->register();
		( new Ajax() )->register();

		if ( is_admin() ) {
			( new Admin\Admin() )->register();
		}
	}

	/**
	 * Zwraca menedżera zasobów.
	 */
	public function assets(): Assets {
		return $this->assets;
	}

	/**
	 * Ładuje tłumaczenia.
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'modohome-katalog-produktow',
			false,
			dirname( MODOHOME_CATALOG_BASENAME ) . '/languages'
		);
	}

	/**
	 * Odświeża reguły przepisywania po zmianie ustawienia podstron produktu.
	 */
	public function maybe_flush_rewrite(): void {
		if ( get_transient( 'modohome_catalog_flush_rewrite' ) ) {
			delete_transient( 'modohome_catalog_flush_rewrite' );
			flush_rewrite_rules();
		}
	}

	/**
	 * Wczytuje szablon, pozwalając motywowi go nadpisać.
	 *
	 * Motyw może umieścić własną wersję w katalogu
	 * „modohome-katalog-produktow/<nazwa>.php”.
	 *
	 * @param string              $template Nazwa pliku szablonu bez rozszerzenia.
	 * @param array<string,mixed> $data     Dane dostępne w szablonie.
	 */
	public static function render_template( string $template, array $data = array() ): string {
		$template = sanitize_file_name( $template );
		$override = locate_template( array( 'modohome-katalog-produktow/' . $template . '.php' ) );
		$file     = $override ?: MODOHOME_CATALOG_DIR . 'templates/' . $template . '.php';

		if ( ! is_readable( $file ) ) {
			return '';
		}

		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract
		extract( $data, EXTR_SKIP );

		ob_start();

		include $file;

		return (string) ob_get_clean();
	}
}
