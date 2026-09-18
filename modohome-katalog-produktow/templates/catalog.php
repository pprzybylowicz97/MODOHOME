<?php
/**
 * Szablon katalogu produktów.
 *
 * Motyw może nadpisać ten plik w katalogu modohome-katalog-produktow/catalog.php.
 *
 * @package MODOhome\Catalog
 *
 * @var string                      $instance_id          Unikalny identyfikator instancji.
 * @var \WP_Query                   $query                Zapytanie o produkty.
 * @var int                         $columns              Liczba kolumn na komputerze.
 * @var string                      $heading              Nagłówek katalogu.
 * @var string                      $intro                Tekst nad katalogiem.
 * @var bool                        $show_filters         Czy pokazać filtry.
 * @var bool                        $show_category_filter Czy pokazać filtr kategorii.
 * @var bool                        $show_search          Czy pokazać wyszukiwarkę.
 * @var string                      $orderby              Domyślne sortowanie.
 * @var array<int,\WP_Term>         $terms                Kategorie do filtrowania.
 * @var array<string,mixed>         $config               Konfiguracja dla JavaScriptu.
 * @var int                         $total                Liczba znalezionych produktów.
 * @var int                         $max_pages            Liczba stron.
 * @var int                         $limit                Limit z shortcode’u.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use MODOhome\Catalog\Frontend\Catalog;
use MODOhome\Catalog\Query;
use MODOhome\Catalog\Settings;

$modohome_show_counts = Settings::bool( 'show_category_counts' );
$modohome_has_more    = $max_pages > 1;

if ( $limit > 0 && $total >= $limit ) {
	$modohome_has_more = count( $query->posts ) < $limit;
}
?>
<div
	class="modohome-catalog"
	id="<?php echo esc_attr( $instance_id ); ?>"
	data-modohome-catalog
	data-config="<?php echo esc_attr( (string) wp_json_encode( $config ) ); ?>"
	style="--modohome-catalog-cols-desktop:<?php echo esc_attr( (string) $columns ); ?>"
>
	<?php if ( '' !== $heading ) : ?>
		<h2 class="modohome-catalog-heading"><?php echo esc_html( $heading ); ?></h2>
	<?php endif; ?>

	<?php if ( '' !== trim( $intro ) ) : ?>
		<div class="modohome-catalog-intro"><?php echo wp_kses_post( wpautop( $intro ) ); ?></div>
	<?php endif; ?>

	<?php if ( $show_search || $show_filters ) : ?>
		<div class="modohome-catalog-toolbar">
			<?php if ( $show_search ) : ?>
				<div class="modohome-catalog-search">
					<label class="screen-reader-text" for="<?php echo esc_attr( $instance_id ); ?>-search">
						<?php esc_html_e( 'Szukaj produktów', 'modohome-katalog-produktow' ); ?>
					</label>
					<input
						type="search"
						id="<?php echo esc_attr( $instance_id ); ?>-search"
						class="modohome-catalog-search-input"
						data-modohome-search
						placeholder="<?php esc_attr_e( 'Szukaj produktu…', 'modohome-katalog-produktow' ); ?>"
						autocomplete="off"
					/>
				</div>
			<?php endif; ?>

			<?php if ( $show_filters ) : ?>
				<div class="modohome-catalog-sort">
					<label class="screen-reader-text" for="<?php echo esc_attr( $instance_id ); ?>-sort">
						<?php esc_html_e( 'Sortowanie', 'modohome-katalog-produktow' ); ?>
					</label>
					<select id="<?php echo esc_attr( $instance_id ); ?>-sort" class="modohome-catalog-select" data-modohome-sort>
						<option value="date" <?php selected( $orderby, 'date' ); ?>><?php esc_html_e( 'Najnowsze', 'modohome-katalog-produktow' ); ?></option>
						<option value="price_asc" <?php selected( $orderby, 'price_asc' ); ?>><?php esc_html_e( 'Cena rosnąco', 'modohome-katalog-produktow' ); ?></option>
						<option value="price_desc" <?php selected( $orderby, 'price_desc' ); ?>><?php esc_html_e( 'Cena malejąco', 'modohome-katalog-produktow' ); ?></option>
					</select>
				</div>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<?php if ( $show_category_filter && ! empty( $terms ) ) : ?>
		<div class="modohome-catalog-filterbar">
		<div class="modohome-catalog-filters" role="group" aria-label="<?php esc_attr_e( 'Filtruj po kategorii', 'modohome-katalog-produktow' ); ?>">
			<button type="button" class="modohome-catalog-filter is-active" data-modohome-filter="" aria-pressed="true">
				<?php esc_html_e( 'Wszystkie', 'modohome-katalog-produktow' ); ?>
			</button>

			<?php foreach ( $terms as $modohome_term ) : ?>
				<?php
				$modohome_count = $modohome_show_counts ? Query::count_in_category( $modohome_term->term_id ) : 0;

				if ( $modohome_show_counts && 0 === $modohome_count ) {
					continue;
				}
				?>
				<button
					type="button"
					class="modohome-catalog-filter<?php echo $modohome_term->parent ? ' modohome-catalog-filter--child' : ''; ?>"
					data-modohome-filter="<?php echo esc_attr( (string) $modohome_term->term_id ); ?>"
					aria-pressed="false"
				>
					<?php echo esc_html( $modohome_term->name ); ?>
					<?php if ( $modohome_show_counts ) : ?>
						<span class="modohome-catalog-filter-count"><?php echo esc_html( (string) $modohome_count ); ?></span>
					<?php endif; ?>
				</button>
			<?php endforeach; ?>
			</div>

			<p class="modohome-catalog-count" data-modohome-count>
				<?php
				// JavaScript podmienia całą treść po filtrowaniu, więc trzymamy tu czysty tekst.
				echo esc_html(
					sprintf(
						/* translators: %s: liczba produktów. */
						_n( '%s produkt', '%s produktów', $total, 'modohome-katalog-produktow' ),
						number_format_i18n( $total )
					)
				);
				?>
			</p>
		</div>
	<?php endif; ?>

	<div class="modohome-catalog-status" data-modohome-status role="status" aria-live="polite"></div>

	<div class="modohome-catalog-grid" data-modohome-grid>
		<?php
		foreach ( $query->posts as $modohome_post ) {
			if ( $modohome_post instanceof WP_Post ) {
				echo Catalog::render_card( $modohome_post ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
		}

		wp_reset_postdata();
		?>
	</div>

	<p class="modohome-catalog-empty" data-modohome-empty <?php echo empty( $query->posts ) ? '' : 'hidden'; ?>>
		<?php esc_html_e( 'Nie znaleziono produktów.', 'modohome-katalog-produktow' ); ?>
	</p>

	<?php if ( $use_load_more ) : ?>
		<div class="modohome-catalog-more">
			<button
				type="button"
				class="modohome-catalog-button modohome-catalog-button--more"
				data-modohome-load-more
				<?php echo $modohome_has_more ? '' : 'hidden'; ?>
			>
				<?php esc_html_e( 'Pokaż więcej', 'modohome-katalog-produktow' ); ?>
			</button>
		</div>
	<?php else : ?>
		<nav class="modohome-catalog-pagination" data-modohome-pagination aria-label="<?php esc_attr_e( 'Strony katalogu', 'modohome-katalog-produktow' ); ?>">
			<?php echo Catalog::render_pagination( 1, $max_pages ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</nav>
	<?php endif; ?>
</div>
