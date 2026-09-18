<?php
/**
 * Lista produktów w panelu: kolumny, filtry, sortowanie i akcje.
 *
 * @package MODOhome\Catalog
 */

declare( strict_types = 1 );

namespace MODOhome\Catalog\Admin;

use MODOhome\Catalog\Post_Type;
use MODOhome\Catalog\Product;
use MODOhome\Catalog\Product_Actions;
use MODOhome\Catalog\Taxonomy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wszystko, co dzieje się na ekranie edit.php dla produktów.
 */
class Columns {

	/**
	 * Podpina filtry i akcje listy.
	 */
	public function register(): void {
		add_filter( 'manage_' . Post_Type::SLUG . '_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_' . Post_Type::SLUG . '_posts_custom_column', array( $this, 'render_column' ), 10, 2 );
		add_filter( 'manage_edit-' . Post_Type::SLUG . '_sortable_columns', array( $this, 'sortable_columns' ) );

		add_action( 'restrict_manage_posts', array( $this, 'render_filters' ) );
		add_action( 'pre_get_posts', array( $this, 'apply_filters_and_sorting' ) );

		add_filter( 'bulk_actions-edit-' . Post_Type::SLUG, array( $this, 'bulk_actions' ) );
		add_filter( 'handle_bulk_actions-edit-' . Post_Type::SLUG, array( $this, 'handle_bulk_actions' ), 10, 3 );

		add_filter( 'post_row_actions', array( $this, 'row_actions' ), 10, 2 );
		add_action( 'admin_action_modohome_catalog_mark_sold', array( $this, 'handle_mark_sold' ) );
		add_action( 'admin_action_modohome_catalog_duplicate', array( $this, 'handle_duplicate' ) );

		add_action( 'admin_notices', array( $this, 'render_notices' ) );
	}

	/**
	 * Definicja kolumn.
	 *
	 * @param array<string,string> $columns Kolumny WordPressa.
	 *
	 * @return array<string,string>
	 */
	public function columns( array $columns ): array {
		return array(
			'cb'                     => $columns['cb'] ?? '',
			'modohome_thumb'         => __( 'Zdjęcie', 'modohome-katalog-produktow' ),
			'title'                  => __( 'Nazwa', 'modohome-katalog-produktow' ),
			'modohome_price'         => __( 'Cena', 'modohome-katalog-produktow' ),
			'modohome_old_price'     => __( 'Cena poprzednia', 'modohome-katalog-produktow' ),
			'modohome_category'      => __( 'Kategoria', 'modohome-katalog-produktow' ),
			'modohome_badge'         => __( 'Etykieta', 'modohome-katalog-produktow' ),
			'modohome_availability'  => __( 'Dostępność', 'modohome-katalog-produktow' ),
			'author'                 => __( 'Autor', 'modohome-katalog-produktow' ),
			'date'                   => __( 'Data dodania', 'modohome-katalog-produktow' ),
		);
	}

	/**
	 * Zawartość kolumn własnych.
	 *
	 * @param string $column  Nazwa kolumny.
	 * @param int    $post_id Identyfikator produktu.
	 */
	public function render_column( string $column, int $post_id ): void {
		switch ( $column ) {
			case 'modohome_thumb':
				$thumb = get_the_post_thumbnail( $post_id, array( 60, 60 ), array( 'class' => 'modohome-admin-thumb' ) );

				if ( $thumb ) {
					echo wp_kses_post( $thumb );
				} else {
					echo '<span class="modohome-admin-thumb modohome-admin-thumb--empty" aria-hidden="true"></span>';
				}
				break;

			case 'modohome_price':
				echo esc_html( Product::format_price( Product::get_price( $post_id ) ) );
				break;

			case 'modohome_old_price':
				$old = Product::get_old_price( $post_id );
				echo '' !== Product::format_price( $old ) ? '<s>' . esc_html( Product::format_price( $old ) ) . '</s>' : '—';
				break;

			case 'modohome_category':
				$terms = get_the_terms( $post_id, Taxonomy::SLUG );

				if ( is_wp_error( $terms ) || empty( $terms ) ) {
					echo '—';
					break;
				}

				$names = array();

				foreach ( $terms as $term ) {
					$url = add_query_arg(
						array(
							'post_type'    => Post_Type::SLUG,
							Taxonomy::SLUG => $term->slug,
						),
						admin_url( 'edit.php' )
					);

					$names[] = '<a href="' . esc_url( $url ) . '">' . esc_html( $term->name ) . '</a>';
				}

				echo wp_kses_post( implode( ', ', $names ) );
				break;

			case 'modohome_badge':
				$badge = Product::get_badge( $post_id );
				echo '' !== $badge ? esc_html( Product::badge_label( $badge ) ) : '—';
				break;

			case 'modohome_availability':
				$status = Product::get_availability( $post_id );
				printf(
					'<span class="modohome-admin-status modohome-admin-status--%1$s">%2$s</span>',
					esc_attr( $status ),
					esc_html( Product::status_label( $status ) )
				);
				break;
		}
	}

