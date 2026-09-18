<?php
/**
 * Zadania cykliczne — automatyczne ukrywanie starych produktów.
 *
 * @package MODOhome\Catalog
 */

declare( strict_types = 1 );

namespace MODOhome\Catalog;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Funkcja jest opcjonalna: działa tylko, gdy administrator ustawi liczbę dni większą od zera.
 */
class Cron {

	/**
	 * Podpina zadanie cykliczne.
	 */
	public function register(): void {
		add_action( Activator::CRON_HOOK, array( $this, 'auto_hide_old_products' ) );
	}

	/**
	 * Ukrywa produkty starsze niż ustawiona liczba dni.
	 */
	public function auto_hide_old_products(): void {
		$days = Settings::int( 'auto_hide_days' );

		if ( $days <= 0 ) {
			return;
		}

		$query = new \WP_Query(
			array(
				'post_type'      => Post_Type::SLUG,
				'post_status'    => 'publish',
				'posts_per_page' => 100,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'date_query'     => array(
					array(
						'before' => gmdate( 'Y-m-d H:i:s', strtotime( '-' . $days . ' days' ) ?: time() ),
					),
				),
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'meta_query'     => array(
					'relation' => 'AND',
					array(
						'relation' => 'OR',
						array(
							'key'     => Product::META_AVAILABILITY,
							'value'   => Product::STATUS_HIDDEN,
							'compare' => '!=',
						),
						array(
							'key'     => Product::META_AVAILABILITY,
							'compare' => 'NOT EXISTS',
						),
					),
				),
			)
		);

		foreach ( $query->posts as $post_id ) {
			$post_id = (int) $post_id;

			update_post_meta( $post_id, Product::META_AVAILABILITY, Product::STATUS_HIDDEN );
			update_post_meta( $post_id, Product::META_AUTO_HIDDEN, time() );

			Activity_Log::record(
				Activity_Log::ACTION_STATUS_CHANGED,
				$post_id,
				sprintf(
					/* translators: %d: liczba dni. */
					__( 'Automatyczne ukrycie po %d dniach', 'modohome-katalog-produktow' ),
					$days
				),
				0
			);
		}

		if ( function_exists( 'wp_cache_flush_group' ) ) {
			wp_cache_flush_group( 'modohome_catalog' );
		}
	}
}
