<?php
/**
 * Optymalizacja przesyłanych zdjęć: skalowanie i konwersja do WebP.
 *
 * @package MODOhome\Catalog
 */

declare( strict_types = 1 );

namespace MODOhome\Catalog;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Zdjęcie jest przetwarzane jeszcze zanim WordPress zapisze je na dysku.
 *
 * Dzięki temu plik trafiający do biblioteki mediów jest od razu mały, a WordPress
 * nie odkłada obok niego wielkiego oryginału (co robi mechanizm „big image”,
 * gdy przeskalowanie zostawimy jemu).
 */
class Image_Optimizer {

	/**
	 * Formaty, które wolno przetwarzać.
	 *
	 * @var array<string>
	 */
	private const SOURCE_MIMES = array( 'image/jpeg', 'image/png', 'image/webp' );

	/**
	 * Czy filtry są aktualnie podpięte (zabezpieczenie przed podwójną rejestracją).
	 */
	private static bool $hooked = false;

	/**
	 * Tryb globalny włącza się dopiero po pełnym załadowaniu WordPressa.
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'maybe_enable_site_wide' ), 20 );
		add_action( 'admin_notices', array( $this, 'render_support_notice' ) );
	}

	/**
	 * Podpina optymalizację do wszystkich przesyłanych plików, jeśli administrator
	 * włączył tryb globalny.
	 */
	public function maybe_enable_site_wide(): void {
		if ( Settings::bool( 'webp_convert' ) && Settings::bool( 'webp_convert_all' ) ) {
			self::enable();
		}
	}

	/**
	 * Podpina filtry optymalizacji.
	 */
	public static function enable(): void {
		if ( self::$hooked ) {
			return;
		}

		self::$hooked = true;

		add_filter( 'wp_handle_upload_prefilter', array( self::class, 'prefilter' ) );
		add_filter( 'wp_editor_set_quality', array( self::class, 'filter_quality' ), 10, 2 );
	}

	/**
	 * Odpina filtry optymalizacji.
	 */
	public static function disable(): void {
		if ( ! self::$hooked ) {
			return;
		}

		self::$hooked = false;

		remove_filter( 'wp_handle_upload_prefilter', array( self::class, 'prefilter' ) );
		remove_filter( 'wp_editor_set_quality', array( self::class, 'filter_quality' ), 10 );
	}

