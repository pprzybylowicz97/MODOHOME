<?php
/**
 * Operacje na produktach współdzielone przez panel i front.
 *
 * @package MODOhome\Catalog
 */

declare( strict_types = 1 );

namespace MODOhome\Catalog;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Każda metoda sama sprawdza uprawnienia — nie zakłada, że zrobił to wywołujący.
 */
class Product_Actions {

	/**
	 * Zmienia status dostępności produktu.
	 *
	 * @param int    $post_id Identyfikator produktu.
	 * @param string $status  Nowy status.
	 *
	 * @return true|\WP_Error
	 */
	public static function set_availability( int $post_id, string $status ): true|\WP_Error {
		$post = get_post( $post_id );

		if ( ! $post instanceof \WP_Post || Post_Type::SLUG !== $post->post_type ) {
			return new \WP_Error( 'modohome_catalog_not_found', __( 'Nie znaleziono produktu.', 'modohome-katalog-produktow' ) );
		}

		if ( ! current_user_can( 'edit_modohome_product', $post_id ) ) {
			return new \WP_Error( 'modohome_catalog_forbidden', __( 'Brak uprawnień do edycji tego produktu.', 'modohome-katalog-produktow' ) );
		}

		$status = Product::sanitize_availability( $status );
		$before = Product::get_availability( $post_id );

		if ( $before === $status ) {
			return true;
		}

		update_post_meta( $post_id, Product::META_AVAILABILITY, $status );
		delete_post_meta( $post_id, Product::META_AUTO_HIDDEN );

		Activity_Log::record(
			Activity_Log::ACTION_STATUS_CHANGED,
			$post_id,
			sprintf(
				/* translators: 1: poprzedni status, 2: nowy status. */
				__( 'Status: %1$s → %2$s', 'modohome-katalog-produktow' ),
				Product::status_label( $before ),
				Product::status_label( $status )
			)
		);

		self::flush_counts();

		return true;
	}

	/**
	 * Przenosi produkt do kosza.
	 *
	 * @param int $post_id Identyfikator produktu.
	 *
	 * @return true|\WP_Error
	 */
	public static function trash( int $post_id ): true|\WP_Error {
		$post = get_post( $post_id );

		if ( ! $post instanceof \WP_Post || Post_Type::SLUG !== $post->post_type ) {
			return new \WP_Error( 'modohome_catalog_not_found', __( 'Nie znaleziono produktu.', 'modohome-katalog-produktow' ) );
		}

		if ( ! current_user_can( 'delete_modohome_product', $post_id ) ) {
			return new \WP_Error( 'modohome_catalog_forbidden', __( 'Brak uprawnień do usunięcia tego produktu.', 'modohome-katalog-produktow' ) );
		}

		$result = wp_trash_post( $post_id );

		if ( ! $result ) {
			return new \WP_Error( 'modohome_catalog_trash_failed', __( 'Nie udało się przenieść produktu do kosza.', 'modohome-katalog-produktow' ) );
		}

		self::flush_counts();

		return true;
	}

	/**
	 * Duplikuje produkt razem z polami własnymi, kategoriami i zdjęciami.
	 *
	 * Kopia powstaje jako szkic, żeby nie trafiła od razu do katalogu.
	 *
	 * @param int $post_id Identyfikator produktu źródłowego.
	 *
	 * @return int|\WP_Error Identyfikator kopii.
	 */
	public static function duplicate( int $post_id ): int|\WP_Error {
		$post = get_post( $post_id );

		if ( ! $post instanceof \WP_Post || Post_Type::SLUG !== $post->post_type ) {
			return new \WP_Error( 'modohome_catalog_not_found', __( 'Nie znaleziono produktu.', 'modohome-katalog-produktow' ) );
		}

		if ( ! current_user_can( 'edit_modohome_product', $post_id ) ) {
			return new \WP_Error( 'modohome_catalog_forbidden', __( 'Brak uprawnień do duplikowania tego produktu.', 'modohome-katalog-produktow' ) );
		}

		if ( ! current_user_can( 'edit_modohome_products' ) ) {
			return new \WP_Error( 'modohome_catalog_forbidden', __( 'Brak uprawnień do tworzenia produktów.', 'modohome-katalog-produktow' ) );
		}

		$new_id = wp_insert_post(
			array(
				'post_type'    => Post_Type::SLUG,
				'post_title'   => sprintf(
					/* translators: %s: nazwa produktu źródłowego. */
					__( '%s (kopia)', 'modohome-katalog-produktow' ),
					$post->post_title
				),
				'post_status'  => 'draft',
				'post_author'  => get_current_user_id(),
				'menu_order'   => $post->menu_order,
				'post_content' => '',
			),
			true
		);

		if ( is_wp_error( $new_id ) ) {
			return $new_id;
		}

		$new_id = (int) $new_id;

		$meta_keys = array(
			Product::META_PRICE,
			Product::META_OLD_PRICE,
			Product::META_DESCRIPTION,
			Product::META_BADGE,
			Product::META_AVAILABILITY,
			Product::META_GALLERY,
		);

		foreach ( $meta_keys as $key ) {
			$value = get_post_meta( $post_id, $key, true );

			if ( '' !== $value && null !== $value ) {
				update_post_meta( $new_id, $key, $value );
			}
		}

		$thumbnail_id = (int) get_post_thumbnail_id( $post_id );

		if ( $thumbnail_id > 0 ) {
			set_post_thumbnail( $new_id, $thumbnail_id );
		}

		$terms = wp_get_object_terms( $post_id, Taxonomy::SLUG, array( 'fields' => 'ids' ) );

		if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
			wp_set_object_terms( $new_id, array_map( 'intval', $terms ), Taxonomy::SLUG, false );
		}

		Activity_Log::record(
			Activity_Log::ACTION_DUPLICATED,
			$new_id,
			sprintf(
				/* translators: %d: identyfikator produktu źródłowego. */
				__( 'Kopia produktu #%d', 'modohome-katalog-produktow' ),
				$post_id
			)
		);

		return $new_id;
	}

	/**
	 * Zapisuje cenę i loguje zmianę, jeśli wartość faktycznie się zmieniła.
	 *
	 * @param int    $post_id Identyfikator produktu.
	 * @param string $price   Cena po sanityzacji.
	 */
	public static function update_price( int $post_id, string $price ): void {
		$before = (string) get_post_meta( $post_id, Product::META_PRICE, true );

		if ( $before === $price ) {
			return;
		}

		update_post_meta( $post_id, Product::META_PRICE, $price );

		if ( '' !== $before ) {
			Activity_Log::record(
				Activity_Log::ACTION_PRICE_CHANGED,
				$post_id,
				sprintf(
					/* translators: 1: poprzednia cena, 2: nowa cena. */
					__( 'Cena: %1$s → %2$s', 'modohome-katalog-produktow' ),
					Product::format_price( (float) $before ),
					Product::format_price( '' === $price ? null : (float) $price )
				)
			);
		}
	}

	/**
	 * Czyści pamięć podręczną liczników kategorii.
	 */
	public static function flush_counts(): void {
		if ( function_exists( 'wp_cache_flush_group' ) ) {
			wp_cache_flush_group( 'modohome_catalog' );
		}
	}
}