	/**
	 * Kolumny, po których można sortować.
	 *
	 * @param array<string,string> $columns Kolumny sortowalne.
	 *
	 * @return array<string,string>
	 */
	public function sortable_columns( array $columns ): array {
		$columns['modohome_price'] = 'modohome_price';
		$columns['date']           = 'date';

		return $columns;
	}

	/**
	 * Pola filtrowania nad listą.
	 *
	 * @param string $post_type Typ wpisu bieżącego ekranu.
	 */
	public function render_filters( string $post_type ): void {
		if ( Post_Type::SLUG !== $post_type ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- filtry listy są odczytem.
		$selected_cat    = isset( $_GET[ Taxonomy::SLUG ] ) ? sanitize_text_field( wp_unslash( (string) $_GET[ Taxonomy::SLUG ] ) ) : '';
		$selected_status = isset( $_GET['modohome_availability'] ) ? sanitize_key( wp_unslash( (string) $_GET['modohome_availability'] ) ) : '';
		$selected_author = isset( $_GET['author'] ) ? absint( $_GET['author'] ) : 0;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		wp_dropdown_categories(
			array(
				'taxonomy'         => Taxonomy::SLUG,
				'name'             => Taxonomy::SLUG,
				'show_option_all'  => __( 'Wszystkie kategorie', 'modohome-katalog-produktow' ),
				'hierarchical'     => true,
				'hide_empty'       => false,
				'value_field'      => 'slug',
				'selected'         => $selected_cat,
				'orderby'          => 'name',
			)
		);

		echo '<select name="modohome_availability">';
		echo '<option value="">' . esc_html__( 'Każda dostępność', 'modohome-katalog-produktow' ) . '</option>';

		foreach ( Product::statuses() as $key => $label ) {
			printf(
				'<option value="%1$s"%2$s>%3$s</option>',
				esc_attr( $key ),
				selected( $selected_status, $key, false ),
				esc_html( $label )
			);
		}

		echo '</select>';

		wp_dropdown_users(
			array(
				'name'            => 'author',
				'show_option_all' => __( 'Wszyscy autorzy', 'modohome-katalog-produktow' ),
				'selected'        => $selected_author,
				'capability'      => array( 'edit_modohome_products' ),
			)
		);
	}

	/**
	 * Stosuje filtry i sortowanie do zapytania listy.
	 *
	 * @param \WP_Query $query Zapytanie.
	 */
	public function apply_filters_and_sorting( \WP_Query $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		if ( Post_Type::SLUG !== $query->get( 'post_type' ) ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- filtry listy są odczytem.
		$status = isset( $_GET['modohome_availability'] ) ? sanitize_key( wp_unslash( (string) $_GET['modohome_availability'] ) ) : '';

		if ( '' !== $status && array_key_exists( $status, Product::statuses() ) ) {
			$query->set(
				'meta_query', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					array(
						'key'   => Product::META_AVAILABILITY,
						'value' => $status,
					),
				)
			);
		}

		$orderby = $query->get( 'orderby' );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( 'modohome_price' === $orderby ) {
			$query->set( 'meta_key', Product::META_PRICE ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			$query->set( 'orderby', 'meta_value_num' );
		}
	}

	/**
	 * Akcje zbiorcze.
	 *
	 * @param array<string,string> $actions Akcje.
	 *
	 * @return array<string,string>
	 */
	public function bulk_actions( array $actions ): array {
		foreach ( Product::statuses() as $key => $label ) {
			$actions[ 'modohome_set_' . $key ] = sprintf(
				/* translators: %s: nazwa statusu. */
				__( 'Zmień dostępność na: %s', 'modohome-katalog-produktow' ),
				$label
			);
		}

		$actions['modohome_duplicate'] = __( 'Duplikuj produkty', 'modohome-katalog-produktow' );

		return $actions;
	}

	/**
	 * Obsługa akcji zbiorczych.
	 *
	 * @param string     $redirect Adres przekierowania.
	 * @param string     $action   Wybrana akcja.
	 * @param array<int> $post_ids Zaznaczone produkty.
	 */
	public function handle_bulk_actions( string $redirect, string $action, array $post_ids ): string {
		if ( ! str_starts_with( $action, 'modohome_' ) ) {
			return $redirect;
		}

		$changed = 0;
		$failed  = 0;

		if ( 'modohome_duplicate' === $action ) {
			foreach ( $post_ids as $post_id ) {
				$result = Product_Actions::duplicate( (int) $post_id );

				if ( is_wp_error( $result ) ) {
					++$failed;
				} else {
					++$changed;
				}
			}

			return add_query_arg(
				array(
					'modohome_duplicated' => $changed,
					'modohome_failed'     => $failed,
				),
				$redirect
			);
		}

		$status = substr( $action, strlen( 'modohome_set_' ) );

		if ( ! array_key_exists( $status, Product::statuses() ) ) {
			return $redirect;
		}

		foreach ( $post_ids as $post_id ) {
			$result = Product_Actions::set_availability( (int) $post_id, $status );

			if ( is_wp_error( $result ) ) {
				++$failed;
			} else {
				++$changed;
			}
		}

		return add_query_arg(
			array(
				'modohome_updated' => $changed,
				'modohome_failed'  => $failed,
			),
			$redirect
		);
	}

	/**
	 * Akcje w wierszu produktu.
	 *
	 * @param array<string,string> $actions Akcje.
	 * @param \WP_Post             $post    Produkt.
	 *
	 * @return array<string,string>
	 */
	public function row_actions( array $actions, \WP_Post $post ): array {
		if ( Post_Type::SLUG !== $post->post_type ) {
			return $actions;
		}

		if ( current_user_can( 'edit_modohome_product', $post->ID ) ) {
			if ( Product::STATUS_SOLD !== Product::get_availability( $post->ID ) ) {
				$url = wp_nonce_url(
					admin_url( 'admin.php?action=modohome_catalog_mark_sold&post=' . $post->ID ),
					'modohome_catalog_mark_sold_' . $post->ID
				);

				$actions['modohome_mark_sold'] = sprintf(
					'<a href="%s">%s</a>',
					esc_url( $url ),
					esc_html__( 'Oznacz jako sprzedany', 'modohome-katalog-produktow' )
				);
			}

			$duplicate_url = wp_nonce_url(
				admin_url( 'admin.php?action=modohome_catalog_duplicate&post=' . $post->ID ),
				'modohome_catalog_duplicate_' . $post->ID
			);

			$actions['modohome_duplicate'] = sprintf(
				'<a href="%s">%s</a>',
				esc_url( $duplicate_url ),
				esc_html__( 'Duplikuj produkt', 'modohome-katalog-produktow' )
			);
		}

		return $actions;
	}

	/**
	 * Obsługa akcji „Oznacz jako sprzedany”.
	 */
	public function handle_mark_sold(): void {
		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;

		check_admin_referer( 'modohome_catalog_mark_sold_' . $post_id );

		$result = Product_Actions::set_availability( $post_id, Product::STATUS_SOLD );

		$args = is_wp_error( $result )
			? array( 'modohome_failed' => 1 )
			: array( 'modohome_updated' => 1 );

		wp_safe_redirect( add_query_arg( $args, admin_url( 'edit.php?post_type=' . Post_Type::SLUG ) ) );
		exit;
	}

	/**
	 * Obsługa akcji „Duplikuj produkt”.
	 */
	public function handle_duplicate(): void {
		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;

		check_admin_referer( 'modohome_catalog_duplicate_' . $post_id );

		$new_id = Product_Actions::duplicate( $post_id );

		if ( is_wp_error( $new_id ) ) {
			wp_safe_redirect( add_query_arg( array( 'modohome_failed' => 1 ), admin_url( 'edit.php?post_type=' . Post_Type::SLUG ) ) );
			exit;
		}

		wp_safe_redirect( admin_url( 'post.php?post=' . $new_id . '&action=edit' ) );
		exit;
	}

	/**
	 * Komunikaty po akcjach.
	 */
	public function render_notices(): void {
		$screen = get_current_screen();

		if ( ! $screen instanceof \WP_Screen || Post_Type::SLUG !== $screen->post_type ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- komunikaty są tylko informacyjne.
		$updated    = isset( $_GET['modohome_updated'] ) ? absint( $_GET['modohome_updated'] ) : 0;
		$duplicated = isset( $_GET['modohome_duplicated'] ) ? absint( $_GET['modohome_duplicated'] ) : 0;
		$failed     = isset( $_GET['modohome_failed'] ) ? absint( $_GET['modohome_failed'] ) : 0;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( $updated > 0 ) {
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: %d: liczba produktów. */
						_n( 'Zaktualizowano %d produkt.', 'Zaktualizowano %d produktów.', $updated, 'modohome-katalog-produktow' ),
						$updated
					)
				)
			);
		}

		if ( $duplicated > 0 ) {
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: %d: liczba produktów. */
						_n( 'Zduplikowano %d produkt.', 'Zduplikowano %d produktów.', $duplicated, 'modohome-katalog-produktow' ),
						$duplicated
					)
				)
			);
		}

		if ( $failed > 0 ) {
			printf(
				'<div class="notice notice-error is-dismissible"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: %d: liczba produktów. */
						_n( 'Nie udało się zmienić %d produktu — brak uprawnień.', 'Nie udało się zmienić %d produktów — brak uprawnień.', $failed, 'modohome-katalog-produktow' ),
						$failed
					)
				)
			);
		}
	}
}
