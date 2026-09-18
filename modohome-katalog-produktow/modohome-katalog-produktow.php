<?php
/**
 * Plugin Name:       MODOhome Katalog Produktów
 * Plugin URI:        https://github.com/pprzybylowicz97/MODOHOME
 * Description:       Prosty katalog produktów sklepu stacjonarnego MODOhome. Prezentacja produktów, zdjęć i cen bez WooCommerce — bez koszyka, płatności i stanów magazynowych.
 * Version:           1.1.0
 * Requires at least: 6.1
 * Requires PHP:      8.1
 * Author:            MODOhome
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       modohome-katalog-produktow
 * Domain Path:       /languages
 *
 * @package MODOhome\Catalog
 */

declare( strict_types = 1 );

namespace MODOhome\Catalog;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MODOHOME_CATALOG_VERSION', '1.1.0' );
define( 'MODOHOME_CATALOG_FILE', __FILE__ );
define( 'MODOHOME_CATALOG_DIR', plugin_dir_path( __FILE__ ) );
define( 'MODOHOME_CATALOG_URL', plugin_dir_url( __FILE__ ) );
define( 'MODOHOME_CATALOG_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Minimalna wersja PHP. Wtyczka nie startuje na starszym środowisku.
 */
if ( version_compare( PHP_VERSION, '8.1', '<' ) ) {
	add_action(
		'admin_notices',
		static function (): void {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html__( 'MODOhome Katalog Produktów wymaga PHP 8.1 lub nowszego.', 'modohome-katalog-produktow' )
			);
		}
	);

	return;
}

/**
 * Prosty autoloader oparty na konwencji nazw plików WordPressa.
 *
 * MODOhome\Catalog\Post_Type          -> includes/class-post-type.php
 * MODOhome\Catalog\Admin\Settings_Page -> admin/class-settings-page.php
 * MODOhome\Catalog\Frontend\Catalog    -> public/class-catalog.php
 */
spl_autoload_register(
	static function ( string $class_name ): void {
		if ( ! str_starts_with( $class_name, __NAMESPACE__ . '\\' ) ) {
			return;
		}

		$relative = substr( $class_name, strlen( __NAMESPACE__ . '\\' ) );
		$parts    = explode( '\\', $relative );
		$short    = array_pop( $parts );
		$sub      = $parts[0] ?? '';

		$directory = match ( $sub ) {
			'Admin'    => 'admin/',
			'Frontend' => 'public/',
			default    => 'includes/',
		};

		$file = MODOHOME_CATALOG_DIR . $directory . 'class-' . str_replace( '_', '-', strtolower( $short ) ) . '.php';

		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);

require_once MODOHOME_CATALOG_DIR . 'includes/class-activator.php';

register_activation_hook( __FILE__, array( Activator::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( Activator::class, 'deactivate' ) );

/**
 * Zwraca instancję głównej klasy wtyczki.
 */
function modohome_catalog_plugin(): Plugin {
	static $instance = null;

	if ( null === $instance ) {
		$instance = new Plugin();
	}

	return $instance;
}

modohome_catalog_plugin()->boot();
