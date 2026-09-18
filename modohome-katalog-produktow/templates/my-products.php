<?php
/**
 * Mobilny panel „Moje produkty”.
 *
 * @package MODOhome\Catalog
 *
 * @var \WP_Query            $query    Produkty zalogowanego pracownika.
 * @var array<string,string> $statuses Dostępne statusy.
 * @var array<int,\WP_Term>  $terms    Kategorie.
 * @var string               $nonce    Nonce operacji.
 * @var string               $heading  Nagłówek panelu.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use MODOhome\Catalog\Product;
?>
<div class="modohome-catalog-mine" data-modohome-my-products data-nonce="<?php echo esc_attr( $nonce ); ?>">
	<h2 class="modohome-catalog-form-heading"><?php echo esc_html( $heading ); ?></h2>

	<div class="modohome-catalog-message" data-modohome-message role="status" aria-live="polite" hidden></div>

	<?php if ( ! $query->have_posts() ) : ?>
		<p class="modohome-catalog-empty"><?php esc_html_e( 'Nie masz jeszcze żadnych produktów.', 'modohome-katalog-produktow' ); ?></p>
	<?php else : ?>
		<ul class="modohome-catalog-mine-list">
			<?php foreach ( $query->posts as $modohome_post ) : ?>
				<?php
				if ( ! $modohome_post instanceof WP_Post ) {
					continue;
				}

				$modohome_data   = Product::to_array( $modohome_post );
				$modohome_status = (string) $modohome_data['availability'];
				$modohome_row_id = 'modohome-mine-' . $modohome_data['id'];
				?>
				<li class="modohome-catalog-mine-item" data-modohome-item data-product-id="<?php echo esc_attr( (string) $modohome_data['id'] ); ?>">
					<div class="modohome-catalog-mine-summary">
						<?php if ( '' !== $modohome_data['thumbnail'] ) : ?>
							<img class="modohome-catalog-mine-thumb" data-modohome-item-thumb src="<?php echo esc_url( (string) $modohome_data['thumbnail'] ); ?>" alt="" loading="lazy" />
						<?php else : ?>
							<span class="modohome-catalog-mine-thumb modohome-catalog-mine-thumb--empty" aria-hidden="true"></span>
						<?php endif; ?>

						<div class="modohome-catalog-mine-meta">
							<span class="modohome-catalog-mine-name" data-modohome-item-name><?php echo esc_html( (string) $modohome_data['title'] ); ?></span>
							<span class="modohome-catalog-mine-price" data-modohome-item-price><?php echo esc_html( (string) $modohome_data['price_formatted'] ); ?></span>
							<span class="modohome-catalog-mine-status modohome-catalog-mine-status--<?php echo esc_attr( $modohome_status ); ?>" data-modohome-item-status>
								<?php echo esc_html( (string) $modohome_data['availability_label'] ); ?>
							</span>
							<?php if ( 'publish' !== $modohome_post->post_status ) : ?>
								<span class="modohome-catalog-mine-pending"><?php esc_html_e( 'Czeka na zatwierdzenie', 'modohome-katalog-produktow' ); ?></span>
							<?php endif; ?>
						</div>

						<button
							type="button"
							class="modohome-catalog-mine-toggle"
							data-modohome-toggle
							aria-expanded="false"
							aria-controls="<?php echo esc_attr( $modohome_row_id ); ?>"
						>
							<?php esc_html_e( 'Edytuj', 'modohome-katalog-produktow' ); ?>
						</button>
					</div>

					<div class="modohome-catalog-mine-editor" id="<?php echo esc_attr( $modohome_row_id ); ?>" hidden>
						<form class="modohome-catalog-form modohome-catalog-form--compact" data-modohome-edit-form enctype="multipart/form-data" novalidate>
							<input type="hidden" name="nonce" value="<?php echo esc_attr( $nonce ); ?>" />
							<input type="hidden" name="action" value="modohome_catalog_update_product" />
							<input type="hidden" name="product_id" value="<?php echo esc_attr( (string) $modohome_data['id'] ); ?>" />

							<div class="modohome-catalog-field">
								<label class="modohome-catalog-label" for="<?php echo esc_attr( $modohome_row_id ); ?>-title">
									<?php esc_html_e( 'Nazwa produktu', 'modohome-katalog-produktow' ); ?>
								</label>
								<input type="text" id="<?php echo esc_attr( $modohome_row_id ); ?>-title" name="title" class="modohome-catalog-input" value="<?php echo esc_attr( (string) $modohome_data['title'] ); ?>" required />
							</div>

							<div class="modohome-catalog-field">
								<label class="modohome-catalog-label" for="<?php echo esc_attr( $modohome_row_id ); ?>-price">
									<?php esc_html_e( 'Cena', 'modohome-katalog-produktow' ); ?>
								</label>
								<input type="text" inputmode="decimal" id="<?php echo esc_attr( $modohome_row_id ); ?>-price" name="price" class="modohome-catalog-input" value="<?php echo esc_attr( null !== $modohome_data['price'] ? (string) $modohome_data['price'] : '' ); ?>" required />
							</div>

							<div class="modohome-catalog-field">
								<label class="modohome-catalog-label" for="<?php echo esc_attr( $modohome_row_id ); ?>-old-price">
									<?php esc_html_e( 'Cena poprzednia', 'modohome-katalog-produktow' ); ?>
								</label>
								<input type="text" inputmode="decimal" id="<?php echo esc_attr( $modohome_row_id ); ?>-old-price" name="old_price" class="modohome-catalog-input" value="<?php echo esc_attr( null !== $modohome_data['old_price'] ? (string) $modohome_data['old_price'] : '' ); ?>" />
							</div>

							<div class="modohome-catalog-field">
								<label class="modohome-catalog-label" for="<?php echo esc_attr( $modohome_row_id ); ?>-description">
									<?php esc_html_e( 'Krótki opis', 'modohome-katalog-produktow' ); ?>
								</label>
								<textarea id="<?php echo esc_attr( $modohome_row_id ); ?>-description" name="description" class="modohome-catalog-textarea" rows="3"><?php echo esc_textarea( (string) $modohome_data['description'] ); ?></textarea>
							</div>

							<div class="modohome-catalog-field">
								<label class="modohome-catalog-label" for="<?php echo esc_attr( $modohome_row_id ); ?>-status">
									<?php esc_html_e( 'Dostępność', 'modohome-katalog-produktow' ); ?>
								</label>
								<select id="<?php echo esc_attr( $modohome_row_id ); ?>-status" name="availability" class="modohome-catalog-select">
									<?php foreach ( $statuses as $modohome_key => $modohome_label ) : ?>
										<option value="<?php echo esc_attr( $modohome_key ); ?>" <?php selected( $modohome_status, $modohome_key ); ?>>
											<?php echo esc_html( $modohome_label ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</div>

							<div class="modohome-catalog-field">
								<label class="modohome-catalog-label" for="<?php echo esc_attr( $modohome_row_id ); ?>-image">
									<?php esc_html_e( 'Zmień zdjęcie', 'modohome-katalog-produktow' ); ?>
								</label>
								<input type="file" id="<?php echo esc_attr( $modohome_row_id ); ?>-image" name="product_image" accept="image/*" capture="environment" class="modohome-catalog-input" />
							</div>

							<button type="submit" class="modohome-catalog-button modohome-catalog-button--submit"><?php esc_html_e( 'Zapisz zmiany', 'modohome-katalog-produktow' ); ?></button>
						</form>

						<div class="modohome-catalog-mine-actions">
							<button type="button" class="modohome-catalog-button modohome-catalog-button--ghost" data-modohome-mark-sold>
								<?php esc_html_e( 'Oznacz jako sprzedany', 'modohome-katalog-produktow' ); ?>
							</button>
							<button type="button" class="modohome-catalog-button modohome-catalog-button--ghost" data-modohome-duplicate>
								<?php esc_html_e( 'Duplikuj', 'modohome-katalog-produktow' ); ?>
							</button>
							<button type="button" class="modohome-catalog-button modohome-catalog-button--danger" data-modohome-trash>
								<?php esc_html_e( 'Do kosza', 'modohome-katalog-produktow' ); ?>
							</button>
						</div>
					</div>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>

	<?php wp_reset_postdata(); ?>
</div>
