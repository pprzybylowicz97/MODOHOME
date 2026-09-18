<?php
/**
 * Szablon karty produktu.
 *
 * @package MODOhome\Catalog
 *
 * @var array<string,mixed> $product Dane produktu.
 * @var \WP_Post            $post    Produkt.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use MODOhome\Catalog\Product;
use MODOhome\Catalog\Settings;

$modohome_details  = (string) Settings::get( 'details_mode', 'modal' );
$modohome_sold     = Product::STATUS_SOLD === $product['availability'];
$modohome_reserved = Product::STATUS_RESERVED === $product['availability'];

$modohome_classes = array( 'modohome-catalog-card' );

if ( $modohome_sold ) {
	$modohome_classes[] = 'modohome-catalog-card--sold';
}

if ( $modohome_reserved ) {
	$modohome_classes[] = 'modohome-catalog-card--reserved';
}

$modohome_interactive = 'none' !== $modohome_details;
$modohome_is_link     = 'link' === $modohome_details && '' !== $product['permalink'];
?>
<article
	class="<?php echo esc_attr( implode( ' ', $modohome_classes ) ); ?>"
	data-modohome-card
	data-product-id="<?php echo esc_attr( (string) $product['id'] ); ?>"
>
	<?php if ( $modohome_is_link ) : ?>
		<a class="modohome-catalog-card-link" href="<?php echo esc_url( (string) $product['permalink'] ); ?>">
	<?php elseif ( $modohome_interactive ) : ?>
		<button
			type="button"
			class="modohome-catalog-card-link"
			data-modohome-open="<?php echo esc_attr( (string) $product['id'] ); ?>"
			aria-haspopup="dialog"
		>
	<?php else : ?>
		<div class="modohome-catalog-card-link modohome-catalog-card-link--static">
	<?php endif; ?>

		<div class="modohome-catalog-card-media">
			<?php if ( '' !== $product['thumbnail'] ) : ?>
				<img
					class="modohome-catalog-card-image"
					src="<?php echo esc_url( (string) $product['thumbnail'] ); ?>"
					alt="<?php echo esc_attr( (string) $product['title'] ); ?>"
					loading="lazy"
					decoding="async"
				/>
			<?php else : ?>
				<span class="modohome-catalog-card-placeholder" aria-hidden="true"></span>
			<?php endif; ?>

			<div class="modohome-catalog-card-flags">
				<?php if ( Settings::bool( 'show_badges' ) && '' !== $product['badge_label'] ) : ?>
					<span class="modohome-catalog-badge modohome-catalog-badge--<?php echo esc_attr( (string) $product['badge'] ); ?>">
						<?php echo esc_html( (string) $product['badge_label'] ); ?>
					</span>
				<?php endif; ?>

				<?php if ( Settings::bool( 'show_added_today' ) && $product['is_new_today'] ) : ?>
					<span class="modohome-catalog-badge modohome-catalog-badge--today">
						<?php esc_html_e( 'Dodano dzisiaj', 'modohome-katalog-produktow' ); ?>
					</span>
				<?php endif; ?>
			</div>

			<?php if ( Settings::bool( 'show_availability' ) && ( $modohome_sold || $modohome_reserved ) ) : ?>
				<span class="modohome-catalog-status-tag modohome-catalog-status-tag--<?php echo esc_attr( (string) $product['availability'] ); ?>">
					<?php echo esc_html( (string) $product['availability_label'] ); ?>
				</span>
			<?php endif; ?>
		</div>

		<div class="modohome-catalog-card-body">
			<?php if ( Settings::bool( 'show_category' ) && ! empty( $product['categories'] ) ) : ?>
				<p class="modohome-catalog-card-category">
					<?php echo esc_html( (string) $product['categories'][0]['name'] ); ?>
				</p>
			<?php endif; ?>

			<h3 class="modohome-catalog-card-title"><?php echo esc_html( (string) $product['title'] ); ?></h3>

			<?php if ( Settings::bool( 'show_description' ) && '' !== $product['description'] ) : ?>
				<p class="modohome-catalog-card-description">
					<?php echo esc_html( wp_trim_words( (string) $product['description'], 14 ) ); ?>
				</p>
			<?php endif; ?>

			<p class="modohome-catalog-card-prices">
				<span class="modohome-catalog-price"><?php echo esc_html( (string) $product['price_formatted'] ); ?></span>

				<?php if ( Settings::bool( 'show_old_price' ) && '' !== $product['old_price_formatted'] ) : ?>
					<span class="modohome-catalog-price-old"><?php echo esc_html( (string) $product['old_price_formatted'] ); ?></span>
				<?php endif; ?>
			</p>

			<?php if ( Settings::bool( 'show_date' ) ) : ?>
				<p class="modohome-catalog-card-date">
					<time datetime="<?php echo esc_attr( (string) $product['date_iso'] ); ?>">
						<?php echo esc_html( (string) $product['date'] ); ?>
					</time>
				</p>
			<?php endif; ?>

			<?php if ( Settings::bool( 'show_details_button' ) && $modohome_interactive ) : ?>
				<span class="modohome-catalog-card-details">
					<?php esc_html_e( 'Zobacz szczegóły', 'modohome-katalog-produktow' ); ?>
				</span>
			<?php endif; ?>
		</div>

	<?php if ( $modohome_is_link ) : ?>
		</a>
	<?php elseif ( $modohome_interactive ) : ?>
		</button>
	<?php else : ?>
		</div>
	<?php endif; ?>
</article>
