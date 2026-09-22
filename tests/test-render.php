<?php
/**
 * Renderuje szablony karty i okna modalnego na danych atrapowych,
 * żeby sprawdzić realne wyjście HTML.
 */

require __DIR__ . '/wp-stubs.php';

define( 'MODOHOME_CATALOG_VERSION', '1.0.0' );
define( 'MODOHOME_CATALOG_DIR', dirname( __DIR__ ) . '/modohome-katalog-produktow/' );
define( 'MODOHOME_CATALOG_URL', 'https://example.test/plugins/modohome-katalog-produktow/' );
define( 'MODOHOME_CATALOG_BASENAME', 'modohome-katalog-produktow/modohome-katalog-produktow.php' );

spl_autoload_register(
	static function ( string $class_name ): void {
		if ( ! str_starts_with( $class_name, 'MODOhome\\Catalog\\' ) ) {
			return;
		}
		$relative  = substr( $class_name, strlen( 'MODOhome\\Catalog\\' ) );
		$parts     = explode( '\\', $relative );
		$short     = array_pop( $parts );
		$sub       = $parts[0] ?? '';
		$directory = match ( $sub ) { 'Admin' => 'admin/', 'Frontend' => 'public/', default => 'includes/' };
		$file      = MODOHOME_CATALOG_DIR . $directory . 'class-' . str_replace( '_', '-', strtolower( $short ) ) . '.php';
		if ( is_readable( $file ) ) { require_once $file; }
	}
);

// Dodatkowe atrapy potrzebne szablonom.
function locate_template( $names ) { return ''; }
function wpautop( $t ) { return '<p>' . $t . '</p>'; }
function wp_trim_words( $t, $n ) { return implode( ' ', array_slice( explode( ' ', $t ), 0, $n ) ); }
function get_the_title( $p ) { return is_object( $p ) ? $p->post_title : ''; }
function get_the_date( $f, $p = null ) { return '2026-09-18'; }
function get_the_author_meta( $f, $id ) { return 'Anna'; }
function get_the_terms( $id, $tax ) { return $GLOBALS['stub_object_terms'][ $id ] ?? array(); }
function get_post_thumbnail_id( $id ) { return 5; }
function wp_get_attachment_image_url( $id, $size ) { return 'https://example.test/img/' . $id . '-' . $size . '.jpg'; }
function get_permalink( $p = null ) { return 'https://example.test/produkt/krzeslo/'; }
function get_edit_post_link( $id, $ctx = '' ) { return 'https://example.test/wp-admin/post.php?post=' . $id; }
function selected( $a, $b, $echo = true ) { $r = (string) $a === (string) $b ? " selected='selected'" : ''; if ( $echo ) { echo $r; } return $r; }
function checked( $a, $b = true, $echo = true ) { $r = $a == $b ? " checked='checked'" : ''; if ( $echo ) { echo $r; } return $r; }

function number_format_i18n( $n, $dec = 0 ) { return number_format( (float) $n, $dec, ',', ' ' ); }
function wp_json_encode( $data, $flags = 0 ) { return json_encode( $data, $flags ); }
function wp_reset_postdata() {}
function wp_rand( $min = 0, $max = 0 ) { return random_int( $min, $max ); }
function is_user_logged_in() { return true; }
function wp_create_nonce( $a = '' ) { return 'nonce123'; }
function wp_login_url( $r = '' ) { return 'https://example.test/login'; }
function wp_nonce_field( ...$a ) { echo '<input type="hidden" name="_wpnonce" value="n" />'; }
function wp_dropdown_categories( $args = array() ) {
	echo '<select name="' . esc_attr( $args['name'] ?? 'cat' ) . '" id="' . esc_attr( $args['id'] ?? '' ) . '" class="' . esc_attr( $args['class'] ?? '' ) . '"><option value="">—</option><option value="11">Krzesła</option></select>';
}

use MODOhome\Catalog\Frontend\Catalog;
use MODOhome\Catalog\Product;
use MODOhome\Catalog\Settings;

$GLOBALS['stub_options']['modohome_catalog_settings'] = Settings::defaults();
Settings::flush();

// Produkt testowy z danymi, które muszą zostać zescape'owane.
$post = new WP_Post();
$post->ID          = 42;
$post->post_title  = 'Krzesło "Riviera" <script>alert(1)</script>';
$post->post_author = 3;
$post->post_type   = 'modohome_product';
$post->post_status = 'publish';

