<?php
/**
 * Obsługa żądań AJAX.
 *
 * @package MODOhome\Catalog
 */

declare( strict_types = 1 );

namespace MODOhome\Catalog;

use MODOhome\Catalog\Frontend\Catalog;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Dwa zestawy nonce: publiczny dla katalogu i prywatny dla operacji zapisu.
 */
class Ajax {

	public const NONCE_PUBLIC  = 'modohome_catalog_public';
	public const NONCE_PRIVATE = 'modohome_catalog_private';

	/**
	 * Podpina wszystkie punkty końcowe.
	 */
	public function register(): void {
		// Katalog — dostępny także dla niezalogowanych.
		add_action( 'wp_ajax_modohome_catalog_filter', array( $this, 'filter_products' ) );
		add_action( 'wp_ajax_nopriv_modohome_catalog_filter', array( $this, 'filter_products' ) );

		add_action( 'wp_ajax_modohome_catalog_details', array( $this, 'product_details' ) );
		add_action( 'wp_ajax_nopriv_modohome_catalog_details', array( $this, 'product_details' ) );

		// Operacje zapisu — wyłącznie dla zalogowanych.
		add_action( 'wp_ajax_modohome_catalog_add_product', array( $this, 'add_product' ) );
		add_action( 'wp_ajax_modohome_catalog_update_product', array( $this, 'update_product' ) );
		add_action( 'wp_ajax_modohome_catalog_set_status', array( $this, 'set_status' ) );
		add_action( 'wp_ajax_modohome_catalog_trash_product', array( $this, 'trash_product' ) );
		add_action( 'wp_ajax_modohome_catalog_duplicate_product', array( $this, 'duplicate_product' ) );
	}

