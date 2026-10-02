<?php
/**
 * Kafelek zajawki najnowszych produktów.
 *
 * @package MODOhome\Catalog
 *
 * @var array<string,mixed> $product Dane produktu.
 * @var \WP_Post            $post    Produkt.
 * @var array<string,mixed> $options Ustawienia zajawki.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use MODOhome\Catalog\Product;

$modohome_link     = (string) $options['link'];
$modohome_has_link = '' !== $modohome_link;
$modohome_sold     = Product::STATUS_SOLD === $product['availability'];
?>
<article class="modohome-catalog-teaser<?php echo $modohome_sold ? ' modohome-catalog-teaser--sold' : ''; ?>">

	<?php if ( $modohome_has_link ) : ?>
		<a class="modohome-catalog-teaser-link" href="<?php echo esc_url( $modohome_link ); ?>">
	<?php else : ?>
		<button type="button" class="modohome-catalog-teaser-link" data-modohome-open="<?php echo esc_attr( (string) $product['id'] ); ?>" aria-haspopup="dialog">
	<?php endif; ?>

		<div class="modohome-catalog-teaser-media">
			<?php if ( $options['show_badges'] && '' !== $product['badge_label'] ) : ?>
				<span class="modohome-catalog-teaser-badge modohome-catalog-teaser-badge--<?php echo esc_attr( (string) $product['badge'] ); ?>">
					<?php echo esc_html( (string) $product['badge_label'] ); ?>
				</span>
			<?php elseif ( $modohome_sold ) : ?>
				<span class="modohome-catalog-teaser-badge modohome-catalog-teaser-badge--sold">
					<?php echo esc_html( (string) $product['availability_label'] ); ?>
				</span>
			<?php endif; ?>

			<?php if ( '' !== $product['thumbnail'] ) : ?>
				<img
					class="modohome-catalog-teaser-image"
					src="<?php echo esc_url( (string) $product['thumbnail'] ); ?>"
					alt="<?php echo esc_attr( (string) $product['title'] ); ?>"
					loading="lazy"
					decoding="async"
				/>
			<?php else : ?>
				<span class="modohome-catalog-teaser-placeholder" aria-hidden="true"></span>
			<?php endif; ?>
		</div>

		<div class="modohome-catalog-teaser-body">
			<?php if ( $options['show_category'] && ! empty( $product['categories'] ) ) : ?>
				<p class="modohome-catalog-teaser-category">
					<?php echo esc_html( (string) $product['categories'][0]['name'] ); ?>
				</p>
			<?php endif; ?>

			<h3 class="modohome-catalog-teaser-title"><?php echo esc_html( (string) $product['title'] ); ?></h3>

			<?php if ( $options['show_description'] && '' !== $product['description'] ) : ?>
				<p class="modohome-catalog-teaser-description">
					<?php echo esc_html( wp_trim_words( (string) $product['description'], 12 ) ); ?>
				</p>
			<?php endif; ?>

			<?php if ( $options['show_price'] && '' !== $product['price_formatted'] ) : ?>
				<p class="modohome-catalog-teaser-price">
					<span class="modohome-catalog-price"><?php echo esc_html( (string) $product['price_formatted'] ); ?></span>
				</p>
			<?php endif; ?>

			<span class="modohome-catalog-teaser-cta">
				<?php echo esc_html( (string) $options['link_text'] ); ?>
				<span class="modohome-catalog-teaser-arrow" aria-hidden="true">&rarr;</span>
			</span>
		</div>

	<?php if ( $modohome_has_link ) : ?>
		</a>
	<?php else : ?>
		</button>
	<?php endif; ?>
</article>
