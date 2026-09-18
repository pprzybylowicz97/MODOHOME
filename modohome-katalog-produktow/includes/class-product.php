<?php
/**
 * Warstwa danych produktu: pola własne, walidacja i formatowanie.
 *
 * @package MODOhome\Catalog
 */

declare( strict_types = 1 );

namespace MODOhome\Catalog;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wszystkie odczyty i zapisy pól produktu przechodzą przez tę klasę.
 */
class Product {

	public const META_PRICE        = '_modohome_catalog_price';
	public const META_OLD_PRICE    = '_modohome_catalog_old_price';
	public const META_DESCRIPTION  = '_modohome_catalog_description';
	public const META_BADGE        = '_modohome_catalog_badge';
	public const META_AVAILABILITY = '_modohome_catalog_availability';
	public const META_GALLERY      = '_modohome_catalog_gallery';
	public const META_AUTO_HIDDEN  = '_modohome_catalog_auto_hidden';

	public const STATUS_AVAILABLE = 'available';
	public const STATUS_RESERVED  = 'reserved';
	public const STATUS_SOLD      = 'sold';
	public const STATUS_HIDDEN    = 'hidden';

	/**
	 * Rejestruje pola własne.
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_meta' ), 6 );
	}

	/**
	 * Rejestracja meta z sanityzacją i kontrolą uprawnień.
	 */
	public function register_meta(): void {
		$auth = static function ( bool $allowed, string $meta_key, int $post_id ): bool {
			return current_user_can( 'edit_modohome_product', $post_id );
		};

		$fields = array(
			self::META_PRICE        => array( 'number', array( self::class, 'sanitize_price' ) ),
			self::META_OLD_PRICE    => array( 'number', array( self::class, 'sanitize_price' ) ),
			self::META_DESCRIPTION  => array( 'string', 'sanitize_textarea_field' ),
			self::META_BADGE        => array( 'string', array( self::class, 'sanitize_badge' ) ),
			self::META_AVAILABILITY => array( 'string', array( self::class, 'sanitize_availability' ) ),
			self::META_GALLERY      => array( 'string', array( self::class, 'sanitize_gallery' ) ),
			self::META_AUTO_HIDDEN  => array( 'integer', 'absint' ),
		);

		foreach ( $fields as $key => $config ) {
			register_post_meta(
				Post_Type::SLUG,
				$key,
				array(
					'type'              => $config[0],
					'single'            => true,
					'show_in_rest'      => false,
					'sanitize_callback' => $config[1],
					'auth_callback'     => $auth,
				)
			);
		}
	}

	/**
	 * Dostępne etykiety produktu.
	 *
	 * @return array<string,string>
	 */
	public static function badges(): array {
		return array(
			'new'        => __( 'Nowość', 'modohome-katalog-produktow' ),
			'promo'      => __( 'Promocja', 'modohome-katalog-produktow' ),
			'last'       => __( 'Ostatnia sztuka', 'modohome-katalog-produktow' ),
			'bestseller' => __( 'Bestseller', 'modohome-katalog-produktow' ),
			'instock'    => __( 'Dostępny od ręki', 'modohome-katalog-produktow' ),
			'exposition' => __( 'Ekspozycja', 'modohome-katalog-produktow' ),
		);
	}

	/**
	 * Dostępne statusy dostępności.
	 *
	 * @return array<string,string>
	 */
	public static function statuses(): array {
		return array(
			self::STATUS_AVAILABLE => __( 'Dostępny', 'modohome-katalog-produktow' ),
			self::STATUS_RESERVED  => __( 'Zarezerwowany', 'modohome-katalog-produktow' ),
			self::STATUS_SOLD      => __( 'Sprzedany', 'modohome-katalog-produktow' ),
			self::STATUS_HIDDEN    => __( 'Ukryty', 'modohome-katalog-produktow' ),
		);
	}

	/**
	 * Etykieta etykiety produktu.
	 *
	 * @param string $badge Klucz etykiety.
	 */
	public static function badge_label( string $badge ): string {
		return self::badges()[ $badge ] ?? '';
	}

