<?php
/**
 * Test optymalizacji zdjęć na prawdziwych plikach, z minimalnym edytorem GD
 * w miejscu WP_Image_Editor.
 */

require __DIR__ . '/wp-stubs.php';

define( 'MODOHOME_CATALOG_VERSION', '1.0.0' );
define( 'MODOHOME_CATALOG_DIR', dirname( __DIR__ ) . '/modohome-katalog-produktow/' );
define( 'MODOHOME_CATALOG_URL', 'https://example.test/p/' );
define( 'MODOHOME_CATALOG_BASENAME', 'p/p.php' );

spl_autoload_register(
	static function ( string $class_name ): void {
		if ( ! str_starts_with( $class_name, 'MODOhome\\Catalog\\' ) ) { return; }
		$relative  = substr( $class_name, strlen( 'MODOhome\\Catalog\\' ) );
		$parts     = explode( '\\', $relative );
		$short     = array_pop( $parts );
		$sub       = $parts[0] ?? '';
		$directory = match ( $sub ) { 'Admin' => 'admin/', 'Frontend' => 'public/', default => 'includes/' };
		$file      = MODOHOME_CATALOG_DIR . $directory . 'class-' . str_replace( '_', '-', strtolower( $short ) ) . '.php';
		if ( is_readable( $file ) ) { require_once $file; }
	}
);

function trailingslashit( $s ) { return rtrim( (string) $s, '/\\' ) . '/'; }
function get_temp_dir() { return sys_get_temp_dir() . '/'; }
function wp_image_editor_supports( $args = array() ) { return function_exists( 'imagewebp' ); }
function get_current_screen() { return null; }

/** Minimalny edytor obrazu oparty na GD — odpowiednik WP_Image_Editor na potrzeby testu. */
class Test_Image_Editor {
	private $image;
	private int $quality = 82;

	public function __construct( string $path ) {
		$info = getimagesize( $path );
		$this->image = match ( $info['mime'] ) {
			'image/jpeg' => imagecreatefromjpeg( $path ),
			'image/png'  => imagecreatefrompng( $path ),
			'image/webp' => imagecreatefromwebp( $path ),
			default      => null,
		};
	}

	public function set_quality( int $q ) { $this->quality = $q; return true; }

	public function get_size(): array {
		return array( 'width' => imagesx( $this->image ), 'height' => imagesy( $this->image ) );
	}

	public function resize( $max_w, $max_h, $crop = false ) {
		$w = imagesx( $this->image );
		$h = imagesy( $this->image );
		$ratio = min( $max_w / $w, $max_h / $h );

		if ( $ratio >= 1 ) { return true; }

		$nw = (int) round( $w * $ratio );
		$nh = (int) round( $h * $ratio );
		$dst = imagecreatetruecolor( $nw, $nh );
		imagealphablending( $dst, false );
		imagesavealpha( $dst, true );
		imagecopyresampled( $dst, $this->image, 0, 0, 0, 0, $nw, $nh, $w, $h );
		$this->image = $dst;

		return true;
	}

	public function save( string $filename, string $mime ) {
		$result = match ( $mime ) {
			'image/webp' => imagewebp( $this->image, $filename, $this->quality ),
			'image/png'  => imagepng( $this->image, $filename ),
			default      => imagejpeg( $this->image, $filename, $this->quality ),
		};

		if ( ! $result ) { return new WP_Error( 'save_failed', 'Nie udało się zapisać' ); }

		return array(
			'path'      => $filename,
			'file'      => basename( $filename ),
			'width'     => imagesx( $this->image ),
			'height'    => imagesy( $this->image ),
			'mime-type' => $mime,
		);
	}
}

function wp_get_image_editor( $path, $args = array() ) {
	if ( ! file_exists( $path ) ) { return new WP_Error( 'missing', 'Brak pliku' ); }
	$info = @getimagesize( $path );
	if ( false === $info ) { return new WP_Error( 'not_image', 'To nie obraz' ); }
	return new Test_Image_Editor( $path );
}

use MODOhome\Catalog\Image_Optimizer;
use MODOhome\Catalog\Settings;

$passed = 0;
$failed = 0;

function check( string $label, $actual, $expected ): void {
	global $passed, $failed;
	if ( $actual === $expected ) { ++$passed; return; }
	++$failed;
	printf( "  NIEPOWODZENIE: %s\n    oczekiwano: %s\n    otrzymano:  %s\n", $label, var_export( $expected, true ), var_export( $actual, true ) );
}

function ok( string $label, bool $cond ): void {
	global $passed, $failed;
	if ( $cond ) { ++$passed; return; }
	++$failed;
	echo "  NIEPOWODZENIE: {$label}\n";
}

