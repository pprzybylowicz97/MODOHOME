<?php
/**
 * Zajawka najnowszych produktów — przeznaczona na stronę główną.
 *
 * @package MODOhome\Catalog
 */

declare( strict_types = 1 );

namespace MODOhome\Catalog\Frontend;

use MODOhome\Catalog\Plugin;
use MODOhome\Catalog\Product;
use MODOhome\Catalog\Query;
use MODOhome\Catalog\Settings;
use MODOhome\Catalog\Shortcodes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Kafelek zajawki różni się od katalogowego: pasek etykiety na całą szerokość,
 * krótki opis zamiast ceny i odnośnik prowadzący do pełnego katalogu.
 */
class Latest {

	/**
	 * Licznik instancji na stronie.
	 */
	private static int $instance = 0;

	/**
	 * Renderuje zajawkę.
	 *
	 * @param array<string,mixed> $atts Atrybuty shortcode’u.
	 */
	public function render( array $atts ): string {
		++self::$instance;

		$limit   = max( 1, min( 24, (int) $atts['limit'] ) );
		$columns = (int) $atts['columns'];
		$columns = ( $columns >= 1 && $columns <= 6 ) ? $columns : 4;

		$categories = Query::normalize_categories(
			'' !== (string) $atts['categories'] ? (string) $atts['categories'] : (string) $atts['category']
		);

		$orderby = (string) $atts['orderby'];
		$orderby = in_array( $orderby, array( 'latest', 'price_asc', 'price_desc', 'menu_order' ), true )
			? $orderby
			: 'latest';

		$query = Query::products(
			array(
				'categories' => $categories,
				'orderby'    => $orderby,
				'per_page'   => $limit,
				'page'       => 1,
				'limit'      => $limit,
				'show_sold'  => (string) Settings::get( 'show_sold', 'show' ),
			)
		);

		if ( empty( $query->posts ) ) {
			return '';
		}

		// Odnośnik „Cena w katalogu”: atrybut shortcode’u ma pierwszeństwo
		// przed adresem z ustawień.
		$link = trim( (string) $atts['link'] );

		if ( '' === $link ) {
			$link = (string) Settings::get( 'catalog_page_url', '' );
		}

		$link      = '' !== $link ? esc_url_raw( $link ) : '';
		$link_text = trim( (string) $atts['link_text'] );

		if ( '' === $link_text ) {
			$link_text = '' !== $link
				? __( 'Cena w katalogu', 'modohome-katalog-produktow' )
				: __( 'Zobacz szczegóły', 'modohome-katalog-produktow' );
		}

		$ratio = (string) $atts['ratio'];
		$ratio = array_key_exists( $ratio, Settings::image_ratios() ) ? $ratio : '4:3';

		return Plugin::render_template(
			'latest',
			array(
				'instance_id'      => 'modohome-latest-' . self::$instance,
				'query'            => $query,
				'columns'          => $columns,
				'heading'          => (string) $atts['heading'],
				'intro'            => (string) $atts['intro'],
				'link'             => $link,
				'link_text'        => $link_text,
				'ratio'            => $ratio,
				'ratio_css'        => Settings::ratio_to_css( $ratio ),
				'media_auto'       => 'auto' === $ratio,
				'show_description' => Shortcodes::bool_att( $atts['show_description'], true ),
				'show_price'       => Shortcodes::bool_att( $atts['show_price'], false ),
				'show_category'    => Shortcodes::bool_att( $atts['show_category'], true ),
				'show_badges'      => Shortcodes::bool_att( $atts['show_badges'], true ),
				'all_link'         => $link,
				'all_text'         => trim( (string) $atts['all_text'] ),
			)
		);
	}

	/**
	 * Renderuje pojedynczy kafelek zajawki.
	 *
	 * @param \WP_Post            $post    Produkt.
	 * @param array<string,mixed> $options Ustawienia zajawki.
	 */
	public static function render_card( \WP_Post $post, array $options ): string {
		return Plugin::render_template(
			'latest-card',
			array(
				'product' => Product::to_array( $post ),
				'post'    => $post,
				'options' => $options,
			)
		);
	}
}
