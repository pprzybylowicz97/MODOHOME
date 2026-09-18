<?php
/**
 * Formularz dodawania produktu z telefonu.
 *
 * @package MODOhome\Catalog
 */

declare( strict_types = 1 );

namespace MODOhome\Catalog\Frontend;

use MODOhome\Catalog\Ajax;
use MODOhome\Catalog\Image_Handler;
use MODOhome\Catalog\Plugin;
use MODOhome\Catalog\Product;
use MODOhome\Catalog\Roles;
use MODOhome\Catalog\Settings;
use MODOhome\Catalog\Taxonomy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Formularz jest dostępny wyłącznie dla zalogowanych osób z uprawnieniem
 * edit_modohome_products.
 */
class Product_Form {

	/**
	 * Renderuje formularz albo komunikat o braku dostępu.
	 *
	 * @param array<string,mixed> $atts Atrybuty shortcode’u.
	 */
	public function render( array $atts ): string {
		if ( ! is_user_logged_in() ) {
			return $this->notice(
				__( 'Aby dodać produkt, zaloguj się.', 'modohome-katalog-produktow' ),
				wp_login_url( (string) get_permalink() ),
				__( 'Zaloguj się', 'modohome-katalog-produktow' )
			);
		}

		if ( ! Roles::can_submit() ) {
			return $this->notice( __( 'Twoje konto nie ma uprawnień do dodawania produktów.', 'modohome-katalog-produktow' ) );
		}

		$terms = Taxonomy::get_ordered_terms( false );

		return Plugin::render_template(
			'product-form',
			array(
				'terms'          => $terms,
				'badges'         => Product::badges(),
				'statuses'       => $this->available_statuses(),
				'nonce'          => wp_create_nonce( Ajax::NONCE_PRIVATE ),
				'max_size_text'  => size_format( Image_Handler::max_upload_bytes() ),
				'heading'        => '' !== (string) $atts['heading'] ? (string) $atts['heading'] : __( 'Dodaj produkt', 'modohome-katalog-produktow' ),
				'needs_approval' => ! Settings::bool( 'worker_autopublish' ) && ! current_user_can( 'publish_modohome_products' ),
				'can_add_terms'  => current_user_can( Roles::CAP_MANAGE_TERMS ),
			)
		);
	}

	/**
	 * Statusy dostępne w formularzu. Pracownik nie ustawia statusu „Ukryty”
	 * przy dodawaniu — od tego jest panel „Moje produkty”.
	 *
	 * @return array<string,string>
	 */
	private function available_statuses(): array {
		$statuses = Product::statuses();

		unset( $statuses[ Product::STATUS_HIDDEN ] );

		return $statuses;
	}

	/**
	 * Prosty komunikat zamiast formularza.
	 *
	 * @param string $message    Treść komunikatu.
	 * @param string $link       Opcjonalny odnośnik.
	 * @param string $link_label Etykieta odnośnika.
	 */
	private function notice( string $message, string $link = '', string $link_label = '' ): string {
		$html = '<div class="modohome-catalog-notice modohome-catalog-notice--info">';
		$html .= '<p>' . esc_html( $message ) . '</p>';

		if ( '' !== $link ) {
			$html .= '<p><a class="modohome-catalog-button" href="' . esc_url( $link ) . '">' . esc_html( $link_label ) . '</a></p>';
		}

		$html .= '</div>';

		return $html;
	}
}
