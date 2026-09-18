<?php
/**
 * Mobilny panel „Moje produkty”.
 *
 * @package MODOhome\Catalog
 */

declare( strict_types = 1 );

namespace MODOhome\Catalog\Frontend;

use MODOhome\Catalog\Ajax;
use MODOhome\Catalog\Plugin;
use MODOhome\Catalog\Post_Type;
use MODOhome\Catalog\Product;
use MODOhome\Catalog\Roles;
use MODOhome\Catalog\Taxonomy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pracownik widzi wyłącznie własne produkty i może nimi zarządzać z telefonu.
 */
class My_Products {

	/**
	 * Renderuje panel.
	 *
	 * @param array<string,mixed> $atts Atrybuty shortcode’u.
	 */
	public function render( array $atts ): string {
		if ( ! is_user_logged_in() ) {
			return '<div class="modohome-catalog-notice modohome-catalog-notice--info"><p>'
				. esc_html__( 'Zaloguj się, aby zobaczyć swoje produkty.', 'modohome-katalog-produktow' )
				. '</p></div>';
		}

		if ( ! Roles::can_submit() ) {
			return '<div class="modohome-catalog-notice modohome-catalog-notice--info"><p>'
				. esc_html__( 'Twoje konto nie ma uprawnień do zarządzania produktami.', 'modohome-katalog-produktow' )
				. '</p></div>';
		}

		$limit = max( 1, min( 100, (int) $atts['limit'] ) );

		$query = new \WP_Query(
			array(
				'post_type'      => Post_Type::SLUG,
				'post_status'    => array( 'publish', 'pending', 'draft' ),
				'author'         => get_current_user_id(),
				'posts_per_page' => $limit,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'no_found_rows'  => true,
			)
		);

		return Plugin::render_template(
			'my-products',
			array(
				'query'    => $query,
				'statuses' => Product::statuses(),
				'terms'    => Taxonomy::get_ordered_terms( false ),
				'nonce'    => wp_create_nonce( Ajax::NONCE_PRIVATE ),
				'heading'  => '' !== (string) $atts['heading'] ? (string) $atts['heading'] : __( 'Moje produkty', 'modohome-katalog-produktow' ),
			)
		);
	}
}
