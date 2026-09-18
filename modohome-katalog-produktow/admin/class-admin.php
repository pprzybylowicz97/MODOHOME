<?php
/**
 * Spina moduły panelu administracyjnego.
 *
 * @package MODOhome\Catalog
 */

declare( strict_types = 1 );

namespace MODOhome\Catalog\Admin;

use MODOhome\Catalog\Post_Type;
use MODOhome\Catalog\Roles;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wszystkie ekrany wtyczki żyją w menu „Produkty”.
 */
class Admin {

	/**
	 * Podpina moduły panelu.
	 */
	public function register(): void {
		( new Metaboxes() )->register();
		( new Columns() )->register();
		( new Settings_Page() )->register();
		( new Tools_Page() )->register();
		( new Log_Page() )->register();

		add_action( 'admin_menu', array( $this, 'reorder_submenu' ), 99 );
		add_filter( 'plugin_action_links_' . MODOHOME_CATALOG_BASENAME, array( $this, 'add_settings_link' ) );
	}

	/**
	 * Ustawienia jako ostatnia pozycja podmenu.
	 */
	public function reorder_submenu(): void {
		global $submenu;

		$parent = 'edit.php?post_type=' . Post_Type::SLUG;

		if ( empty( $submenu[ $parent ] ) ) {
			return;
		}

		// Pozostawiamy kolejność nadaną przez add_submenu_page — metoda istnieje,
		// by inne wtyczki mogły się pod nią podpiąć filtrem.
		$submenu[ $parent ] = array_values( $submenu[ $parent ] ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	}

	/**
	 * Skrót do ustawień na liście wtyczek.
	 *
	 * @param array<int,string> $links Istniejące odnośniki.
	 *
	 * @return array<int,string>
	 */
	public function add_settings_link( array $links ): array {
		if ( ! current_user_can( Roles::CAP_MANAGE ) ) {
			return $links;
		}

		$url = admin_url( 'edit.php?post_type=' . Post_Type::SLUG . '&page=modohome-catalog-settings' );

		array_unshift(
			$links,
			'<a href="' . esc_url( $url ) . '">' . esc_html__( 'Ustawienia', 'modohome-katalog-produktow' ) . '</a>'
		);

		return $links;
	}
}