	/**
	 * Filtrowanie, wyszukiwanie i „Pokaż więcej” w katalogu.
	 */
	public function filter_products(): void {
		check_ajax_referer( self::NONCE_PUBLIC, 'nonce' );

		$page     = isset( $_POST['page'] ) ? max( 1, (int) $_POST['page'] ) : 1;
		$per_page = isset( $_POST['per_page'] ) ? max( 1, min( 100, (int) $_POST['per_page'] ) ) : Settings::int( 'per_page' );
		$limit    = isset( $_POST['limit'] ) ? max( 0, (int) $_POST['limit'] ) : 0;

		$search = isset( $_POST['search'] )
			? sanitize_text_field( wp_unslash( (string) $_POST['search'] ) )
			: '';

		$orderby = isset( $_POST['orderby'] )
			? sanitize_key( wp_unslash( (string) $_POST['orderby'] ) )
			: (string) Settings::get( 'default_order', 'date' );

		if ( ! in_array( $orderby, array( 'date', 'price_asc', 'price_desc', 'menu_order' ), true ) ) {
			$orderby = (string) Settings::get( 'default_order', 'date' );
		}

		$show_sold = isset( $_POST['show_sold'] )
			? sanitize_key( wp_unslash( (string) $_POST['show_sold'] ) )
			: (string) Settings::get( 'show_sold', 'show' );

		if ( ! in_array( $show_sold, array( 'show', 'hide' ), true ) ) {
			$show_sold = (string) Settings::get( 'show_sold', 'show' );
		}

		$categories = array();

		if ( isset( $_POST['categories'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- normalizacja poniżej.
			$raw        = wp_unslash( $_POST['categories'] );
			$categories = Query::normalize_categories( is_array( $raw ) ? array_map( 'sanitize_text_field', $raw ) : sanitize_text_field( (string) $raw ) );
		}

		// Limit z shortcode’u nie może zostać obejściem przez kolejne strony.
		if ( $limit > 0 && ( ( $page - 1 ) * $per_page ) >= $limit ) {
			wp_send_json_success(
				array(
					'html'       => '',
					'hasMore'    => false,
					'total'      => $limit,
					'totalText'  => number_format_i18n( $limit ),
					'countLabel' => sprintf(
						/* translators: %s: liczba produktów. */
						_n( '%s produkt', '%s produktów', $limit, 'modohome-katalog-produktow' ),
						number_format_i18n( $limit )
					),
					'page'       => $page,
					'pages'      => (int) ceil( $limit / max( 1, $per_page ) ),
					'pagination' => '',
				)
			);
		}

		$query = Query::products(
			array(
				'categories' => $categories,
				'search'     => $search,
				'orderby'    => $orderby,
				'per_page'   => $per_page,
				'page'       => $page,
				'limit'      => $limit,
				'show_sold'  => $show_sold,
			)
		);

		$html = '';

		foreach ( $query->posts as $post ) {
			if ( $post instanceof \WP_Post ) {
				$html .= Catalog::render_card( $post );
			}
		}

		$total    = (int) $query->found_posts;
		$capped   = ( $limit > 0 ) ? min( $total, $limit ) : $total;
		$shown    = ( $page - 1 ) * $per_page + count( $query->posts );
		$has_more = $shown < $capped;
		$pages    = (int) ceil( $capped / max( 1, $per_page ) );

		wp_send_json_success(
			array(
				'html'       => $html,
				'hasMore'    => $has_more,
				'total'      => $capped,
				'totalText'  => number_format_i18n( $capped ),
				'countLabel' => sprintf(
					/* translators: %s: liczba produktów. */
					_n( '%s produkt', '%s produktów', $capped, 'modohome-katalog-produktow' ),
					number_format_i18n( $capped )
				),
				'page'       => $page,
				'pages'      => $pages,
				'pagination' => Catalog::render_pagination( $page, $pages ),
			)
		);
	}

	/**
	 * Zawartość okna modalnego produktu.
	 */
	public function product_details(): void {
		check_ajax_referer( self::NONCE_PUBLIC, 'nonce' );

		$post_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$post    = $post_id > 0 ? get_post( $post_id ) : null;

		if ( ! $post instanceof \WP_Post || Post_Type::SLUG !== $post->post_type || 'publish' !== $post->post_status ) {
			wp_send_json_error( array( 'message' => __( 'Nie znaleziono produktu.', 'modohome-katalog-produktow' ) ), 404 );
		}

		$availability = Product::get_availability( $post_id );

		// Produkty ukryte nie wyciekają przez bezpośrednie wywołanie punktu końcowego.
		if ( Product::STATUS_HIDDEN === $availability && ! current_user_can( 'edit_modohome_product', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Nie znaleziono produktu.', 'modohome-katalog-produktow' ) ), 404 );
		}

		wp_send_json_success(
			array(
				'html'  => Catalog::render_modal_content( $post ),
				'title' => get_the_title( $post ),
			)
		);
	}

	/**
	 * Dodanie produktu z formularza frontendowego.
	 */
	public function add_product(): void {
		check_ajax_referer( self::NONCE_PRIVATE, 'nonce' );

		if ( ! Roles::can_submit() ) {
			wp_send_json_error( array( 'message' => __( 'Brak uprawnień do dodawania produktów.', 'modohome-katalog-produktow' ) ), 403 );
		}

		$fields = $this->read_product_fields();

		$errors = array();

		if ( '' === $fields['title'] ) {
			$errors['title'] = __( 'Podaj nazwę produktu.', 'modohome-katalog-produktow' );
		}

		if ( '' === $fields['price'] ) {
			$errors['price'] = __( 'Podaj cenę produktu.', 'modohome-katalog-produktow' );
		}

		if ( empty( $fields['terms'] ) ) {
			$errors['category'] = __( 'Wybierz kategorię produktu.', 'modohome-katalog-produktow' );
		}

		if ( empty( $_FILES['product_image'] ) || ( isset( $_FILES['product_image']['error'] ) && UPLOAD_ERR_NO_FILE === (int) $_FILES['product_image']['error'] ) ) {
			$errors['image'] = __( 'Dodaj zdjęcie produktu.', 'modohome-katalog-produktow' );
		}

		if ( ! empty( $errors ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Uzupełnij wymagane pola.', 'modohome-katalog-produktow' ),
					'fields'  => $errors,
				),
				400
			);
		}

		// Pracownikowi prawo publikacji zdejmuje Roles::filter_dynamic_caps(),
		// gdy administrator wymaga zatwierdzania. Administrator publikuje zawsze.
		$post_status = current_user_can( 'publish_modohome_products' ) ? 'publish' : 'pending';

		$post_id = wp_insert_post(
			array(
				'post_type'    => Post_Type::SLUG,
				'post_title'   => $fields['title'],
				'post_status'  => $post_status,
				'post_author'  => get_current_user_id(),
				'post_content' => '',
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			wp_send_json_error( array( 'message' => $post_id->get_error_message() ), 500 );
		}

		$post_id = (int) $post_id;

		$attachment_id = Image_Handler::handle_upload( 'product_image', $post_id );

		if ( is_wp_error( $attachment_id ) ) {
			// Nie zostawiamy pustego produktu, gdy zdjęcie się nie powiodło.
			wp_delete_post( $post_id, true );

			wp_send_json_error(
				array(
					'message' => $attachment_id->get_error_message(),
					'fields'  => array( 'image' => $attachment_id->get_error_message() ),
				),
				400
			);
		}

		Image_Handler::set_featured_image( $post_id, $attachment_id, $fields['title'] );

		update_post_meta( $post_id, Product::META_PRICE, $fields['price'] );
		update_post_meta( $post_id, Product::META_OLD_PRICE, $fields['old_price'] );
		update_post_meta( $post_id, Product::META_DESCRIPTION, $fields['description'] );
		update_post_meta( $post_id, Product::META_BADGE, $fields['badge'] );
		update_post_meta( $post_id, Product::META_AVAILABILITY, $fields['availability'] );

		wp_set_object_terms( $post_id, $fields['terms'], Taxonomy::SLUG, false );

		Product_Actions::flush_counts();

		$post = get_post( $post_id );

		if ( ! $post instanceof \WP_Post ) {
			wp_send_json_error( array( 'message' => __( 'Nie udało się odczytać zapisanego produktu.', 'modohome-katalog-produktow' ) ), 500 );
		}

		wp_send_json_success(
			array(
				'message' => 'pending' === $post_status
					? __( 'Produkt został dodany i czeka na zatwierdzenie', 'modohome-katalog-produktow' )
					: __( 'Produkt został dodany', 'modohome-katalog-produktow' ),
				'pending' => 'pending' === $post_status,
				'product' => Product::to_array( $post ),
			)
		);
	}

	/**
	 * Edycja produktu z panelu „Moje produkty”.
	 */
	public function update_product(): void {
		check_ajax_referer( self::NONCE_PRIVATE, 'nonce' );

		$post_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$post    = $post_id > 0 ? get_post( $post_id ) : null;

		if ( ! $post instanceof \WP_Post || Post_Type::SLUG !== $post->post_type ) {
			wp_send_json_error( array( 'message' => __( 'Nie znaleziono produktu.', 'modohome-katalog-produktow' ) ), 404 );
		}

		if ( ! current_user_can( 'edit_modohome_product', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Brak uprawnień do edycji tego produktu.', 'modohome-katalog-produktow' ) ), 403 );
		}

		$fields = $this->read_product_fields();

		if ( '' === $fields['title'] ) {
			wp_send_json_error(
				array(
					'message' => __( 'Podaj nazwę produktu.', 'modohome-katalog-produktow' ),
					'fields'  => array( 'title' => __( 'Podaj nazwę produktu.', 'modohome-katalog-produktow' ) ),
				),
				400
			);
		}

		if ( '' === $fields['price'] ) {
			wp_send_json_error(
				array(
					'message' => __( 'Podaj cenę produktu.', 'modohome-katalog-produktow' ),
					'fields'  => array( 'price' => __( 'Podaj cenę produktu.', 'modohome-katalog-produktow' ) ),
				),
				400
			);
		}

		$updated = wp_update_post(
			array(
				'ID'         => $post_id,
				'post_title' => $fields['title'],
			),
			true
		);

		if ( is_wp_error( $updated ) ) {
			wp_send_json_error( array( 'message' => $updated->get_error_message() ), 500 );
		}

		// Zdjęcie jest opcjonalne przy edycji — zmieniamy tylko, gdy przesłano nowe.
		if ( ! empty( $_FILES['product_image'] ) && isset( $_FILES['product_image']['error'] ) && UPLOAD_ERR_NO_FILE !== (int) $_FILES['product_image']['error'] ) {
			$attachment_id = Image_Handler::handle_upload( 'product_image', $post_id );

			if ( is_wp_error( $attachment_id ) ) {
				wp_send_json_error(
					array(
						'message' => $attachment_id->get_error_message(),
						'fields'  => array( 'image' => $attachment_id->get_error_message() ),
					),
					400
				);
			}

			Image_Handler::set_featured_image( $post_id, $attachment_id, $fields['title'] );
		}

		Product_Actions::update_price( $post_id, $fields['price'] );

		update_post_meta( $post_id, Product::META_OLD_PRICE, $fields['old_price'] );
		update_post_meta( $post_id, Product::META_DESCRIPTION, $fields['description'] );
		update_post_meta( $post_id, Product::META_BADGE, $fields['badge'] );

		$before_status = Product::get_availability( $post_id );

		if ( $fields['availability'] !== $before_status ) {
			$result = Product_Actions::set_availability( $post_id, $fields['availability'] );

			if ( is_wp_error( $result ) ) {
				wp_send_json_error( array( 'message' => $result->get_error_message() ), 403 );
			}
		}

		if ( ! empty( $fields['terms'] ) ) {
			wp_set_object_terms( $post_id, $fields['terms'], Taxonomy::SLUG, false );
		}

		Product_Actions::flush_counts();

		$post = get_post( $post_id );

		wp_send_json_success(
			array(
				'message' => __( 'Zmiany zapisane', 'modohome-katalog-produktow' ),
				'product' => $post instanceof \WP_Post ? Product::to_array( $post ) : array(),
			)
		);
	}

	/**
	 * Szybka zmiana statusu dostępności.
	 */
	public function set_status(): void {
		check_ajax_referer( self::NONCE_PRIVATE, 'nonce' );

		$post_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$status  = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( (string) $_POST['status'] ) ) : '';

		$result = Product_Actions::set_availability( $post_id, $status );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 403 );
		}

		wp_send_json_success(
			array(
				'message' => __( 'Status zaktualizowany', 'modohome-katalog-produktow' ),
				'status'  => Product::get_availability( $post_id ),
				'label'   => Product::status_label( Product::get_availability( $post_id ) ),
			)
		);
	}

	/**
	 * Przeniesienie produktu do kosza.
	 */
	public function trash_product(): void {
		check_ajax_referer( self::NONCE_PRIVATE, 'nonce' );

		$post_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$result  = Product_Actions::trash( $post_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 403 );
		}

		wp_send_json_success( array( 'message' => __( 'Produkt przeniesiony do kosza', 'modohome-katalog-produktow' ) ) );
	}