$GLOBALS['stub_meta'][42] = array(
	'_modohome_catalog_price'        => '1299.90',
	'_modohome_catalog_old_price'    => '1599.00',
	'_modohome_catalog_description'  => 'Wygodne krzesło <b>tapicerowane</b>.',
	'_modohome_catalog_badge'        => 'promo',
	'_modohome_catalog_availability' => 'reserved',
	'_modohome_catalog_gallery'      => '6',
);
$GLOBALS['stub_post_types'][6] = 'attachment';

$term = new WP_Term();
$term->term_id = 11;
$term->slug    = 'krzesla';
$term->name    = 'Krzesła';
$GLOBALS['stub_object_terms'][42] = array( $term );

$passed = 0;
$failed = 0;

function assert_contains( string $label, string $haystack, string $needle ): void {
	global $passed, $failed;
	if ( str_contains( $haystack, $needle ) ) { ++$passed; return; }
	++$failed;
	echo "  NIEPOWODZENIE: {$label} — brak: {$needle}\n";
}

function assert_missing( string $label, string $haystack, string $needle ): void {
	global $passed, $failed;
	if ( ! str_contains( $haystack, $needle ) ) { ++$passed; return; }
	++$failed;
	echo "  NIEPOWODZENIE: {$label} — znaleziono niepożądane: {$needle}\n";
}

echo "== Karta produktu ==\n";

$card = Catalog::render_card( $post );

file_put_contents( __DIR__ . '/out-card.html', $card );

assert_contains( 'kontener karty', $card, 'class="modohome-catalog-card' );
assert_contains( 'identyfikator produktu', $card, 'data-product-id="42"' );
assert_contains( 'wyzwalacz okna modalnego', $card, 'data-modohome-open="42"' );
assert_contains( 'aria-haspopup dla modala', $card, 'aria-haspopup="dialog"' );
assert_contains( 'cena sformatowana z walutą', $card, '1 299,90 zł' );
assert_contains( 'przekreślona cena poprzednia', $card, 'modohome-catalog-price-old' );
assert_contains( 'cena poprzednia z walutą', $card, '1 599 zł' );
assert_contains( 'kategoria na karcie', $card, 'Krzesła' );
assert_contains( 'etykieta Promocja', $card, 'Promocja' );
assert_contains( 'status Zarezerwowany', $card, 'Zarezerwowany' );
assert_contains( 'klasa stanu zarezerwowania', $card, 'modohome-catalog-card--reserved' );
assert_contains( 'leniwe ładowanie zdjęcia', $card, 'loading="lazy"' );
assert_missing( 'XSS w tytule zneutralizowany', $card, '<script>alert(1)</script>' );
assert_contains( 'tytuł zescape\'owany', $card, '&lt;script&gt;' );

echo "== Okno modalne ==\n";

$modal = Catalog::render_modal_content( $post );

file_put_contents( __DIR__ . '/out-modal.html', $modal );

assert_contains( 'identyfikator nagłówka dla aria-labelledby', $modal, 'id="modohome-catalog-modal-title"' );
assert_contains( 'zdjęcie główne w oknie', $modal, 'data-modohome-gallery-main' );
assert_contains( 'galeria: miniatury', $modal, 'data-modohome-gallery-thumb' );
assert_contains( 'galeria: poprzednie', $modal, 'data-modohome-gallery-prev' );
assert_contains( 'galeria: następne', $modal, 'data-modohome-gallery-next' );
assert_contains( 'opis produktu', $modal, 'tapicerowane' );
assert_contains( 'status w oknie', $modal, 'modohome-catalog-status-dot--reserved' );
assert_missing( 'XSS w oknie zneutralizowany', $modal, '<script>alert(1)</script>' );

echo "== Ukrywanie elementów przez ustawienia ==\n";

$off = Settings::defaults();
$off['show_old_price']    = false;
$off['show_category']     = false;
$off['show_availability'] = false;
$off['show_badges']       = false;
$GLOBALS['stub_options']['modohome_catalog_settings'] = $off;
Settings::flush();

$card_off = Catalog::render_card( $post );