/** Tworzy testowe zdjęcie „z telefonu” o zadanych wymiarach. */
function make_photo( int $w, int $h, string $path, string $format = 'jpeg' ): void {
	$img = imagecreatetruecolor( $w, $h );

	// Szum, żeby plik nie skompresował się do zera i wynik był realistyczny.
	for ( $i = 0; $i < 2500; $i++ ) {
		$color = imagecolorallocate( $img, random_int( 0, 255 ), random_int( 0, 255 ), random_int( 0, 255 ) );
		imagefilledellipse( $img, random_int( 0, $w ), random_int( 0, $h ), random_int( 20, 260 ), random_int( 20, 260 ), $color );
	}

	if ( 'png' === $format ) { imagepng( $img, $path ); } else { imagejpeg( $img, $path, 92 ); }
}

$settings = Settings::defaults();
$GLOBALS['stub_options']['modohome_catalog_settings'] = $settings;
Settings::flush();

$dir = sys_get_temp_dir() . '/modohome-test';
@mkdir( $dir );

echo "== 1. Duże zdjęcie z telefonu (4000x3000 JPG) ==\n";

$src = $dir . '/telefon.jpg';
make_photo( 4000, 3000, $src );
$before = filesize( $src );

$tmp = $dir . '/upload-tmp-1';
copy( $src, $tmp );

$file = array( 'name' => 'IMG_4821.JPG', 'type' => 'image/jpeg', 'tmp_name' => $tmp, 'error' => 0, 'size' => $before );
$out  = Image_Optimizer::prefilter( $file );

$after = filesize( $out['tmp_name'] );
$dims  = getimagesize( $out['tmp_name'] );

check( 'typ zmieniony na WebP', $out['type'], 'image/webp' );
check( 'rozszerzenie nazwy zmienione', $out['name'], 'IMG_4821.webp' );
check( 'MIME pliku to WebP', $dims['mime'], 'image/webp' );
check( 'dłuższy bok przeskalowany do 1600', max( $dims[0], $dims[1] ), 1600 );
check( 'proporcje 4:3 zachowane', $dims[0] . 'x' . $dims[1], '1600x1200' );
check( 'zgłoszony rozmiar zgadza się z plikiem', $out['size'], $after );
ok( 'plik wyraźnie mniejszy', $after < $before );

printf( "    %s → %s (%d%% oryginału), %dx%d → %dx%d\n",
	size_format( $before ), size_format( $after ), (int) round( $after / $before * 100 ), 4000, 3000, $dims[0], $dims[1] );

echo "== 2. Zdjęcie pionowe — proporcje ==\n";

$src2 = $dir . '/pionowe.jpg';
make_photo( 2400, 3200, $src2 );
$tmp2 = $dir . '/upload-tmp-2';
copy( $src2, $tmp2 );

$out2  = Image_Optimizer::prefilter( array( 'name' => 'pion.jpg', 'type' => 'image/jpeg', 'tmp_name' => $tmp2, 'error' => 0, 'size' => filesize( $src2 ) ) );
$dims2 = getimagesize( $out2['tmp_name'] );

check( 'pionowe: dłuższy bok 1600', max( $dims2[0], $dims2[1] ), 1600 );
check( 'pionowe: proporcje 3:4 zachowane', $dims2[0] . 'x' . $dims2[1], '1200x1600' );

echo "== 3. Małe zdjęcie — bez skalowania, sama konwersja ==\n";

$src3 = $dir . '/male.jpg';
make_photo( 800, 600, $src3 );
$tmp3 = $dir . '/upload-tmp-3';
copy( $src3, $tmp3 );

$out3  = Image_Optimizer::prefilter( array( 'name' => 'male.jpg', 'type' => 'image/jpeg', 'tmp_name' => $tmp3, 'error' => 0, 'size' => filesize( $src3 ) ) );
$dims3 = getimagesize( $out3['tmp_name'] );

check( 'małe: wymiary nietknięte', $dims3[0] . 'x' . $dims3[1], '800x600' );
ok( 'małe: format WebP albo oryginał, gdy WebP nie byłby mniejszy', in_array( $dims3['mime'], array( 'image/webp', 'image/jpeg' ), true ) );

echo "== 4. PNG z przezroczystością ==\n";

$png = imagecreatetruecolor( 2000, 2000 );
imagesavealpha( $png, true );
imagefill( $png, 0, 0, imagecolorallocatealpha( $png, 0, 0, 0, 127 ) );
imagefilledellipse( $png, 1000, 1000, 1200, 1200, imagecolorallocate( $png, 239, 22, 22 ) );
$src4 = $dir . '/przezroczysty.png';
imagepng( $png, $src4 );
$tmp4 = $dir . '/upload-tmp-4';
copy( $src4, $tmp4 );

