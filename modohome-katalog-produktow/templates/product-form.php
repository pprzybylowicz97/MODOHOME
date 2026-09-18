<?php
/**
 * Formularz dodawania produktu — układ jednokolumnowy, zoptymalizowany pod telefon.
 *
 * @package MODOhome\Catalog
 *
 * @var array<int,\WP_Term> $terms          Kategorie.
 * @var array<string,string> $badges        Dostępne etykiety.
 * @var array<string,string> $statuses      Dostępne statusy.
 * @var string              $nonce          Nonce formularza.
 * @var string              $max_size_text  Maksymalny rozmiar pliku.
 * @var string              $heading        Nagłówek formularza.
 * @var bool                $needs_approval Czy produkt wymaga zatwierdzenia.
 * @var bool                $can_add_terms  Czy użytkownik może tworzyć kategorie.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use MODOhome\Catalog\Taxonomy;

$modohome_form_id = 'modohome-product-form-' . wp_rand( 1000, 9999 );
?>
<div class="modohome-catalog-form-wrap" data-modohome-form-wrap>

	<form
		class="modohome-catalog-form"
		id="<?php echo esc_attr( $modohome_form_id ); ?>"
		data-modohome-product-form
		method="post"
		enctype="multipart/form-data"
		novalidate
	>
		<?php wp_nonce_field( 'modohome_catalog_form', 'modohome_catalog_form_nonce' ); ?>
		<input type="hidden" name="nonce" value="<?php echo esc_attr( $nonce ); ?>" />
		<input type="hidden" name="action" value="modohome_catalog_add_product" />

		<h2 class="modohome-catalog-form-heading"><?php echo esc_html( $heading ); ?></h2>

		<?php if ( $needs_approval ) : ?>
			<p class="modohome-catalog-hint">
				<?php esc_html_e( 'Dodane produkty trafiają do zatwierdzenia przez administratora.', 'modohome-katalog-produktow' ); ?>
			</p>
		<?php endif; ?>

		<div class="modohome-catalog-message" data-modohome-message role="status" aria-live="polite" hidden></div>

		<div class="modohome-catalog-field modohome-catalog-field--photo">
			<label class="modohome-catalog-photo-button" for="<?php echo esc_attr( $modohome_form_id ); ?>-image">
				<span class="modohome-catalog-photo-icon" aria-hidden="true">
					<svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
						<path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path>
						<circle cx="12" cy="13" r="4"></circle>
					</svg>
				</span>
				<span class="modohome-catalog-photo-label">
					<?php esc_html_e( 'Dodaj zdjęcie lub zrób zdjęcie', 'modohome-katalog-produktow' ); ?>
				</span>
			</label>

			<input
				type="file"
				id="<?php echo esc_attr( $modohome_form_id ); ?>-image"
				name="product_image"
				accept="image/*"
				capture="environment"
				class="modohome-catalog-file-input"
				data-modohome-file
				required
			/>

			<p class="modohome-catalog-hint">
				<?php
				printf(
					/* translators: %s: maksymalny rozmiar pliku. */
					esc_html__( 'JPG, PNG lub WebP. Maksymalnie %s.', 'modohome-katalog-produktow' ),
					esc_html( $max_size_text )
				);
				?>
			</p>

			<div class="modohome-catalog-preview" data-modohome-preview hidden>
				<img class="modohome-catalog-preview-image" data-modohome-preview-image src="" alt="" />
				<button type="button" class="modohome-catalog-preview-remove" data-modohome-preview-remove>
					<?php esc_html_e( 'Usuń zdjęcie', 'modohome-katalog-produktow' ); ?>
				</button>
			</div>

			<p class="modohome-catalog-error" data-modohome-error="image" hidden></p>
		</div>

		<div class="modohome-catalog-field">
			<label class="modohome-catalog-label" for="<?php echo esc_attr( $modohome_form_id ); ?>-title">
				<?php esc_html_e( 'Nazwa produktu', 'modohome-katalog-produktow' ); ?>
				<span class="modohome-catalog-required" aria-hidden="true">*</span>
			</label>
			<input
				type="text"
				id="<?php echo esc_attr( $modohome_form_id ); ?>-title"
				name="title"
				class="modohome-catalog-input"
				autocomplete="off"
				required
			/>
			<p class="modohome-catalog-error" data-modohome-error="title" hidden></p>
		</div>

		<div class="modohome-catalog-field">
			<label class="modohome-catalog-label" for="<?php echo esc_attr( $modohome_form_id ); ?>-price">
				<?php esc_html_e( 'Cena', 'modohome-katalog-produktow' ); ?>
				<span class="modohome-catalog-required" aria-hidden="true">*</span>
			</label>
			<input
				type="text"
				inputmode="decimal"
				id="<?php echo esc_attr( $modohome_form_id ); ?>-price"
				name="price"
				class="modohome-catalog-input"
				placeholder="0"
				required
			/>
			<p class="modohome-catalog-error" data-modohome-error="price" hidden></p>
		</div>

		<div class="modohome-catalog-field">
			<label class="modohome-catalog-label" for="<?php echo esc_attr( $modohome_form_id ); ?>-old-price">
				<?php esc_html_e( 'Cena poprzednia', 'modohome-katalog-produktow' ); ?>
				<span class="modohome-catalog-optional"><?php esc_html_e( '(opcjonalnie)', 'modohome-katalog-produktow' ); ?></span>
			</label>
			<input
				type="text"
				inputmode="decimal"
				id="<?php echo esc_attr( $modohome_form_id ); ?>-old-price"
				name="old_price"
				class="modohome-catalog-input"
				placeholder="0"
			/>
		</div>

		<div class="modohome-catalog-field">
			<label class="modohome-catalog-label" for="<?php echo esc_attr( $modohome_form_id ); ?>-category">
				<?php esc_html_e( 'Kategoria', 'modohome-katalog-produktow' ); ?>
				<span class="modohome-catalog-required" aria-hidden="true">*</span>
			</label>
			<?php
			wp_dropdown_categories(
				array(
					'taxonomy'          => Taxonomy::SLUG,
					'name'              => 'categories[]',
					'id'                => $modohome_form_id . '-category',
					'class'             => 'modohome-catalog-select',
					'hierarchical'      => true,
					'hide_empty'        => false,
					'show_option_none'  => __( '— wybierz kategorię —', 'modohome-katalog-produktow' ),
					'option_none_value' => '',
					'value_field'       => 'term_id',
					'required'          => true,
				)
			);
			?>
			<?php if ( empty( $terms ) ) : ?>
				<p class="modohome-catalog-hint">
					<?php
					if ( $can_add_terms ) {
						esc_html_e( 'Nie ma jeszcze żadnych kategorii — dodaj je w panelu WordPressa.', 'modohome-katalog-produktow' );
					} else {
						esc_html_e( 'Nie ma jeszcze żadnych kategorii. Poproś administratora o ich dodanie.', 'modohome-katalog-produktow' );
					}
					?>
				</p>
			<?php endif; ?>
			<p class="modohome-catalog-error" data-modohome-error="category" hidden></p>
		</div>

		<div class="modohome-catalog-field">
			<label class="modohome-catalog-label" for="<?php echo esc_attr( $modohome_form_id ); ?>-description">
				<?php esc_html_e( 'Krótki opis', 'modohome-katalog-produktow' ); ?>
				<span class="modohome-catalog-optional"><?php esc_html_e( '(opcjonalnie)', 'modohome-katalog-produktow' ); ?></span>
			</label>
			<textarea
				id="<?php echo esc_attr( $modohome_form_id ); ?>-description"
				name="description"
				class="modohome-catalog-textarea"
				rows="3"
			></textarea>
		</div>

		<div class="modohome-catalog-field">
			<label class="modohome-catalog-label" for="<?php echo esc_attr( $modohome_form_id ); ?>-badge">
				<?php esc_html_e( 'Etykieta', 'modohome-katalog-produktow' ); ?>
				<span class="modohome-catalog-optional"><?php esc_html_e( '(opcjonalnie)', 'modohome-katalog-produktow' ); ?></span>
			</label>
			<select id="<?php echo esc_attr( $modohome_form_id ); ?>-badge" name="badge" class="modohome-catalog-select">
				<option value=""><?php esc_html_e( '— brak —', 'modohome-katalog-produktow' ); ?></option>
				<?php foreach ( $badges as $modohome_key => $modohome_label ) : ?>
					<option value="<?php echo esc_attr( $modohome_key ); ?>"><?php echo esc_html( $modohome_label ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="modohome-catalog-field">
			<label class="modohome-catalog-label" for="<?php echo esc_attr( $modohome_form_id ); ?>-availability">
				<?php esc_html_e( 'Dostępność', 'modohome-katalog-produktow' ); ?>
			</label>
			<select id="<?php echo esc_attr( $modohome_form_id ); ?>-availability" name="availability" class="modohome-catalog-select">
				<?php foreach ( $statuses as $modohome_key => $modohome_label ) : ?>
					<option value="<?php echo esc_attr( $modohome_key ); ?>" <?php selected( 'available', $modohome_key ); ?>>
						<?php echo esc_html( $modohome_label ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>

		<button type="submit" class="modohome-catalog-button modohome-catalog-button--submit" data-modohome-submit>
			<?php esc_html_e( 'Dodaj produkt', 'modohome-katalog-produktow' ); ?>
		</button>
	</form>

	<div class="modohome-catalog-success" data-modohome-success hidden>
		<p class="modohome-catalog-success-title" data-modohome-success-message>
			<?php esc_html_e( 'Produkt został dodany', 'modohome-katalog-produktow' ); ?>
		</p>

		<div class="modohome-catalog-success-card">
			<img class="modohome-catalog-success-image" data-modohome-success-image src="" alt="" />
			<div class="modohome-catalog-success-info">
				<span class="modohome-catalog-success-name" data-modohome-success-name></span>
				<span class="modohome-catalog-success-price" data-modohome-success-price></span>
			</div>
		</div>

		<button type="button" class="modohome-catalog-button modohome-catalog-button--again" data-modohome-again>
			<?php esc_html_e( 'Dodaj kolejny produkt', 'modohome-katalog-produktow' ); ?>
		</button>
	</div>
</div>
