<?php
/**
 * Rejestracja shortcode’ów wtyczki.
 *
 * @package MODOhome\Catalog
 */

declare( strict_types = 1 );

namespace MODOhome\Catalog;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Trzy shortcode’y: katalog, formularz dodawania i panel pracownika.
 */
class Shortcodes {

	private Assets $assets;

	/**
	 * @param Assets $assets Menedżer zasobów.
	 */
	public function __construct( Assets $assets ) {
		$this->assets = $assets;
	}

	/**
	 * Podpina shortcode’y.
	 */
	public function register(): void {
		add_shortcode( 'modohome_catalog', array( $this, 'render_catalog' ) );
		add_shortcode( 'modohome_product_form', array( $this, 'render_form' ) );
		add_shortcode( 'modohome_my_products', array( $this, 'render_my_products' ) );
	}

	/**
	 * [modohome_catalog]
	 *
	 * @param array<string,mixed>|string $atts Atrybuty shortcode’u.
	 */
	public function render_catalog( array|string $atts = array() ): string {
		$atts = shortcode_atts(
			array(
				'category'     => '',
				'categories'   => '',
				'limit'        => 0,
				'columns'      => 0,
				'show_filters' => '',
				'show_search'  => '',
				'orderby'      => '',
				'show_sold'    => '',
				'heading'      => '',
			),
			is_array( $atts ) ? $atts : array(),
			'modohome_catalog'
		);

		$this->assets->enqueue_catalog();

		return ( new Frontend\Catalog() )->render( $atts );
	}

	/**
	 * [modohome_product_form]
	 *
	 * @param array<string,mixed>|string $atts Atrybuty shortcode’u.
	 */
	public function render_form( array|string $atts = array() ): string {
		$atts = shortcode_atts(
			array(
				'heading' => '',
			),
			is_array( $atts ) ? $atts : array(),
			'modohome_product_form'
		);

		$this->assets->enqueue_form();

		return ( new Frontend\Product_Form() )->render( $atts );
	}

	/**
	 * [modohome_my_products]
	 *
	 * @param array<string,mixed>|string $atts Atrybuty shortcode’u.
	 */
	public function render_my_products( array|string $atts = array() ): string {
		$atts = shortcode_atts(
			array(
				'limit'   => 20,
				'heading' => '',
			),
			is_array( $atts ) ? $atts : array(),
			'modohome_my_products'
		);

		$this->assets->enqueue_form();
		$this->assets->enqueue_catalog();

		return ( new Frontend\My_Products() )->render( $atts );
	}

	/**
	 * Zamienia wartość atrybutu shortcode’u na wartość logiczną.
	 *
	 * @param mixed $value    Wartość atrybutu.
	 * @param bool  $fallback Wartość, gdy atrybut nie został podany.
	 */
	public static function bool_att( mixed $value, bool $fallback ): bool {
		if ( null === $value || '' === $value ) {
			return $fallback;
		}

		return in_array( strtolower( (string) $value ), array( 'yes', 'true', '1', 'on' ), true );
	}
}