assert_missing( 'cena poprzednia wyłączona', $card_off, 'modohome-catalog-price-old' );
assert_missing( 'kategoria wyłączona', $card_off, 'modohome-catalog-card-category' );
assert_missing( 'etykieta wyłączona', $card_off, 'modohome-catalog-badge--promo' );
assert_missing( 'status wyłączony', $card_off, 'modohome-catalog-status-tag' );
assert_contains( 'cena nadal widoczna', $card_off, '1 299,90 zł' );

echo "== Pełny szablon katalogu ==\n";

$GLOBALS['stub_options']['modohome_catalog_settings'] = Settings::defaults();
Settings::flush();

$query = new WP_Query();
$query->posts         = array( $post );
$query->found_posts   = 94;
$query->max_num_pages = 8;

$catalog = \MODOhome\Catalog\Plugin::render_template(
	'catalog',
	array(
		'instance_id'          => 'modohome-catalog-1',
		'query'                => $query,
		'columns'              => 4,
		'heading'              => 'Katalog MODOhome',
		'intro'                => '',
		'show_filters'         => true,
		'show_category_filter' => true,
		'show_search'          => true,
		'orderby'              => 'date',
		'terms'                => array( $term ),
		'config'               => array( 'perPage' => 12, 'loadMore' => false ),
		'total'                => 94,
		'max_pages'            => 8,
		'limit'                => 0,
		'use_load_more'        => false,
	)
);

file_put_contents( __DIR__ . '/out-catalog.html', $catalog );

assert_contains( 'nagłówek katalogu', $catalog, 'Katalog MODOhome' );
assert_contains( 'pasek filtrów', $catalog, 'modohome-catalog-filterbar' );
assert_contains( 'przycisk „Wszystkie”', $catalog, 'Wszystkie' );
assert_contains( 'filtr kategorii z makiety', $catalog, 'Krzesła' );
assert_contains( 'licznik produktów', $catalog, 'data-modohome-count' );
assert_contains( 'licznik pokazuje liczbę', $catalog, '94 produktów' );
assert_contains( 'wyszukiwarka', $catalog, 'data-modohome-search' );
assert_contains( 'sortowanie', $catalog, 'data-modohome-sort' );
assert_contains( 'siatka produktów', $catalog, 'data-modohome-grid' );
assert_contains( 'numerowana paginacja', $catalog, 'data-modohome-pagination' );
assert_contains( 'strona 1 aktywna', $catalog, 'is-current' );
assert_contains( 'ostatnia strona 8', $catalog, 'data-modohome-page="8"' );
assert_contains( 'wielokropek w paginacji', $catalog, 'modohome-catalog-page--gap' );
assert_missing( 'brak przycisku „Pokaż więcej”', $catalog, 'data-modohome-load-more' );

echo "== Katalog w trybie „Pokaż więcej” ==\n";

$more = Settings::defaults();
$more['enable_load_more'] = true;
$GLOBALS['stub_options']['modohome_catalog_settings'] = $more;
Settings::flush();

$catalog2 = \MODOhome\Catalog\Plugin::render_template(
	'catalog',
	array(
		'instance_id' => 'modohome-catalog-2', 'query' => $query, 'columns' => 4,
		'heading' => '', 'intro' => '', 'show_filters' => false, 'show_category_filter' => false,
		'show_search' => false, 'orderby' => 'date', 'terms' => array(),
		'config' => array( 'perPage' => 12, 'loadMore' => true ),
		'total' => 94, 'max_pages' => 8, 'limit' => 0, 'use_load_more' => true,
	)
);

assert_contains( 'przycisk „Pokaż więcej” obecny', $catalog2, 'data-modohome-load-more' );
assert_missing( 'brak numerowanej paginacji', $catalog2, 'data-modohome-pagination' );

$GLOBALS['stub_options']['modohome_catalog_settings'] = Settings::defaults();
Settings::flush();

echo "== Etykieta Ekspozycja na karcie ==\n";

$GLOBALS['stub_meta'][42]['_modohome_catalog_badge'] = 'exposition';
$card_exp = Catalog::render_card( $post );

assert_contains( 'tekst etykiety', $card_exp, 'Ekspozycja' );
assert_contains( 'klasa koloru etykiety', $card_exp, 'modohome-catalog-badge--exposition' );

echo "\n----------------------------------------\n";
printf( "Zaliczone: %d   Niepowodzenia: %d\n", $passed, $failed );

exit( $failed > 0 ? 1 : 0 );
