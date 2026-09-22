<?php
/**
 * Testy logiki wtyczki MODOhome na atrapach WordPressa.
 */

require __DIR__ . '/wp-stubs.php';

define( 'MODOHOME_CATALOG_VERSION', '1.0.0' );
define( 'MODOHOME_CATALOG_DIR', dirname( __DIR__ ) . '/modohome-katalog-produktow/' );
define( 'MODOHOME_CATALOG_URL', 'https://example.test/wp-content/plugins/modohome-katalog-produktow/' );
define( 'MODOHOME_CATALOG_BASENAME', 'modohome-katalog-produktow/modohome-katalog-produktow.php' );

// Ten sam autoloader, co w pliku głównym wtyczki.
spl_autoload_register(
	static function ( string $class_name ): void {
		if ( ! str_starts_with( $class_name, 'MODOhome\\Catalog\\' ) ) {
			return;
		}

		$relative = substr( $class_name, strlen( 'MODOhome\\Catalog\\' ) );
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

$passed = 0;
$failed = 0;

function check( string $label, $actual, $expected ): void {
	global $passed, $failed;

	if ( $actual === $expected ) {
		++$passed;
		return;
	}

	++$failed;
	printf(
		"  NIEPOWODZENIE: %s\n    oczekiwano: %s\n    otrzymano:  %s\n",
		$label,
		var_export( $expected, true ),
		var_export( $actual, true )
	);
}

function ok( string $label, bool $condition ): void {
	global $passed, $failed;

	if ( $condition ) {
		++$passed;
		return;
	}

	++$failed;
	echo "  NIEPOWODZENIE: {$label}\n";
}

use MODOhome\Catalog\Product;
use MODOhome\Catalog\Query;
use MODOhome\Catalog\Roles;
use MODOhome\Catalog\Settings;
use MODOhome\Catalog\Taxonomy;
use MODOhome\Catalog\Post_Type;

echo "== 1. Autoloader: czy każda klasa się ładuje ==\n";

$classes = array(
	'MODOhome\Catalog\Plugin',
	'MODOhome\Catalog\Activator',
	'MODOhome\Catalog\Settings',
	'MODOhome\Catalog\Roles',
	'MODOhome\Catalog\Post_Type',
	'MODOhome\Catalog\Taxonomy',
	'MODOhome\Catalog\Product',
	'MODOhome\Catalog\Product_Actions',
	'MODOhome\Catalog\Query',
	'MODOhome\Catalog\Image_Handler',
	'MODOhome\Catalog\Activity_Log',
	'MODOhome\Catalog\Shortcodes',
	'MODOhome\Catalog\Ajax',
	'MODOhome\Catalog\Assets',
	'MODOhome\Catalog\Cron',
	'MODOhome\Catalog\Admin\Admin',
	'MODOhome\Catalog\Admin\Metaboxes',
	'MODOhome\Catalog\Admin\Columns',
	'MODOhome\Catalog\Admin\Settings_Page',
	'MODOhome\Catalog\Admin\Tools_Page',
	'MODOhome\Catalog\Admin\Log_Page',
	'MODOhome\Catalog\Frontend\Catalog',
	'MODOhome\Catalog\Frontend\Product_Form',
	'MODOhome\Catalog\Frontend\My_Products',
);

foreach ( $classes as $class ) {
	ok( "klasa {$class} istnieje", class_exists( $class ) );
}

echo "== 2. Sanityzacja ceny ==\n";

check( 'liczba całkowita', Product::sanitize_price( '1200' ), '1200.00' );
check( 'przecinek dziesiętny', Product::sanitize_price( '1299,90' ), '1299.90' );
check( 'kropka dziesiętna', Product::sanitize_price( '1299.90' ), '1299.90' );
check( 'spacje tysięcy', Product::sanitize_price( '12 999,50' ), '12999.50' );
check( 'waluta w tekście', Product::sanitize_price( '349 zł' ), '349.00' );
check( 'wartość ujemna przycięta do zera', Product::sanitize_price( '-50' ), '0.00' );
check( 'pusty ciąg', Product::sanitize_price( '' ), '' );
check( 'sam tekst', Product::sanitize_price( 'abc' ), '' );
check( 'tablica odrzucona', Product::sanitize_price( array( 1 ) ), '' );
check( 'zaokrąglenie do groszy', Product::sanitize_price( '10,999' ), '11.00' );

echo "== 3. Etykiety i statusy ==\n";

check( 'poprawna etykieta', Product::sanitize_badge( 'promo' ), 'promo' );
check( 'nieznana etykieta odrzucona', Product::sanitize_badge( 'hacker' ), '' );
check( 'poprawny status', Product::sanitize_availability( 'sold' ), 'sold' );
check( 'nieznany status → dostępny', Product::sanitize_availability( '../../etc' ), 'available' );
check( 'brak statusu → dostępny', Product::sanitize_availability( '' ), 'available' );
ok( 'sześć etykiet (pięć ze specyfikacji + Ekspozycja z makiety)', 6 === count( Product::badges() ) );
ok( 'cztery statusy zgodnie ze specyfikacją', 4 === count( Product::statuses() ) );
check( 'etykieta „Nowość”', Product::badge_label( 'new' ), 'Nowość' );
check( 'status „Zarezerwowany”', Product::status_label( 'reserved' ), 'Zarezerwowany' );

echo "== 4. Formatowanie ceny z walutą ==\n";

$GLOBALS['stub_options']['modohome_catalog_settings'] = Settings::defaults();
Settings::flush();

check( 'pełne złote bez groszy', Product::format_price( 1200.0 ), '1 200 zł' );
check( 'z groszami', Product::format_price( 1299.9 ), '1 299,90 zł' );
check( 'null → pusty ciąg', Product::format_price( null ), '' );

$custom = Settings::defaults();
$custom['currency'] = 'EUR';
$custom['currency_position'] = 'before';
$GLOBALS['stub_options']['modohome_catalog_settings'] = $custom;
Settings::flush();

check( 'zmieniona waluta i pozycja', Product::format_price( 99.0 ), 'EUR 99' );

$GLOBALS['stub_options']['modohome_catalog_settings'] = Settings::defaults();
Settings::flush();

echo "== 5. Sanityzacja ustawień ==\n";

$dirty = array(
	'primary_color'   => '#ef1616',
	'text_color'      => 'javascript:alert(1)',
	'columns_desktop' => '99',
	'columns_mobile'  => '0',
	'per_page'        => '-5',
	'image_ratio'     => '666:1',
	'default_order'   => 'DROP TABLE',
	'currency'        => 'zł',
	'max_upload_size' => '999',
	'custom_css'      => '.a{color:red} <script>alert(1)</script> @import url(evil);',
	'show_category'   => '1',
);

$clean = Settings::sanitize( $dirty );

check( 'poprawny kolor zachowany', $clean['primary_color'], '#ef1616' );
check( 'niepoprawny kolor → domyślny', $clean['text_color'], '#111111' );
check( 'kolumny ograniczone do 6', $clean['columns_desktop'], 6 );
check( 'kolumny telefonu minimum 1', $clean['columns_mobile'], 1 );
check( 'produkty na stronę minimum 1', $clean['per_page'], 1 );
check( 'nieznane proporcje → domyślne', $clean['image_ratio'], '3:4' );
check( 'nieznane sortowanie → domyślne', $clean['default_order'], 'date' );
check( 'rozmiar pliku ograniczony do 64', $clean['max_upload_size'], 64 );
ok( 'znacznik script usunięty z CSS', ! str_contains( $clean['custom_css'], '<script>' ) );
ok( 'reguła @import usunięta z CSS', ! str_contains( $clean['custom_css'], '@import' ) );
ok( 'właściwa reguła CSS zachowana', str_contains( $clean['custom_css'], 'color:red' ) );
check( 'checkbox zaznaczony', $clean['show_category'], true );
check( 'checkbox pominięty → false', $clean['show_badges'], false );

echo "== 6. Własny CSS tylko dla administratora ==\n";

$GLOBALS['stub_options']['modohome_catalog_settings'] = array_merge( Settings::defaults(), array( 'custom_css' => '.zachowane{}' ) );
Settings::flush();
$GLOBALS['stub_caps'] = array();

$attempt = Settings::sanitize( array( 'custom_css' => '.wstrzykniete{}' ) );
check( 'bez uprawnień CSS bez zmian', $attempt['custom_css'], '.zachowane{}' );

$GLOBALS['stub_caps'] = array( 'manage_modohome_catalog' => true );
$allowed = Settings::sanitize( array( 'custom_css' => '.nowe{}' ) );
check( 'administrator zapisuje CSS', $allowed['custom_css'], '.nowe{}' );

echo "== 7. Przywracanie ustawień domyślnych ==\n";

$reset = Settings::sanitize( array( 'modohome_catalog_reset' => '1', 'columns_desktop' => 2 ) );
check( 'reset ignoruje pozostałe pola', $reset['columns_desktop'], 4 );
check( 'reset przywraca kolor główny', $reset['primary_color'], '#ef1616' );

echo "== 8. Proporcje zdjęć na CSS ==\n";

check( 'pionowe', Settings::ratio_to_css( '3:4' ), '3 / 4' );
check( 'kwadrat', Settings::ratio_to_css( '1:1' ), '1 / 1' );
check( 'nieprawidłowe → domyślne', Settings::ratio_to_css( 'xx' ), '3 / 4' );

echo "== 9. Normalizacja kategorii ==\n";

stub_add_term( 11, 'krzesla', 'Krzesła' );
stub_add_term( 12, 'meble', 'Meble' );

check( 'slug → identyfikator', Query::normalize_categories( 'krzesla' ), array( 11 ) );
check( 'lista slugów', Query::normalize_categories( 'krzesla,meble' ), array( 11, 12 ) );
check( 'identyfikator liczbowy', Query::normalize_categories( array( 12 ) ), array( 12 ) );
check( 'nieistniejąca kategoria odrzucona', Query::normalize_categories( 'nie-ma-takiej' ), array() );
check( 'duplikaty usunięte', Query::normalize_categories( 'krzesla,krzesla' ), array( 11 ) );
check( 'puste wejście', Query::normalize_categories( '' ), array() );

echo "== 10. Uprawnienia ==\n";

$worker = Roles::worker_caps();

ok( 'pracownik edytuje produkty', ! empty( $worker['edit_modohome_products'] ) );
ok( 'pracownik przesyła pliki', ! empty( $worker['upload_files'] ) );
ok( 'pracownik przypisuje kategorie', ! empty( $worker[ Roles::CAP_ASSIGN_TERMS ] ) );
ok( 'pracownik NIE edytuje cudzych produktów', ! isset( $worker['edit_others_modohome_products'] ) );
ok( 'pracownik NIE usuwa cudzych produktów', ! isset( $worker['delete_others_modohome_products'] ) );
ok( 'pracownik NIE edytuje stron', ! isset( $worker['edit_pages'] ) );
ok( 'pracownik NIE instaluje wtyczek', ! isset( $worker['install_plugins'] ) );
ok( 'pracownik NIE zmienia ustawień WP', ! isset( $worker['manage_options'] ) );
ok( 'pracownik NIE zarządza użytkownikami', ! isset( $worker['list_users'] ) );
ok( 'pracownik NIE tworzy kategorii domyślnie', ! isset( $worker[ Roles::CAP_MANAGE_TERMS ] ) );
ok( 'pracownik NIE ma uprawnienia do ustawień wtyczki', ! isset( $worker[ Roles::CAP_MANAGE ] ) );

$admin = Roles::admin_caps();
ok( 'administrator ma uprawnienie do ustawień', in_array( Roles::CAP_MANAGE, $admin, true ) );
ok( 'administrator edytuje cudze produkty', in_array( 'edit_others_modohome_products', $admin, true ) );
ok( 'administrator zarządza kategoriami', in_array( Roles::CAP_MANAGE_TERMS, $admin, true ) );

$pt_caps = Roles::post_type_caps();
ok( 'typ wpisu ma własne capability, nie standardowe', 'edit_posts' !== $pt_caps['edit_posts'] );
ok( 'create_posts zmapowane', isset( $pt_caps['create_posts'] ) );

echo "== 11. Stałe wymagane przez specyfikację ==\n";

check( 'slug typu wpisu', Post_Type::SLUG, 'modohome_product' );
check( 'slug taksonomii', Taxonomy::SLUG, 'modohome_product_category' );
check( 'slug roli', Roles::ROLE, 'modohome_catalog_worker' );

echo "== 12. Sanityzacja galerii ==\n";

$GLOBALS['stub_post_types'] = array( 5 => 'attachment', 6 => 'attachment', 7 => 'post' );

check( 'poprawne załączniki', Product::sanitize_gallery( '5,6' ), '5,6' );
check( 'nie-załącznik odrzucony', Product::sanitize_gallery( '5,7' ), '5' );
check( 'duplikaty usunięte', Product::sanitize_gallery( '5,5,6' ), '5,6' );
check( 'śmieci odrzucone', Product::sanitize_gallery( 'abc' ), '' );

echo "== 13. Numerowanie stron ==\n";

use MODOhome\Catalog\Frontend\Catalog as CatalogView;

check( 'jedna strona — brak paginacji', CatalogView::pagination_items( 1, 1 ), array() );
check( 'zero stron — brak paginacji', CatalogView::pagination_items( 1, 0 ), array() );
check( 'układ z makiety: 8 stron, strona 1', CatalogView::pagination_items( 1, 8 ), array( 1, 2, 3, '…', 8 ) );
check( 'środek zakresu', CatalogView::pagination_items( 5, 8 ), array( 1, '…', 4, 5, 6, '…', 8 ) );
check( 'koniec zakresu', CatalogView::pagination_items( 8, 8 ), array( 1, '…', 6, 7, 8 ) );
check( 'dwie strony', CatalogView::pagination_items( 1, 2 ), array( 1, 2 ) );
check( 'trzy strony', CatalogView::pagination_items( 2, 3 ), array( 1, 2, 3 ) );
check( 'strona poza zakresem przycięta, bez zbędnego wielokropka', CatalogView::pagination_items( 99, 4 ), array( 1, 2, 3, 4 ) );

$markup = CatalogView::render_pagination( 1, 8 );
ok( 'aktywna strona oznaczona', str_contains( $markup, 'is-current' ) );
ok( 'aktywna strona ma aria-current', str_contains( $markup, 'aria-current="page"' ) );
ok( 'wielokropek nie jest przyciskiem', str_contains( $markup, 'modohome-catalog-page--gap' ) );
ok( 'ostatnia strona obecna', str_contains( $markup, 'data-modohome-page="8"' ) );
check( 'brak paginacji dla jednej strony', CatalogView::render_pagination( 1, 1 ), '' );

echo "== 14. Etykiety produktów z makiety ==\n";

$badges = Product::badges();
ok( 'etykieta Ekspozycja istnieje', isset( $badges['exposition'] ) );
check( 'nazwa etykiety Ekspozycja', Product::badge_label( 'exposition' ), 'Ekspozycja' );
check( 'sanityzacja etykiety Ekspozycja', Product::sanitize_badge( 'exposition' ), 'exposition' );
ok( 'sześć etykiet po dodaniu Ekspozycji', 6 === count( $badges ) );

echo "== 15. Domyślne ustawienia zgodne z makietą ==\n";

$d = Settings::defaults();
check( 'proporcje zdjęć pionowe 3:4', $d['image_ratio'], '3:4' );
check( 'numerowane strony zamiast „Pokaż więcej”', $d['enable_load_more'], false );
check( 'ciepłe tło katalogu', $d['bg_color'], '#f4f2ee' );
check( 'karty bez „Zobacz szczegóły”', $d['show_details_button'], false );
check( 'minimalne zaokrąglenie', $d['border_radius'], 2 );
check( 'kolor akcentu marki', $d['primary_color'], '#ef1616' );

echo "\n----------------------------------------\n";
printf( "Zaliczone: %d   Niepowodzenia: %d\n", $passed, $failed );

exit( $failed > 0 ? 1 : 0 );
