<?php
/**
 * Obsługa przesyłanych zdjęć produktów.
 *
 * @package MODOhome\Catalog
 */

declare( strict_types = 1 );

namespace MODOhome\Catalog;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wszystkie pliki trafiają do biblioteki mediów WordPressa — wtyczka nie zapisuje
 * niczego we własnym katalogu.
 */
class Image_Handler {

	/**
	 * Dozwolone typy plików.
	 *
	 * @return array<string,string>
	 */
	public static function allowed_mimes(): array {
		return array(
			'jpg|jpeg|jpe' => 'image/jpeg',
			'png'          => 'image/png',
			'webp'         => 'image/webp',
		);
	}

	/**
	 * Maksymalny rozmiar pliku w bajtach (ustawienie wtyczki ograniczone limitem serwera).
	 */
	public static function max_upload_bytes(): int {
		$plugin_limit = Settings::int( 'max_upload_size' ) * MB_IN_BYTES;
		$server_limit = (int) wp_max_upload_size();

		if ( $server_limit > 0 ) {
			return min( $plugin_limit, $server_limit );
		}

		return $plugin_limit;
	}

	/**
	 * Przyjmuje plik z $_FILES i tworzy załącznik przypisany do produktu.
	 *
	 * @param string $field_name Nazwa pola formularza.
	 * @param int    $post_id    Produkt, do którego przypisujemy załącznik.
	 *
	 * @return int|\WP_Error Identyfikator załącznika lub błąd.
	 */
	public static function handle_upload( string $field_name, int $post_id = 0 ): int|\WP_Error {
		if ( ! current_user_can( 'upload_files' ) ) {
			return new \WP_Error( 'modohome_catalog_forbidden', __( 'Brak uprawnień do przesyłania plików.', 'modohome-katalog-produktow' ) );
		}

		if ( empty( $_FILES[ $field_name ] ) || ! is_array( $_FILES[ $field_name ] ) ) {
			return new \WP_Error( 'modohome_catalog_no_file', __( 'Nie wybrano zdjęcia.', 'modohome-katalog-produktow' ) );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- dane pliku weryfikujemy poniżej.
		$file = $_FILES[ $field_name ];

		$error_code = isset( $file['error'] ) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;

		if ( UPLOAD_ERR_NO_FILE === $error_code ) {
			return new \WP_Error( 'modohome_catalog_no_file', __( 'Nie wybrano zdjęcia.', 'modohome-katalog-produktow' ) );
		}

		if ( UPLOAD_ERR_OK !== $error_code ) {
			return new \WP_Error( 'modohome_catalog_upload_error', self::upload_error_message( $error_code ) );
		}

		$size = isset( $file['size'] ) ? (int) $file['size'] : 0;
		$max  = self::max_upload_bytes();

		if ( $size <= 0 ) {
			return new \WP_Error( 'modohome_catalog_empty_file', __( 'Przesłany plik jest pusty.', 'modohome-katalog-produktow' ) );
		}

		if ( $size > $max ) {
			return new \WP_Error(
				'modohome_catalog_too_large',
				sprintf(
					/* translators: %s: maksymalny rozmiar pliku, np. „10 MB”. */
					__( 'Zdjęcie jest za duże. Maksymalny rozmiar to %s.', 'modohome-katalog-produktow' ),
					size_format( $max )
				)
			);
		}

		$tmp_name = isset( $file['tmp_name'] ) ? (string) $file['tmp_name'] : '';

		if ( '' === $tmp_name || ! is_uploaded_file( $tmp_name ) ) {
			return new \WP_Error( 'modohome_catalog_invalid_upload', __( 'Nieprawidłowy plik.', 'modohome-katalog-produktow' ) );
		}

		// Typ pliku sprawdzamy po zawartości, nie po rozszerzeniu z nazwy.
		$image_info = @getimagesize( $tmp_name ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		if ( false === $image_info || empty( $image_info['mime'] ) ) {
			return new \WP_Error( 'modohome_catalog_not_image', __( 'Plik nie jest prawidłowym zdjęciem.', 'modohome-katalog-produktow' ) );
		}

		if ( ! in_array( $image_info['mime'], array_values( self::allowed_mimes() ), true ) ) {
			return new \WP_Error(
				'modohome_catalog_bad_type',
				__( 'Dozwolone formaty zdjęć to JPG, PNG i WebP.', 'modohome-katalog-produktow' )
			);
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$threshold_filter = static fn(): int => max( 400, Settings::int( 'max_image_dimension' ) );

		// Duże zdjęcia z telefonu WordPress przeskaluje do ustawionego wymiaru,
		// zachowując proporcje i korygując orientację na podstawie danych EXIF.
		add_filter( 'big_image_size_threshold', $threshold_filter, 99 );

		$overrides = array(
			'test_form' => false,
			'mimes'     => self::allowed_mimes(),
		);

		$attachment_id = media_handle_upload( $field_name, $post_id, array(), $overrides );

		remove_filter( 'big_image_size_threshold', $threshold_filter, 99 );

		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}

		return (int) $attachment_id;
	}

	/**
	 * Czytelny komunikat dla kodów błędów PHP.
	 *
	 * @param int $code Kod błędu z $_FILES.
	 */
	private static function upload_error_message( int $code ): string {
		return match ( $code ) {
			UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => sprintf(
				/* translators: %s: maksymalny rozmiar pliku. */
				__( 'Zdjęcie jest za duże. Maksymalny rozmiar to %s.', 'modohome-katalog-produktow' ),
				size_format( self::max_upload_bytes() )
			),
			UPLOAD_ERR_PARTIAL   => __( 'Przesyłanie zdjęcia zostało przerwane. Spróbuj ponownie.', 'modohome-katalog-produktow' ),
			UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE => __( 'Serwer nie mógł zapisać pliku. Skontaktuj się z administratorem.', 'modohome-katalog-produktow' ),
			UPLOAD_ERR_EXTENSION => __( 'Przesyłanie zablokowane przez konfigurację serwera.', 'modohome-katalog-produktow' ),
			default              => __( 'Nie udało się przesłać zdjęcia. Spróbuj ponownie.', 'modohome-katalog-produktow' ),
		};
	}

	/**
	 * Ustawia zdjęcie główne produktu i uzupełnia tekst alternatywny.
	 *
	 * @param int    $post_id       Produkt.
	 * @param int    $attachment_id Załącznik.
	 * @param string $alt_text      Tekst alternatywny.
	 */
	public static function set_featured_image( int $post_id, int $attachment_id, string $alt_text = '' ): void {
		set_post_thumbnail( $post_id, $attachment_id );

		$existing_alt = (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );

		if ( '' === $existing_alt && '' !== $alt_text ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $alt_text ) );
		}
	}
}