	/**
	 * Etykieta statusu dostępności.
	 *
	 * @param string $status Klucz statusu.
	 */
	public static function status_label( string $status ): string {
		return self::statuses()[ $status ] ?? '';
	}

	/**
	 * Sanityzacja ceny. Akceptuje przecinek jako separator dziesiętny i spacje tysięcy.
	 *
	 * @param mixed $value Wartość wejściowa.
	 */
	public static function sanitize_price( mixed $value ): string {
		if ( is_array( $value ) ) {
			return '';
		}

		$raw = trim( (string) $value );

		if ( '' === $raw ) {
			return '';
		}

		$raw = str_replace( array( ' ', "\u{00A0}", "\u{202F}" ), '', $raw );
		$raw = str_replace( ',', '.', $raw );
		$raw = preg_replace( '/[^0-9.\-]/', '', $raw ) ?? '';

		if ( '' === $raw || ! is_numeric( $raw ) ) {
			return '';
		}

		$number = round( max( 0.0, (float) $raw ), 2 );

		return number_format( $number, 2, '.', '' );
	}

	/**
	 * Sanityzacja etykiety.
	 *
	 * @param mixed $value Wartość wejściowa.
	 */
	public static function sanitize_badge( mixed $value ): string {
		$value = is_string( $value ) ? $value : '';

		return array_key_exists( $value, self::badges() ) ? $value : '';
	}

	/**
	 * Sanityzacja statusu dostępności.
	 *
	 * @param mixed $value Wartość wejściowa.
	 */
	public static function sanitize_availability( mixed $value ): string {
		$value = is_string( $value ) ? $value : '';

		return array_key_exists( $value, self::statuses() ) ? $value : self::STATUS_AVAILABLE;
	}

	/**
	 * Sanityzacja listy identyfikatorów galerii.
	 *
	 * @param mixed $value Lista identyfikatorów (tablica lub ciąg po przecinku).
	 */
	public static function sanitize_gallery( mixed $value ): string {
		if ( is_string( $value ) ) {
			$value = explode( ',', $value );
		}

		if ( ! is_array( $value ) ) {
			return '';
		}

		$ids = array();

		foreach ( $value as $id ) {
			$id = absint( $id );

			if ( $id > 0 && 'attachment' === get_post_type( $id ) ) {
				$ids[] = $id;
			}
		}

		return implode( ',', array_values( array_unique( $ids ) ) );
	}

	/**
	 * Cena produktu jako liczba.
	 *
	 * @param int $post_id Identyfikator produktu.
	 */
	public static function get_price( int $post_id ): ?float {
		$raw = get_post_meta( $post_id, self::META_PRICE, true );

		return ( '' === $raw || null === $raw ) ? null : (float) $raw;
	}

	/**
	 * Poprzednia cena produktu jako liczba.
	 *
	 * @param int $post_id Identyfikator produktu.
	 */
	public static function get_old_price( int $post_id ): ?float {
		$raw = get_post_meta( $post_id, self::META_OLD_PRICE, true );

		return ( '' === $raw || null === $raw ) ? null : (float) $raw;
	}

	/**
	 * Status dostępności produktu.
	 *
	 * @param int $post_id Identyfikator produktu.
	 */
	public static function get_availability( int $post_id ): string {
		$status = (string) get_post_meta( $post_id, self::META_AVAILABILITY, true );

		return array_key_exists( $status, self::statuses() ) ? $status : self::STATUS_AVAILABLE;
	}

	/**
	 * Etykieta produktu.
	 *
	 * @param int $post_id Identyfikator produktu.
	 */
	public static function get_badge( int $post_id ): string {
		return (string) get_post_meta( $post_id, self::META_BADGE, true );
	}

	/**
	 * Krótki opis produktu.
	 *
	 * @param int $post_id Identyfikator produktu.
	 */
	public static function get_description( int $post_id ): string {
		return (string) get_post_meta( $post_id, self::META_DESCRIPTION, true );
	}

	/**
	 * Identyfikatory zdjęć galerii.
	 *
	 * @param int $post_id Identyfikator produktu.
	 *
	 * @return array<int>
	 */
	public static function get_gallery_ids( int $post_id ): array {
		$raw = (string) get_post_meta( $post_id, self::META_GALLERY, true );

		if ( '' === $raw ) {
			return array();
		}

		return array_values(
			array_filter(
				array_map( 'absint', explode( ',', $raw ) ),
				static fn( int $id ): bool => $id > 0
			)
		);
	}

