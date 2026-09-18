<?php
/**
 * Panel danych produktu na ekranie edycji.
 *
 * @package MODOhome\Catalog
 */

declare( strict_types = 1 );

namespace MODOhome\Catalog\Admin;

use MODOhome\Catalog\Activity_Log;
use MODOhome\Catalog\Post_Type;
use MODOhome\Catalog\Product;
use MODOhome\Catalog\Product_Actions;
use MODOhome\Catalog\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Zamiast rozsypanych pól — jeden czytelny panel z wszystkimi danymi produktu.
 */
class Metaboxes {

	private const NONCE_ACTION = 'modohome_catalog_save_product';
	private const NONCE_FIELD  = 'modohome_catalog_product_nonce';

	/**
	 * Podpina metaboksy.
	 */
	public function register(): void {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post_' . Post_Type::SLUG, array( $this, 'save' ), 10, 2 );
	}

	/**
	 * Rejestruje panel danych produktu.
	 */
	public function add_meta_boxes(): void {
		add_meta_box(
			'modohome_catalog_product_data',
			__( 'Dane produktu', 'modohome-katalog-produktow' ),
			array( $this, 'render_product_data' ),
			Post_Type::SLUG,
			'normal',
			'high'
		);

		add_meta_box(
			'modohome_catalog_product_gallery',
			__( 'Galeria zdjęć', 'modohome-katalog-produktow' ),
			array( $this, 'render_gallery' ),
			Post_Type::SLUG,
			'side',
			'low'
		);
	}

	/**
	 * Panel z ceną, opisem, etykietą i dostępnością.
	 *
	 * @param \WP_Post $post Edytowany produkt.
	 */
	public function render_product_data( \WP_Post $post ): void {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );

		$price        = (string) get_post_meta( $post->ID, Product::META_PRICE, true );
		$old_price    = (string) get_post_meta( $post->ID, Product::META_OLD_PRICE, true );
		$description  = Product::get_description( $post->ID );
		$badge        = Product::get_badge( $post->ID );
		$availability = Product::get_availability( $post->ID );
		$currency     = (string) Settings::get( 'currency', 'zł' );
		?>
		<div class="modohome-admin-panel">
			<div class="modohome-admin-grid">
				<p class="modohome-admin-field">
					<label class="modohome-admin-label" for="modohome_catalog_price">
						<?php esc_html_e( 'Cena', 'modohome-katalog-produktow' ); ?>
						<span class="modohome-admin-required">*</span>
					</label>
					<span class="modohome-admin-input-group">
						<input
							type="text"
							inputmode="decimal"
							id="modohome_catalog_price"
							name="modohome_catalog_price"
							class="modohome-admin-input"
							value="<?php echo esc_attr( $price ); ?>"
						/>
						<span class="modohome-admin-suffix"><?php echo esc_html( $currency ); ?></span>
					</span>
				</p>

				<p class="modohome-admin-field">
					<label class="modohome-admin-label" for="modohome_catalog_old_price">
						<?php esc_html_e( 'Cena poprzednia', 'modohome-katalog-produktow' ); ?>
					</label>
					<span class="modohome-admin-input-group">
						<input
							type="text"
							inputmode="decimal"
							id="modohome_catalog_old_price"
							name="modohome_catalog_old_price"
							class="modohome-admin-input"
							value="<?php echo esc_attr( $old_price ); ?>"
						/>
						<span class="modohome-admin-suffix"><?php echo esc_html( $currency ); ?></span>
					</span>
				</p>

