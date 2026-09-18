<?php
/**
 * Zawartość okna modalnego produktu.
 *
 * @package MODOhome\Catalog
 *
 * @var array<string,mixed> $product Dane produktu.
 * @var \WP_Post            $post    Produkt.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use MODOhome\Catalog\Settings;

$modohome_images = array();

if ( '' !== $product['image'] ) {
	$modohome_images[] = array(
		'url' => (string) $product['image'],
		'alt' => (string) $product['title'],
	);
}

foreach ( (array) $product['gallery'] as $modohome_item ) {
	$modohome_images[] = array(
		'url' => (string) $modohome_item['url'],
		'alt' => '' !== (string) $modohome_item['alt'] ? (string) $modohome_item['alt'] : (string) $product['title'],
	);
}
?>
<div class="modohome-catalog-modal-layout">
	<div class="modohome-catalog-modal-gallery" data-modohome-gallery>
		<?php if ( ! empty( $modohome_images ) ) : ?>
			<div class="modohome-catalog-modal-stage">
				<img
					class="modohome-catalog-modal-image"
					data-modohome-gallery-main
					src="<?php echo esc_url( $modohome_images[0]['url'] ); ?>"
					alt="<?php echo esc_attr( $modohome_images[0]['alt'] ); ?>"
				/>

				<?php if ( count( $modohome_images ) > 1 ) : ?>
					<button type="button" class="modohome-catalog-gallery-nav modohome-catalog-gallery-nav--prev" data-modohome-gallery-prev aria-label="<?php esc_attr_e( 'Poprzednie zdjęcie', 'modohome-katalog-produktow' ); ?>">&#8249;</button>
					<button type="button" class="modohome-catalog-gallery-nav modohome-catalog-gallery-nav--next" data-modohome-gallery-next aria-label="<?php esc_attr_e( 'Następne zdjęcie', 'modohome-katalog-produktow' ); ?>">&#8250;</button>
				<?php endif; ?>
			</div>

			<?php if ( count( $modohome_images ) > 1 ) : ?>
				<div class="modohome-catalog-modal-thumbs">
					<?php foreach ( $modohome_images as $modohome_index => $modohome_image ) : ?>
						<button
							type="button"
							class="modohome-catalog-modal-thumb<?php echo 0 === $modohome_index ? ' is-active' : ''; ?>"
							data-modohome-gallery-thumb="<?php echo esc_attr( (string) $modohome_index ); ?>"
							data-full="<?php echo esc_url( $modohome_image['url'] ); ?>"
							aria-label="<?php echo esc_attr( sprintf( /* translators: %d: numer zdjęcia. */ __( 'Zdjęcie %d', 'modohome-katalog-produktow' ), $modohome_index + 1 ) ); ?>"
						>
							<img src="<?php echo esc_url( $modohome_image['url'] ); ?>" alt="" loading="lazy" />
						</button>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		<?php else : ?>
			<div class="modohome-catalog-modal-stage modohome-catalog-modal-stage--empty" aria-hidden="true"></div>
		<?php endif; ?>
	</div>

	<div class="modohome-catalog-modal-details">
		<?php if ( ! empty( $product['categories'] ) ) : ?>
			<p class="modohome-catalog-modal-category">
				<?php
				echo esc_html(
					implode(
						', ',
						array_map(
							static fn( array $modohome_term ): string => (string) $modohome_term['name'],
							(array) $product['categories']
						)
					)
				);
				?>
			</p>
		<?php endif; ?>

		<h2 class="modohome-catalog-modal-title" id="modohome-catalog-modal-title">
			<?php echo esc_html( (string) $product['title'] ); ?>
		</h2>

		<p class="modohome-catalog-modal-prices">
			<span class="modohome-catalog-price"><?php echo esc_html( (string) $product['price_formatted'] ); ?></span>

			<?php if ( '' !== $product['old_price_formatted'] ) : ?>
				<span class="modohome-catalog-price-old"><?php echo esc_html( (string) $product['old_price_formatted'] ); ?></span>
			<?php endif; ?>
		</p>

		<p class="modohome-catalog-modal-availability">
			<span class="modohome-catalog-status-dot modohome-catalog-status-dot--<?php echo esc_attr( (string) $product['availability'] ); ?>" aria-hidden="true"></span>
			<?php echo esc_html( (string) $product['availability_label'] ); ?>
		</p>

		<?php if ( '' !== $product['badge_label'] ) : ?>
			<p class="modohome-catalog-modal-badge">
				<span class="modohome-catalog-badge modohome-catalog-badge--<?php echo esc_attr( (string) $product['badge'] ); ?>">
					<?php echo esc_html( (string) $product['badge_label'] ); ?>
				</span>
			</p>
		<?php endif; ?>

		<?php if ( '' !== $product['description'] ) : ?>
			<div class="modohome-catalog-modal-description">
				<?php echo wp_kses_post( wpautop( (string) $product['description'] ) ); ?>
			</div>
		<?php endif; ?>

		<?php if ( Settings::bool( 'show_date' ) ) : ?>
			<p class="modohome-catalog-modal-date">
				<?php esc_html_e( 'Dodano:', 'modohome-katalog-produktow' ); ?>
				<time datetime="<?php echo esc_attr( (string) $product['date_iso'] ); ?>"><?php echo esc_html( (string) $product['date'] ); ?></time>
			</p>
		<?php endif; ?>
	</div>
</div>