	/**
	 * Czy serwer potrafi zapisywać pliki WebP.
	 */
	public static function is_supported(): bool {
		return wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) );
	}

	/**
	 * Jakość zapisu z ustawień, ograniczona do sensownego zakresu.
	 */
	public static function quality(): int {
		return max( 40, min( 100, Settings::int( 'webp_quality' ) ) );
	}

	/**
	 * Jakość dla generowanych miniatur WebP.
	 *
	 * @param mixed $quality Jakość proponowana przez WordPressa.
	 * @param mixed $mime    Typ MIME zapisywanego pliku.
	 */
	public static function filter_quality( mixed $quality, mixed $mime = '' ): int {
		if ( 'image/webp' === $mime ) {
			return self::quality();
		}

		return is_numeric( $quality ) ? (int) $quality : 82;
	}

	/**
	 * Skaluje i konwertuje plik tymczasowy, zanim WordPress przeniesie go do uploadów.
	 *
	 * @param array<string,mixed> $file Wpis z tablicy $_FILES przetwarzany przez WordPressa.
	 *
	 * @return array<string,mixed>
	 */
	public static function prefilter( array $file ): array {
		if ( ! empty( $file['error'] ) || empty( $file['tmp_name'] ) ) {
			return $file;
		}

		$tmp_name = (string) $file['tmp_name'];

		if ( ! is_readable( $tmp_name ) ) {
			return $file;
		}

		// Typ ustalamy z zawartości pliku, nie z nazwy.
		$info = @getimagesize( $tmp_name ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		if ( false === $info || empty( $info['mime'] ) ) {
			return $file;
		}

		$mime = (string) $info['mime'];

		if ( ! in_array( $mime, self::SOURCE_MIMES, true ) ) {
			return $file;
		}

		$max           = max( 400, Settings::int( 'max_image_dimension' ) );
		$needs_resize  = ( (int) $info[0] > $max || (int) $info[1] > $max );
		$convert       = Settings::bool( 'webp_convert' ) && self::is_supported();
		$needs_convert = $convert && 'image/webp' !== $mime;

		if ( ! $needs_resize && ! $needs_convert ) {
			return $file;
		}

		$editor = wp_get_image_editor( $tmp_name );

		if ( is_wp_error( $editor ) ) {
			return $file;
		}

		$target_mime = $needs_convert ? 'image/webp' : $mime;

		$editor->set_quality( 'image/webp' === $target_mime ? self::quality() : 82 );

		if ( $needs_resize ) {
			// false = bez kadrowania, proporcje zostają zachowane.
			$resized = $editor->resize( $max, $max, false );

			if ( is_wp_error( $resized ) ) {
				return $file;
			}
		}

		$extension = 'image/webp' === $target_mime ? 'webp' : ( 'image/png' === $target_mime ? 'png' : 'jpg' );
		$target    = trailingslashit( get_temp_dir() ) . uniqid( 'modohome-opt-', true ) . '.' . $extension;

		$saved = $editor->save( $target, $target_mime );

		if ( is_wp_error( $saved ) || empty( $saved['path'] ) || ! file_exists( $saved['path'] ) ) {
			return $file;
		}

		$new_size      = (int) filesize( $saved['path'] );
		$original_size = isset( $file['size'] ) ? (int) $file['size'] : (int) filesize( $tmp_name );

		// Gdy zdjęcia nie trzeba było skalować, a konwersja nic nie dała — zostawiamy oryginał.
		if ( ! $needs_resize && $new_size > 0 && $original_size > 0 && $new_size >= $original_size ) {
			self::remove_file( (string) $saved['path'] );

			return $file;
		}

		// Nadpisujemy plik tymczasowy w miejscu: move_uploaded_file() sprawdza ścieżkę
		// zarejestrowaną przez PHP, a nie zawartość, więc podmiana treści jest bezpieczna.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_copy
		if ( ! @copy( (string) $saved['path'], $tmp_name ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			self::remove_file( (string) $saved['path'] );

			return $file;
		}

		self::remove_file( (string) $saved['path'] );

		$file['type'] = $target_mime;
		$file['size'] = (int) filesize( $tmp_name );

		if ( $needs_convert ) {
			$name         = (string) ( $file['name'] ?? 'zdjecie' );
			$file['name'] = preg_replace( '/\.[A-Za-z0-9]+$/', '', $name ) . '.webp';
		}

		return $file;
	}

	/**
	 * Komunikat, gdy serwer nie obsługuje WebP, a opcja jest włączona.
	 */
	public function render_support_notice(): void {
		if ( ! current_user_can( Roles::CAP_MANAGE ) ) {
			return;
		}

		$screen = get_current_screen();

		if ( ! $screen instanceof \WP_Screen || ! str_contains( (string) $screen->id, 'modohome-catalog-settings' ) ) {
			return;
		}

		if ( ! Settings::bool( 'webp_convert' ) || self::is_supported() ) {
			return;
		}

		printf(
			'<div class="notice notice-warning"><p>%s</p></div>',
			esc_html__( 'Serwer nie obsługuje zapisu plików WebP (brak biblioteki GD z WebP lub Imagick). Konwersja jest pomijana — zdjęcia są tylko skalowane. Skontaktuj się z hostingiem, aby włączyć obsługę WebP.', 'modohome-katalog-produktow' )
		);
	}

	/**
	 * Usuwa plik tymczasowy.
	 *
	 * @param string $path Ścieżka pliku.
	 */
	private static function remove_file( string $path ): void {
		if ( '' !== $path && file_exists( $path ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_unlink, WordPress.PHP.NoSilencedErrors.Discouraged
			@unlink( $path );
		}
	}
}