	/**
	 * Formatuje cenę z walutą z ustawień.
	 *
	 * @param float|null $price Cena.
	 */
	public static function format_price( ?float $price ): string {
		if ( null === $price ) {
			return '';
		}

		$currency = (string) Settings::get( 'currency', 'zł' );

		// Pełne złotówki bez końcówki „,00” — czytelniej na kartach produktów.
		$decimals  = ( abs( $price - round( $price ) ) < 0.005 ) ? 0 : 2;
		$formatted = number_format( $price, $decimals, ',', ' ' );

		if ( 'before' === Settings::get( 'currency_position', 'after' ) ) {
			return $currency . ' ' . $formatted;
		}

		return $formatted . ' ' . $currency;
	}

	/**
	 * Czy produkt został dodany dzisiaj (według czasu lokalnego strony).
	 *
	 * @param \WP_Post $post Produkt.
	 */
	public static function is_added_today( \WP_Post $post ): bool {
		return get_the_date( 'Y-m-d', $post ) === current_time( 'Y-m-d' );
	}

	/**
	 * Zwraca pierwszą (najgłębszą) kategorię produktu.
	 *
	 * @param int $post_id Identyfikator produktu.
	 */
	public static function get_primary_term( int $post_id ): ?\WP_Term {
		$terms = get_the_terms( $post_id, Taxonomy::SLUG );

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return null;
		}

		return $terms[0];
	}

	/**
	 * Zbiera dane produktu do postaci używanej przez szablony i odpowiedzi AJAX.
	 *
	 * @param \WP_Post $post Produkt.
	 *
	 * @return array<string,mixed>
	 */
	public static function to_array( \WP_Post $post ): array {
		$id        = $post->ID;
		$price     = self::get_price( $id );
		$old_price = self::get_old_price( $id );
		$thumb_id  = (int) get_post_thumbnail_id( $id );

		$terms = get_the_terms( $id, Taxonomy::SLUG );
		$terms = ( is_wp_error( $terms ) || ! is_array( $terms ) ) ? array() : $terms;

		$gallery = array();

		foreach ( self::get_gallery_ids( $id ) as $attachment_id ) {
			$url = wp_get_attachment_image_url( $attachment_id, 'large' );

			if ( $url ) {
				$gallery[] = array(
					'id'  => $attachment_id,
					'url' => $url,
					'alt' => (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
				);
			}
		}

		$availability = self::get_availability( $id );
		$badge        = self::get_badge( $id );

		return array(
			'id'                 => $id,
			'title'              => get_the_title( $post ),
			'description'        => self::get_description( $id ),
			'price'              => $price,
			'price_formatted'    => self::format_price( $price ),
			'old_price'          => $old_price,
			'old_price_formatted' => self::format_price( $old_price ),
			'thumbnail'          => $thumb_id ? (string) wp_get_attachment_image_url( $thumb_id, 'medium_large' ) : '',
			'image'              => $thumb_id ? (string) wp_get_attachment_image_url( $thumb_id, 'large' ) : '',
			'gallery'            => $gallery,
			'availability'       => $availability,
			'availability_label' => self::status_label( $availability ),
			'badge'              => $badge,
			'badge_label'        => self::badge_label( $badge ),
			'categories'         => array_map(
				static fn( \WP_Term $term ): array => array(
					'id'   => $term->term_id,
					'name' => $term->name,
					'slug' => $term->slug,
				),
				$terms
			),
			'author'             => get_the_author_meta( 'display_name', (int) $post->post_author ),
			'date'               => get_the_date( get_option( 'date_format' ), $post ),
			'date_iso'           => get_the_date( 'c', $post ),
			'is_new_today'       => self::is_added_today( $post ),
			'permalink'          => Settings::bool( 'enable_single_pages' ) ? get_permalink( $post ) : '',
			'edit_link'          => current_user_can( 'edit_modohome_product', $id ) ? (string) get_edit_post_link( $id, 'raw' ) : '',
		);
	}
}
