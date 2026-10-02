<?php
/**
 * Wywołuje prawdziwą metodę Assets::build_inline_css() dla zadanych ustawień
 * i zapisuje wynik — dokładnie ten CSS trafia na stronę.
 */

require __DIR__ . '/wp-stubs.php';

define( 'MODOHOME_CATALOG_DIR', dirname( __DIR__ ) . '/modohome-katalog-produktow/' );
define( 'MODOHOME_CATALOG_VERSION', '1.2.0' );
define( 'MODOHOME_CATALOG_URL', 'https://example.test/' );
define( 'MODOHOME_CATALOG_BASENAME', 'p/p.php' );

spl_autoload_register(
	static function ( string $c ): void {
		if ( ! str_starts_with( $c, 'MODOhome\\Catalog\\' ) ) { return; }
		$r = substr( $c, strlen( 'MODOhome\\Catalog\\' ) );
		$p = explode( '\\', $r ); $short = array_pop( $p ); $sub = $p[0] ?? '';
		$d = match ( $sub ) { 'Admin' => 'admin/', 'Frontend' => 'public/', default => 'includes/' };
		$f = MODOHOME_CATALOG_DIR . $d . 'class-' . str_replace( '_', '-', strtolower( $short ) ) . '.php';
		if ( is_readable( $f ) ) { require_once $f; }
	}
);

function wp_register_style( ...$a ) {}
function wp_register_script( ...$a ) {}

use MODOhome\Catalog\Assets;
use MODOhome\Catalog\Settings;

// Ustawienia takie, jakie wpisałby administrator. Nadpisania można podać
// jako JSON w pierwszym argumencie, np.: php build-inline-css.php '{"image_ratio":"auto"}'
$settings = Settings::defaults();
$settings['columns_mobile']  = 1;
$settings['columns_tablet']  = 2;
$settings['columns_desktop'] = 4;
$settings['primary_color']   = '#00aa55';
$settings['grid_gap']        = 33;

if ( isset( $argv[1] ) ) {
	$overrides = json_decode( (string) $argv[1], true );

	if ( is_array( $overrides ) ) {
		$settings = array_merge( $settings, $overrides );
	}
}

$out_file = $argv[2] ?? 'inline.css';

$GLOBALS['stub_options']['modohome_catalog_settings'] = $settings;
Settings::flush();

$assets = new Assets();
$method = new ReflectionMethod( Assets::class, 'build_inline_css' );
$method->setAccessible( true );

file_put_contents( __DIR__ . '/' . basename( $out_file ), $method->invoke( $assets ) );

echo "zapisano " . basename( $out_file ) . "\n";