	/**
	 * Duplikowanie produktu.
	 */
	public function duplicate_product(): void {
		check_ajax_referer( self::NONCE_PRIVATE, 'nonce' );

		$post_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$new_id  = Product_Actions::duplicate( $post_id );

		if ( is_wp_error( $new_id ) ) {
			wp_send_json_error( array( 'message' => $new_id->get_error_message() ), 403 );
		}

		$post = get_post( $new_id );

		wp_send_json_success(
			array(
				'message' => __( 'Produkt zduplikowany', 'modohome-katalog-produktow' ),
				'product' => $post instanceof \WP_Post ? Product::to_array( $post ) : array(),
			)
		);
	}

	/**
	 * Odczytuje i sanityzuje pola produktu z żądania.
	 *
	 * @return array{title:string,price:string,old_price:string,description:string,badge:string,availability:string,terms:array<int>}
	 */
	private function read_product_fields(): array {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce sprawdzony przez wywołującego.
		$title = isset( $_POST['title'] )
			? sanitize_text_field( wp_unslash( (string) $_POST['title'] ) )
			: '';

		$price = isset( $_POST['price'] )
			? Product::sanitize_price( wp_unslash( (string) $_POST['price'] ) )
			: '';

		$old_price = isset( $_POST['old_price'] )
			? Product::sanitize_price( wp_unslash( (string) $_POST['old_price'] ) )
			: '';

		$description = isset( $_POST['description'] )
			? sanitize_textarea_field( wp_unslash( (string) $_POST['description'] ) )
			: '';

		$badge = isset( $_POST['badge'] )
			? Product::sanitize_badge( wp_unslash( (string) $_POST['badge'] ) )
			: '';

		$availability = isset( $_POST['availability'] )
			? Product::sanitize_availability( wp_unslash( (string) $_POST['availability'] ) )
			: Product::STATUS_AVAILABLE;

		$terms = array();

		if ( isset( $_POST['categories'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- normalizacja poniżej.
			$raw   = wp_unslash( $_POST['categories'] );
			$terms = Query::normalize_categories( is_array( $raw ) ? array_map( 'sanitize_text_field', $raw ) : sanitize_text_field( (string) $raw ) );
		} elseif ( isset( $_POST['category'] ) ) {
			$terms = Query::normalize_categories( sanitize_text_field( wp_unslash( (string) $_POST['category'] ) ) );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		// Pracownik bez prawa zarządzania kategoriami nie tworzy nowych terminów —
		// normalize_categories() zwraca wyłącznie istniejące kategorie.
		return array(
			'title'        => $title,
			'price'        => $price,
			'old_price'    => $old_price,
			'description'  => $description,
			'badge'        => $badge,
			'availability' => $availability,
			'terms'        => $terms,
		);
	}
}