				<p class="modohome-admin-field">
					<label class="modohome-admin-label" for="modohome_catalog_badge">
						<?php esc_html_e( 'Etykieta', 'modohome-katalog-produktow' ); ?>
					</label>
					<select id="modohome_catalog_badge" name="modohome_catalog_badge" class="modohome-admin-input">
						<option value=""><?php esc_html_e( '— brak —', 'modohome-katalog-produktow' ); ?></option>
						<?php foreach ( Product::badges() as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $badge, $key ); ?>>
								<?php echo esc_html( $label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</p>

				<p class="modohome-admin-field">
					<label class="modohome-admin-label" for="modohome_catalog_availability">
						<?php esc_html_e( 'Dostępność', 'modohome-katalog-produktow' ); ?>
					</label>
					<select id="modohome_catalog_availability" name="modohome_catalog_availability" class="modohome-admin-input">
						<?php foreach ( Product::statuses() as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $availability, $key ); ?>>
								<?php echo esc_html( $label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</p>
			</div>

			<p class="modohome-admin-field">
				<label class="modohome-admin-label" for="modohome_catalog_description">
					<?php esc_html_e( 'Krótki opis', 'modohome-katalog-produktow' ); ?>
				</label>
				<textarea
					id="modohome_catalog_description"
					name="modohome_catalog_description"
					class="modohome-admin-textarea"
					rows="4"
				><?php echo esc_textarea( $description ); ?></textarea>
			</p>

			<p class="modohome-admin-note">
				<?php esc_html_e( 'Zdjęcie główne i kategorię ustawisz w panelach po prawej stronie. Kolejność wyświetlania zmienisz w panelu „Atrybuty”.', 'modohome-katalog-produktow' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Panel galerii dodatkowych zdjęć.
	 *
	 * @param \WP_Post $post Edytowany produkt.
	 */
	public function render_gallery( \WP_Post $post ): void {
		$ids = Product::get_gallery_ids( $post->ID );
		?>
		<div class="modohome-admin-gallery" data-modohome-gallery-box>
			<input
				type="hidden"
				id="modohome_catalog_gallery"
				name="modohome_catalog_gallery"
				value="<?php echo esc_attr( implode( ',', $ids ) ); ?>"
				data-modohome-gallery-input
			/>

			<ul class="modohome-admin-gallery-list" data-modohome-gallery-list>
				<?php foreach ( $ids as $id ) : ?>
					<?php $thumb = wp_get_attachment_image_url( $id, 'thumbnail' ); ?>
					<?php if ( ! $thumb ) : ?>
						<?php continue; ?>
					<?php endif; ?>
					<li class="modohome-admin-gallery-item" data-id="<?php echo esc_attr( (string) $id ); ?>">
						<img src="<?php echo esc_url( $thumb ); ?>" alt="" />
						<button type="button" class="modohome-admin-gallery-remove" data-modohome-gallery-remove aria-label="<?php esc_attr_e( 'Usuń zdjęcie z galerii', 'modohome-katalog-produktow' ); ?>">&times;</button>
					</li>
				<?php endforeach; ?>
			</ul>

			<button type="button" class="button button-secondary" data-modohome-gallery-add>
				<?php esc_html_e( 'Dodaj zdjęcia', 'modohome-katalog-produktow' ); ?>
			</button>
		</div>
		<?php
	}

	/**
	 * Zapis pól produktu.
	 *
	 * @param int      $post_id Identyfikator produktu.
	 * @param \WP_Post $post    Produkt.
	 */
	public function save( int $post_id, \WP_Post $post ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( ! isset( $_POST[ self::NONCE_FIELD ] ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( (string) $_POST[ self::NONCE_FIELD ] ) );

		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_modohome_product', $post_id ) ) {
			return;
		}

		$price = isset( $_POST['modohome_catalog_price'] )
			? Product::sanitize_price( wp_unslash( (string) $_POST['modohome_catalog_price'] ) )
			: '';

		Product_Actions::update_price( $post_id, $price );

		$old_price = isset( $_POST['modohome_catalog_old_price'] )
			? Product::sanitize_price( wp_unslash( (string) $_POST['modohome_catalog_old_price'] ) )
			: '';

		update_post_meta( $post_id, Product::META_OLD_PRICE, $old_price );

		$description = isset( $_POST['modohome_catalog_description'] )
			? sanitize_textarea_field( wp_unslash( (string) $_POST['modohome_catalog_description'] ) )
			: '';

		update_post_meta( $post_id, Product::META_DESCRIPTION, $description );

		$badge = isset( $_POST['modohome_catalog_badge'] )
			? Product::sanitize_badge( wp_unslash( (string) $_POST['modohome_catalog_badge'] ) )
			: '';

		update_post_meta( $post_id, Product::META_BADGE, $badge );

		$availability = isset( $_POST['modohome_catalog_availability'] )
			? Product::sanitize_availability( wp_unslash( (string) $_POST['modohome_catalog_availability'] ) )
			: Product::STATUS_AVAILABLE;

		$before = Product::get_availability( $post_id );

		if ( $before !== $availability ) {
			update_post_meta( $post_id, Product::META_AVAILABILITY, $availability );

			Activity_Log::record(
				Activity_Log::ACTION_STATUS_CHANGED,
				$post_id,
				sprintf(
					/* translators: 1: poprzedni status, 2: nowy status. */
					__( 'Status: %1$s → %2$s', 'modohome-katalog-produktow' ),
					Product::status_label( $before ),
					Product::status_label( $availability )
				)
			);
		}

		$gallery = isset( $_POST['modohome_catalog_gallery'] )
			? Product::sanitize_gallery( wp_unslash( (string) $_POST['modohome_catalog_gallery'] ) )
			: '';

		update_post_meta( $post_id, Product::META_GALLERY, $gallery );

		Product_Actions::flush_counts();
	}
}
