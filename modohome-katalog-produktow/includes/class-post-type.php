<?php
/**
 * Typ wpisu „Produkty”.
 *
 * @package MODOhome\Catalog
 */

declare( strict_types = 1 );

namespace MODOhome\Catalog;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Rejestracja typu wpisu modohome_product.
 */
class Post_Type {

	public const SLUG = 'modohome_product';

	/**
	 * Podpina rejestrację pod init.
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_post_type' ), 5 );
		add_filter( 'post_updated_messages', array( $this, 'filter_messages' ) );
		add_action( 'wp', array( $this, 'guard_single_view' ) );
	}

	/**
	 * Rejestruje typ wpisu.
	 */
	public function register_post_type(): void {
		$public_single = Settings::bool( 'enable_single_pages' );

		$labels = array(
			'name'                  => __( 'Produkty', 'modohome-katalog-produktow' ),
			'singular_name'         => __( 'Produkt', 'modohome-katalog-produktow' ),
			'menu_name'             => __( 'Produkty', 'modohome-katalog-produktow' ),
			'add_new'               => __( 'Dodaj nowy', 'modohome-katalog-produktow' ),
			'add_new_item'          => __( 'Dodaj nowy produkt', 'modohome-katalog-produktow' ),
			'edit_item'             => __( 'Edytuj produkt', 'modohome-katalog-produktow' ),
			'new_item'              => __( 'Nowy produkt', 'modohome-katalog-produktow' ),
			'view_item'             => __( 'Zobacz produkt', 'modohome-katalog-produktow' ),
			'view_items'            => __( 'Zobacz produkty', 'modohome-katalog-produktow' ),
			'search_items'          => __( 'Szukaj produktów', 'modohome-katalog-produktow' ),
			'not_found'             => __( 'Nie znaleziono produktów.', 'modohome-katalog-produktow' ),
			'not_found_in_trash'    => __( 'Brak produktów w koszu.', 'modohome-katalog-produktow' ),
			'all_items'             => __( 'Wszystkie produkty', 'modohome-katalog-produktow' ),
			'featured_image'        => __( 'Zdjęcie główne', 'modohome-katalog-produktow' ),
			'set_featured_image'    => __( 'Ustaw zdjęcie główne', 'modohome-katalog-produktow' ),
			'remove_featured_image' => __( 'Usuń zdjęcie główne', 'modohome-katalog-produktow' ),
			'use_featured_image'    => __( 'Użyj jako zdjęcia głównego', 'modohome-katalog-produktow' ),
			'items_list'            => __( 'Lista produktów', 'modohome-katalog-produktow' ),
			'item_published'        => __( 'Produkt opublikowany.', 'modohome-katalog-produktow' ),
			'item_updated'          => __( 'Produkt zaktualizowany.', 'modohome-katalog-produktow' ),
		);

		$args = array(
			'labels'              => $labels,
			'description'         => __( 'Produkty katalogu MODOhome.', 'modohome-katalog-produktow' ),
			'public'              => $public_single,
			'publicly_queryable'  => $public_single,
			'exclude_from_search' => ! $public_single,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'show_in_nav_menus'   => $public_single,
			'show_in_admin_bar'   => true,
			'show_in_rest'        => false,
			'menu_position'       => 26,
			'menu_icon'           => 'dashicons-store',
			'hierarchical'        => false,
			'supports'            => array( 'title', 'author', 'thumbnail', 'page-attributes' ),
			'taxonomies'          => array( Taxonomy::SLUG ),
			'has_archive'         => false,
			'rewrite'             => $public_single ? array(
				'slug'       => 'produkt',
				'with_front' => false,
			) : false,
			'query_var'           => $public_single,
			'capability_type'     => array( 'modohome_product', 'modohome_products' ),
			'map_meta_cap'        => true,
			'capabilities'        => Roles::post_type_caps(),
			'delete_with_user'    => false,
		);

		register_post_type( self::SLUG, $args );
	}

	/**
	 * Gdy podstrony produktu są wyłączone, nikt nie dostaje się do nich adresem bezpośrednim.
	 */
	public function guard_single_view(): void {
		if ( is_admin() || Settings::bool( 'enable_single_pages' ) ) {
			return;
		}

		if ( ! is_singular( self::SLUG ) ) {
			return;
		}

		$post = get_queried_object();

		// Autor i osoba z prawem edycji mogą podejrzeć własny produkt.
		if ( $post instanceof \WP_Post && current_user_can( 'edit_modohome_product', $post->ID ) ) {
			return;
		}

		global $wp_query;

		$wp_query->set_404();
		status_header( 404 );
		nocache_headers();
	}

	/**
	 * Komunikaty po zapisie produktu.
	 *
	 * @param array<string,array<int,string>> $messages Komunikaty WordPressa.
	 *
	 * @return array<string,array<int,string>>
	 */
	public function filter_messages( array $messages ): array {
		$messages[ self::SLUG ] = array(
			0 => '',
			1 => __( 'Produkt zaktualizowany.', 'modohome-katalog-produktow' ),
			2 => __( 'Pole zaktualizowane.', 'modohome-katalog-produktow' ),
			3 => __( 'Pole usunięte.', 'modohome-katalog-produktow' ),
			4 => __( 'Produkt zaktualizowany.', 'modohome-katalog-produktow' ),
			5 => __( 'Przywrócono produkt z wersji archiwalnej.', 'modohome-katalog-produktow' ),
			6 => __( 'Produkt opublikowany.', 'modohome-katalog-produktow' ),
			7 => __( 'Produkt zapisany.', 'modohome-katalog-produktow' ),
			8 => __( 'Produkt przesłany do zatwierdzenia.', 'modohome-katalog-produktow' ),
			9 => __( 'Publikacja produktu zaplanowana.', 'modohome-katalog-produktow' ),
			10 => __( 'Szkic produktu zaktualizowany.', 'modohome-katalog-produktow' ),
		);

		return $messages;
	}
}