$out4  = Image_Optimizer::prefilter( array( 'name' => 'logo.png', 'type' => 'image/png', 'tmp_name' => $tmp4, 'error' => 0, 'size' => filesize( $src4 ) ) );
$dims4 = getimagesize( $out4['tmp_name'] );

check( 'PNG przeskalowany do 1600', max( $dims4[0], $dims4[1] ), 1600 );
check( 'PNG skonwertowany do WebP', $out4['type'], 'image/webp' );

echo "== 5. Konwersja wyłączona w ustawieniach ==\n";

$no_webp = Settings::defaults();
$no_webp['webp_convert'] = false;
$GLOBALS['stub_options']['modohome_catalog_settings'] = $no_webp;
Settings::flush();

$src5 = $dir . '/bez-konwersji.jpg';
make_photo( 3000, 2000, $src5 );
$tmp5 = $dir . '/upload-tmp-5';
copy( $src5, $tmp5 );

$out5  = Image_Optimizer::prefilter( array( 'name' => 'x.jpg', 'type' => 'image/jpeg', 'tmp_name' => $tmp5, 'error' => 0, 'size' => filesize( $src5 ) ) );
$dims5 = getimagesize( $out5['tmp_name'] );

check( 'bez konwersji: pozostaje JPG', $dims5['mime'], 'image/jpeg' );
check( 'bez konwersji: nazwa bez zmian', $out5['name'], 'x.jpg' );
check( 'bez konwersji: nadal przeskalowane', max( $dims5[0], $dims5[1] ), 1600 );

$GLOBALS['stub_options']['modohome_catalog_settings'] = Settings::defaults();
Settings::flush();

echo "== 6. Własny wymiar maksymalny ==\n";

$custom = Settings::defaults();
$custom['max_image_dimension'] = 1000;
$GLOBALS['stub_options']['modohome_catalog_settings'] = $custom;
Settings::flush();

$tmp6 = $dir . '/upload-tmp-6';
copy( $src, $tmp6 );
$out6  = Image_Optimizer::prefilter( array( 'name' => 'a.jpg', 'type' => 'image/jpeg', 'tmp_name' => $tmp6, 'error' => 0, 'size' => filesize( $src ) ) );
$dims6 = getimagesize( $out6['tmp_name'] );

check( 'wymiar 1000 px respektowany', max( $dims6[0], $dims6[1] ), 1000 );

$GLOBALS['stub_options']['modohome_catalog_settings'] = Settings::defaults();
Settings::flush();

echo "== 7. Odporność na błędne wejście ==\n";

$txt = $dir . '/plik.txt';
file_put_contents( $txt, 'to nie jest obraz' );
$out7 = Image_Optimizer::prefilter( array( 'name' => 'plik.txt', 'type' => 'text/plain', 'tmp_name' => $txt, 'error' => 0, 'size' => filesize( $txt ) ) );
check( 'nie-obraz przechodzi bez zmian', $out7['type'], 'text/plain' );

$out8 = Image_Optimizer::prefilter( array( 'name' => 'x.jpg', 'type' => 'image/jpeg', 'tmp_name' => '', 'error' => 4, 'size' => 0 ) );
check( 'błąd przesyłania przechodzi bez zmian', $out8['error'], 4 );

$out9 = Image_Optimizer::prefilter( array( 'name' => 'x.jpg', 'type' => 'image/jpeg', 'tmp_name' => $dir . '/nie-ma-takiego', 'error' => 0, 'size' => 10 ) );
check( 'brakujący plik przechodzi bez zmian', $out9['size'], 10 );

echo "== 8. Jakość WebP ==\n";

check( 'jakość domyślna', Image_Optimizer::quality(), 82 );

$q = Settings::defaults();
$q['webp_quality'] = 200;
$GLOBALS['stub_options']['modohome_catalog_settings'] = $q;
Settings::flush();
check( 'jakość ograniczona do 100', Image_Optimizer::quality(), 100 );

$q['webp_quality'] = 5;
$GLOBALS['stub_options']['modohome_catalog_settings'] = $q;
Settings::flush();
check( 'jakość ograniczona do 40', Image_Optimizer::quality(), 40 );

check( 'filtr jakości dotyczy tylko WebP', Image_Optimizer::filter_quality( 90, 'image/jpeg' ), 90 );
check( 'filtr jakości ustawia WebP', Image_Optimizer::filter_quality( 90, 'image/webp' ), 40 );

echo "\n----------------------------------------\n";
printf( "Zaliczone: %d   Niepowodzenia: %d\n", $passed, $failed );

array_map( 'unlink', glob( $dir . '/*' ) );
@rmdir( $dir );

exit( $failed > 0 ? 1 : 0 );
