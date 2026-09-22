<?php
/**
 * Przepuszcza wygenerowany CSV przez tę samą logikę parsowania i sanityzacji,
 * której używa importer wtyczki.
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

use MODOhome\Catalog\Admin\Tools_Page;
use MODOhome\Catalog\Product;

$path = dirname( __DIR__ ) . '/produkty-modohome.csv';
$errors = 0;

echo "== Parsowanie pliku tak, jak robi to importer ==\n";

$handle = fopen( $path, 'r' );
$header = fgetcsv( $handle, 0, ';', '"', '' );

// Importer usuwa BOM z pierwszej komórki nagłówka.
$header[0] = preg_replace( '/^\x{FEFF}/u', '', (string) $header[0] );
$header    = array_map( static fn( $v ): string => strtolower( trim( (string) $v ) ), $header );
$map       = array_flip( $header );

$expected = Tools_Page::columns();

if ( $header === $expected ) {
	echo "  OK   nagłówek zgodny z kolumnami wtyczki\n";
} else {
	echo "  BŁĄD nagłówek niezgodny\n    plik:    " . implode( ';', $header ) . "\n    wtyczka: " . implode( ';', $expected ) . "\n";
	++$errors;
}

foreach ( array( 'nazwa', 'cena', 'kategoria' ) as $required ) {
	if ( isset( $map[ $required ] ) ) {
		echo "  OK   kolumna wymagana obecna: {$required}\n";
	} else {
		echo "  BŁĄD brak kolumny wymaganej: {$required}\n";
		++$errors;
	}
}

echo "\n== Wiersze ==\n";

$seen = array();
$row_no = 0;

while ( false !== ( $row = fgetcsv( $handle, 0, ';', '"', '' ) ) ) {
	if ( ! is_array( $row ) || array_filter( $row, static fn( $v ): bool => '' !== trim( (string) $v ) ) === array() ) {
		continue;
	}

	++$row_no;

	$value = static function ( string $k ) use ( $row, $map ): string {
		return isset( $map[ $k ], $row[ $map[ $k ] ] ) ? trim( (string) $row[ $map[ $k ] ] ) : '';
	};

	$title  = sanitize_text_field( $value( 'nazwa' ) );
	$price  = Product::sanitize_price( $value( 'cena' ) );
	$cat    = $value( 'kategoria' );
	$status = Product::sanitize_availability( $value( 'status' ) );
	$badge  = Product::sanitize_badge( $value( 'etykieta' ) );

	$problems = array();

	if ( '' === $title )  { $problems[] = 'brak nazwy'; }
	if ( '' === $price )  { $problems[] = 'cena nie do odczytania'; }
	if ( '' === $cat )    { $problems[] = 'brak kategorii'; }
	if ( count( $row ) !== count( $expected ) ) { $problems[] = 'zła liczba kolumn: ' . count( $row ); }

	// Importer domyślnie pomija produkty o powtórzonej nazwie.
	$key = mb_strtolower( $title );
	if ( isset( $seen[ $key ] ) ) { $problems[] = 'DUPLIKAT NAZWY — zostanie pominięty'; }
	$seen[ $key ] = true;

	if ( empty( $problems ) ) {
		printf( "  OK   %-46s %8s  %-24s [%s]\n", mb_substr( $title, 0, 46 ), Product::format_price( (float) $price ), $cat, $status );
	} else {
		printf( "  BŁĄD %-46s -> %s\n", mb_substr( $title, 0, 46 ), implode( '; ', $problems ) );
		++$errors;
	}
}

fclose( $handle );

echo "\n== Podsumowanie ==\n";
printf( "  wierszy: %d, problemów: %d\n", $row_no, $errors );

exit( $errors > 0 ? 1 : 0 );
