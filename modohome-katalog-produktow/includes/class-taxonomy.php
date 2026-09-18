<?php
/**
 * Taksonomia „Kategorie produktów”.
 *
 * @package MODOhome\Catalog
 */

declare( strict_types = 1 );

namespace MODOhome\Catalog;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Hierarchiczna taksonomia z własną kolejnością wyświetlania.
 */
class Taxonomy {

	public const SLUG      = 'modohome_product_category';
	public const META_ORDER = 'modohome_catalog_term_order';

	/**
	 * Podpina rejestrację i pola kolejności.
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_taxonomy' ), 5 );

		add_action( self::SLUG . '_add_form_fields', array( $this, 'render_add_order_field' ) );
		add_action( self::SLUG . '_edit_form_fields', array( $this, 'render_edit_order_field' ) );
		add_action( 'created_' . self::SLUG, array( $this, 'save_order_field' ) );
		add_action( 'edited_' . self::SLUG, array( $this, 'save_order_field' ) );

		add_filter( 'manage_edit-' . self::SLUG . '_columns', array( $this, 'add_order_column' ) );
		add_filter( 'manage_' . self::SLUG . '_custom_column', array( $this, 'render_order_column' ), 10, 3 );
	}

	/**
	 * Rejestruje taksonomię.
	 */
	public function register_taxonomy(): void {
		$public_single = Settings::bool( 'enable_single_pages' );

		$labels = array(
			'name'              => __( 'Kategorie produktów', 'modohome-katalog-produktow' ),
			'singular_name'     => __( 'Kategoria produktu', 'modohome-katalog-produktow' ),
			'menu_name'         => __( 'Kategorie', 'modohome-katalog-produktow' ),
			'all_items'         => __( 'Wszystkie kategorie', 'modohome-katalog-produktow' ),
			'edit_item'         => __( 'Edytuj kategorię', 'modohome-katalog-produktow' ),
			'update_item'       => __( 'Zaktualizuj kategorię', 'modohome-katalog-produktow' ),
			'add_new_item'      => __( 'Dodaj nową kategorię', 'modohome-katalog-produktow' ),
			'new_item_name'     => __( 'Nazwa nowej kategorii', 'modohome-katalog-produktow' ),
			'parent_item'       => __( 'Kategoria nadrzędna', 'modohome-katalog-produktow' ),
			'parent_item_colon' => __( 'Kategoria nadrzędna:', 'modohome-katalog-produktow' ),
			'search_items'      => __( 'Szukaj kategorii', 'modohome-katalog-produktow' ),
			'not_found'         => __( 'Nie znaleziono kategorii.', 'modohome-katalog-produktow' ),
			'back_to_items'     => __( '← Wróć do kategorii', 'modohome-katalog-produktow' ),
		);

		register_taxonomy(
			self::SLUG,
			array( Post_Type::SLUG ),
			array(
				'labels'            => $labels,
				'hierarchical'      => true,
				'public'            => $public_single,
				'publicly_queryable' => $public_single,
				'show_ui'           => true,
				'show_admin_column' => false,
				'show_in_nav_menus' => $public_single,
				'show_in_rest'      => false,
				'show_tagcloud'     => false,
				'query_var'         => $public_single,
				'rewrite'           => $public_single ? array(
					'slug'         => 'kategoria-produktu',
					'hierarchical' => true,
					'with_front'   => false,
				) : false,
				'capabilities'      => Roles::taxonomy_caps(),
			)
		);

		register_term_meta(
			self::SLUG,
			self::META_ORDER,
			array(
				'type'              => 'integer',
				'single'            => true,
				'show_in_rest'      => false,
				'sanitize_callback' => static fn( $value ): int => max( 0, (int) $value ),
				'auth_callback'     => static fn(): bool => current_user_can( Roles::CAP_EDIT_TERMS ),
			)
		);
	}

	/**
	 * Pole kolejności na formularzu dodawania kategorii.
	 */
	public function render_add_order_field(): void {
		?>
		<div class="form-field">
			<label for="modohome_catalog_term_order"><?php esc_html_e( 'Kolejność', 'modohome-katalog-produktow' ); ?></label>
			<input type="number" min="0" step="1" name="<?php echo esc_attr( self::META_ORDER ); ?>" id="modohome_catalog_term_order" value="0" />
			<p><?php esc_html_e( 'Niższa liczba oznacza wcześniejszą pozycję na liście kategorii.', 'modohome-katalog-produktow' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Pole kolejności na formularzu edycji kategorii.
	 *
	 * @param \WP_Term $term Edytowana kategoria.
	 */
	public function render_edit_order_field( \WP_Term $term ): void {
		$order = (int) get_term_meta( $term->term_id, self::META_ORDER, true );
		?>
		<tr class="form-field">
			<th scope="row"><label for="modohome_catalog_term_order"><?php esc_html_e( 'Kolejność', 'modohome-katalog-produktow' ); ?></label></th>
			<td>
				<input type="number" min="0" step="1" name="<?php echo esc_attr( self::META_ORDER ); ?>" id="modohome_catalog_term_order" value="<?php echo esc_attr( (string) $order ); ?>" />
				<p class="description"><?php esc_html_e( 'Niższa liczba oznacza wcześniejszą pozycję na liście kategorii.', 'modohome-katalog-produktow' ); ?></p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Zapis pola kolejności.
	 *
	 * @param int $term_id Identyfikator kategorii.
	 */
	public function save_order_field( int $term_id ): void {
		if ( ! current_user_can( Roles::CAP_EDIT_TERMS ) ) {
			return;
		}

		// Nonce weryfikuje rdzeń WordPressa na ekranie taksonomii.
		if ( ! isset( $_POST[ self::META_ORDER ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}

		$order = max( 0, (int) wp_unslash( $_POST[ self::META_ORDER ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		update_term_meta( $term_id, self::META_ORDER, $order );
	}

	/**
	 * Kolumna kolejności na liście kategorii.
	 *
	 * @param array<string,string> $columns Kolumny.
	 *
	 * @return array<string,string>
	 */
	public function add_order_column( array $columns ): array {
		$columns['modohome_order'] = __( 'Kolejność', 'modohome-katalog-produktow' );

		return $columns;
	}

	/**
	 * Zawartość kolumny kolejności.
	 *
	 * @param string $content Dotychczasowa treść.
	 * @param string $column  Nazwa kolumny.
	 * @param int    $term_id Identyfikator kategorii.
	 */
	public function render_order_column( string $content, string $column, int $term_id ): string {
		if ( 'modohome_order' !== $column ) {
			return $content;
		}

		return esc_html( (string) (int) get_term_meta( $term_id, self::META_ORDER, true ) );
	}

	/**
	 * Zwraca kategorie uporządkowane według pola kolejności, a następnie nazwy.
	 *
	 * @param bool $hide_empty Czy pomijać puste kategorie.
	 *
	 * @return array<int,\WP_Term>
	 */
	public static function get_ordered_terms( bool $hide_empty = false ): array {
		$terms = get_terms(
			array(
				'taxonomy'   => self::SLUG,
				'hide_empty' => $hide_empty,
			)
		);

		if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
			return array();
		}

		usort(
			$terms,
			static function ( \WP_Term $a, \WP_Term $b ): int {
				$order_a = (int) get_term_meta( $a->term_id, self::META_ORDER, true );
				$order_b = (int) get_term_meta( $b->term_id, self::META_ORDER, true );

				if ( $order_a === $order_b ) {
					return strcasecmp( $a->name, $b->name );
				}

				return $order_a <=> $order_b;
			}
		);

		return $terms;
	}
}
