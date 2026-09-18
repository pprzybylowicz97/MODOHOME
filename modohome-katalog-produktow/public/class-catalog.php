<?php
/**
 * Renderowanie katalogu produktów na froncie.
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
use MODOhome\Catalog\Taxonomy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Katalog składa się z paska filtrów, siatki kart i wspólnego okna modalnego.
 */
class Catalog {

	/**
	 * Licznik instancji na stronie — pozwala umieścić kilka katalogów naraz.
	 */
	private static int $instance = 0;

	/**
	 * Renderuje katalog.
	 *
	 * @param array<string,mixed> $atts Atrybuty shortcode’u.
	 */
	public function render( array $atts ): string {
		++self::$instance;

		$fixed_categories = Query::normalize_categories(
			'' !== (string) $atts['categories'] ? (string) $atts['categories'] : (string) $atts['category']
		);

		$columns   = (int) $atts['columns'];
		$columns   = ( $columns >= 1 && $columns <= 6 ) ? $columns : Settings::int( 'columns_desktop' );
		$limit     = max( 0, (int) $atts['limit'] );
		$per_page  = $limit > 0 ? min( $limit, Settings::int( 'per_page' ) ) : Settings::int( 'per_page' );

		$show_filters = Shortcodes::bool_att( $atts['show_filters'], Settings::bool( 'enable_filters' ) );
		$show_search  = Shortcodes::bool_att( $atts['show_search'], Settings::bool( 'enable_search' ) );

		// Gdy shortcode wskazuje konkretne kategorie, filtr kategorii nie ma sensu.
		$show_category_filter = $show_filters && empty( $fixed_categories );

		$orderby = (string) $atts['orderby'];
		$orderby = in_array( $orderby, array( 'date', 'price_asc', 'price_desc', 'menu_order' ), true )
			? $orderby
			: (string) Settings::get( 'default_order', 'date' );

		$show_sold = (string) $atts['show_sold'];
		$show_sold = in_array( $show_sold, array( 'show', 'hide' ), true )
			? $show_sold
			: (string) Settings::get( 'show_sold', 'show' );

		$query = Query::products(
			array(
				'categories' => $fixed_categories,
				'orderby'    => $orderby,
				'per_page'   => $per_page,
				'page'       => 1,
				'limit'      => $limit,
				'show_sold'  => $show_sold,
			)
		);

		$total = (int) $query->found_posts;

		if ( $limit > 0 ) {
			$total = min( $total, $limit );
		}

		$pages = (int) ceil( $total / max( 1, $per_page ) );

		$heading = '' !== (string) $atts['heading']
			? (string) $atts['heading']
			: (string) Settings::get( 'catalog_heading', '' );

		$config = array(
			'categories' => $fixed_categories,
			'orderby'    => $orderby,
			'perPage'    => $per_page,
			'limit'      => $limit,
			'showSold'   => $show_sold,
			'columns'    => $columns,
			'loadMore'   => Settings::bool( 'enable_load_more' ),
		);

		return Plugin::render_template(
			'catalog',
			array(
				'instance_id'          => 'modohome-catalog-' . self::$instance,
				'query'                => $query,
				'columns'              => $columns,
				'heading'              => $heading,
				'intro'                => (string) Settings::get( 'catalog_intro', '' ),
				'show_filters'         => $show_filters,
				'show_category_filter' => $show_category_filter,
				'show_search'          => $show_search,
				'orderby'              => $orderby,
				'terms'                => $show_category_filter ? Taxonomy::get_ordered_terms( true ) : array(),
				'config'               => $config,
				'total'                => $total,
				'max_pages'            => $pages,
				'limit'                => $limit,
				'use_load_more'        => Settings::bool( 'enable_load_more' ),
			)
		);
	}

	/**
	 * Renderuje pojedynczą kartę produktu.
	 *
	 * @param \WP_Post $post Produkt.
	 */
	public static function render_card( \WP_Post $post ): string {
		return Plugin::render_template(
			'product-card',
			array(
				'product' => Product::to_array( $post ),
				'post'    => $post,
			)
		);
	}

	/**
	 * Renderuje zawartość okna modalnego produktu.
	 *
	 * @param \WP_Post $post Produkt.
	 */
	public static function render_modal_content( \WP_Post $post ): string {
		return Plugin::render_template(
			'product-modal',
			array(
				'product' => Product::to_array( $post ),
				'post'    => $post,
			)
		);
	}

	/**
	 * Buduje listę numerów stron z wielokropkami.
	 *
	 * Dla 8 stron i strony 1 zwraca: 1, 2, 3, …, 8.
	 *
	 * @param int $current Bieżąca strona.
	 * @param int $total   Liczba stron.
	 *
	 * @return array<int,int|string>
	 */
	public static function pagination_items( int $current, int $total ): array {
		if ( $total < 2 ) {
			return array();
		}

		$current = max( 1, min( $total, $current ) );
		$start   = max( 1, $current - 1 );
		$end     = min( $total, $current + 1 );

		// W oknie pokazujemy co najmniej trzy strony, o ile jest ich tyle.
		while ( ( $end - $start ) < 2 && ( $start > 1 || $end < $total ) ) {
			if ( $start > 1 ) {
				--$start;
			} elseif ( $end < $total ) {
				++$end;
			}
		}

		$items = array();

		if ( $start > 1 ) {
			$items[] = 1;

			if ( $start > 2 ) {
				$items[] = '…';
			}
		}

		for ( $page = $start; $page <= $end; $page++ ) {
			$items[] = $page;
		}

		if ( $end < $total ) {
			if ( $end < $total - 1 ) {
				$items[] = '…';
			}

			$items[] = $total;
		}

		return $items;
	}

	/**
	 * Renderuje numerowaną paginację katalogu.
	 *
	 * @param int $current Bieżąca strona.
	 * @param int $total   Liczba stron.
	 */
	public static function render_pagination( int $current, int $total ): string {
		$items = self::pagination_items( $current, $total );

		if ( empty( $items ) ) {
			return '';
		}

		$current = max( 1, min( $total, $current ) );
		$html    = '';

		foreach ( $items as $item ) {
			if ( ! is_int( $item ) ) {
				$html .= '<span class="modohome-catalog-page modohome-catalog-page--gap" aria-hidden="true">' . esc_html( (string) $item ) . '</span>';
				continue;
			}

			$is_current = ( $item === $current );

			$html .= sprintf(
				'<button type="button" class="modohome-catalog-page%1$s" data-modohome-page="%2$d"%3$s>%4$s</button>',
				$is_current ? ' is-current' : '',
				$item,
				$is_current ? ' aria-current="page"' : '',
				esc_html( (string) $item )
			);
		}

		return $html;
	}
}
