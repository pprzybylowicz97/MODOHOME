<?php
/**
 * Budowanie zapytań katalogu.
 *
 * @package MODOhome\Catalog
 */

declare( strict_types = 1 );

namespace MODOhome\Catalog;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Jedno miejsce, w którym powstaje zapytanie o produkty — używane przez shortcode i AJAX.
 */
class Query {

	/**
	 * Buduje i wykonuje zapytanie o produkty katalogu.
	 *
	 * @param array<string,mixed> $args Parametry katalogu.
	 */
	public static function products( array $args ): \WP_Query {
		$args = wp_parse_args(
			$args,
			array(
				'categories' => array(),
				'search'     => '',
				'orderby'    => (string) Settings::get( 'default_order', 'date' ),
				'per_page'   => Settings::int( 'per_page' ),
				'page'       => 1,
				'limit'      => 0,
				'show_sold'  => (string) Settings::get( 'show_sold', 'show' ),
			)
		);

		$per_page = max( 1, (int) $args['per_page'] );

		// „limit” z shortcode’u ogranicza całkowitą liczbę produktów.
		if ( (int) $args['limit'] > 0 ) {
			$per_page = min( $per_page, (int) $args['limit'] );
		}

		$query_args = array(
			'post_type'           => Post_Type::SLUG,
			'post_status'         => 'publish',
			'posts_per_page'      => $per_page,
			'paged'               => max( 1, (int) $args['page'] ),
			'ignore_sticky_posts' => true,
			'no_found_rows'       => false,
		);

		// Produkty ukryte nigdy nie trafiają do katalogu publicznego.
		$excluded = array( Product::STATUS_HIDDEN );

		if ( 'hide' === $args['show_sold'] ) {
			$excluded[] = Product::STATUS_SOLD;
		}

		$meta_query = array(
			'relation' => 'AND',
			array(
				'relation' => 'OR',
				array(
					'key'     => Product::META_AVAILABILITY,
					'value'   => $excluded,
					'compare' => 'NOT IN',
				),
				array(
					'key'     => Product::META_AVAILABILITY,
					'compare' => 'NOT EXISTS',
				),
			),
		);

		$orderby = (string) $args['orderby'];

		if ( in_array( $orderby, array( 'price_asc', 'price_desc' ), true ) ) {
			// Klucz ceny musi istnieć, żeby sortowanie numeryczne było przewidywalne.
			$meta_query['modohome_price'] = array(
				'key'     => Product::META_PRICE,
				'compare' => 'EXISTS',
				'type'    => 'DECIMAL(12,2)',
			);

			$query_args['orderby'] = array( 'modohome_price' => 'price_asc' === $orderby ? 'ASC' : 'DESC' );
		} elseif ( 'menu_order' === $orderby ) {
			$query_args['orderby'] = array(
				'menu_order' => 'ASC',
				'date'       => 'DESC',
			);
		} else {
			$query_args['orderby'] = array(
				'menu_order' => 'ASC',
				'date'       => 'DESC',
			);
		}

		$query_args['meta_query'] = $meta_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query

		$categories = self::normalize_categories( $args['categories'] );

		if ( ! empty( $categories ) ) {
			$query_args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy'         => Taxonomy::SLUG,
					'field'            => 'term_id',
					'terms'            => $categories,
					'include_children' => true,
				),
			);
		}

		$search = trim( (string) $args['search'] );

		if ( '' !== $search ) {
			$query_args['s'] = $search;
			// Wyszukiwanie ma obejmować wyłącznie nazwę produktu.
			add_filter( 'posts_search', array( self::class, 'search_title_only' ), 10, 2 );
		}

		$query = new \WP_Query( $query_args );

		if ( '' !== $search ) {
			remove_filter( 'posts_search', array( self::class, 'search_title_only' ), 10 );
		}

		return $query;
	}

	/**
	 * Zawęża wyszukiwanie do tytułu wpisu.
	 *
	 * @param string    $search Fragment SQL.
	 * @param \WP_Query $query  Zapytanie.
	 */
	public static function search_title_only( string $search, \WP_Query $query ): string {
		global $wpdb;

		$term = $query->get( 's' );

		if ( ! is_string( $term ) || '' === $term ) {
			return $search;
		}

		$like = '%' . $wpdb->esc_like( $term ) . '%';

		return (string) $wpdb->prepare( " AND {$wpdb->posts}.post_title LIKE %s ", $like );
	}

	/**
	 * Zamienia slugi lub identyfikatory kategorii na listę identyfikatorów.
	 *
	 * @param mixed $categories Lista kategorii (tablica lub ciąg po przecinku).
	 *
	 * @return array<int>
	 */
	public static function normalize_categories( mixed $categories ): array {
		if ( is_string( $categories ) ) {
			$categories = explode( ',', $categories );
		}

		if ( ! is_array( $categories ) ) {
			return array();
		}

		$ids = array();

		foreach ( $categories as $item ) {
			$item = is_string( $item ) ? trim( $item ) : $item;

			if ( '' === $item || null === $item ) {
				continue;
			}

			if ( is_numeric( $item ) ) {
				$term = get_term( (int) $item, Taxonomy::SLUG );
			} else {
				$term = get_term_by( 'slug', sanitize_title( (string) $item ), Taxonomy::SLUG );
			}

			if ( $term instanceof \WP_Term ) {
				$ids[] = $term->term_id;
			}
		}

		return array_values( array_unique( $ids ) );
	}

	/**
	 * Liczba opublikowanych, widocznych produktów w kategorii (z podkategoriami).
	 *
	 * @param int $term_id Identyfikator kategorii.
	 */
	public static function count_in_category( int $term_id ): int {
		$cache_key = 'modohome_catalog_count_' . $term_id . '_' . (string) Settings::get( 'show_sold', 'show' );
		$cached    = wp_cache_get( $cache_key, 'modohome_catalog' );

		if ( false !== $cached ) {
			return (int) $cached;
		}

		$query = self::products(
			array(
				'categories' => array( $term_id ),
				'per_page'   => 1,
				'page'       => 1,
			)
		);

		$count = (int) $query->found_posts;

		wp_cache_set( $cache_key, $count, 'modohome_catalog', 5 * MINUTE_IN_SECONDS );

		return $count;
	}
}
