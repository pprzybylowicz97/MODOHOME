<?php
/**
 * Zajawka najnowszych produktów.
 *
 * Motyw może nadpisać ten plik w katalogu modohome-katalog-produktow/latest.php.
 *
 * @package MODOhome\Catalog
 *
 * @var string   $instance_id      Unikalny identyfikator instancji.
 * @var \WP_Query $query           Produkty do pokazania.
 * @var int      $columns          Liczba kolumn na komputerze.
 * @var string   $heading          Nagłówek sekcji.
 * @var string   $intro            Tekst pod nagłówkiem.
 * @var string   $link             Adres strony katalogu.
 * @var string   $link_text        Tekst odnośnika na kafelku.
 * @var string   $ratio            Proporcje zdjęć.
 * @var string   $ratio_css        Proporcje w formacie CSS.
 * @var bool     $media_auto       Czy kafelek dopasowuje się do zdjęcia.
 * @var bool     $show_description Czy pokazać krótki opis.
 * @var bool     $show_price       Czy pokazać cenę.
 * @var bool     $show_category    Czy pokazać kategorię.
 * @var bool     $show_badges      Czy pokazać etykiety.
 * @var string   $all_link         Adres przycisku pod zajawką.
 * @var string   $all_text         Tekst przycisku pod zajawką.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use MODOhome\Catalog\Frontend\Latest;

$modohome_options = array(
	'link'             => $link,
	'link_text'        => $link_text,
	'show_description' => $show_description,
	'show_price'       => $show_price,
	'show_category'    => $show_category,
	'show_badges'      => $show_badges,
);
?>
<section
	class="modohome-catalog modohome-catalog-latest<?php echo $media_auto ? ' modohome-catalog--media-auto' : ''; ?>"
	id="<?php echo esc_attr( $instance_id ); ?>"
	data-modohome-teaser
	style="--modohome-catalog-cols-desktop:<?php echo esc_attr( (string) $columns ); ?>;--modohome-catalog-ratio:<?php echo esc_attr( $ratio_css ); ?>"
>
	<?php if ( '' !== trim( $heading ) ) : ?>
		<h2 class="modohome-catalog-heading"><?php echo esc_html( $heading ); ?></h2>
	<?php endif; ?>

	<?php if ( '' !== trim( $intro ) ) : ?>
		<div class="modohome-catalog-intro"><?php echo wp_kses_post( wpautop( $intro ) ); ?></div>
	<?php endif; ?>

	<div class="modohome-catalog-grid modohome-catalog-latest-grid">
		<?php
		foreach ( $query->posts as $modohome_post ) {
			if ( $modohome_post instanceof WP_Post ) {
				echo Latest::render_card( $modohome_post, $modohome_options ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
		}

		wp_reset_postdata();
		?>
	</div>

	<?php if ( '' !== $all_link && '' !== trim( $all_text ) ) : ?>
		<p class="modohome-catalog-latest-all">
			<a class="modohome-catalog-button" href="<?php echo esc_url( $all_link ); ?>">
				<?php echo esc_html( $all_text ); ?>
			</a>
		</p>
	<?php endif; ?>
</section>
