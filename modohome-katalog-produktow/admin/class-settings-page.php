<?php
/**
 * Ekran „Produkty → Ustawienia katalogu”.
 *
 * @package MODOhome\Catalog
 */

declare( strict_types = 1 );

namespace MODOhome\Catalog\Admin;

use MODOhome\Catalog\Post_Type;
use MODOhome\Catalog\Roles;
use MODOhome\Catalog\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Formularz ustawień w czterech sekcjach.
 */
class Settings_Page {

	public const SLUG = 'modohome-catalog-settings';

	/**
	 * Podpina stronę i rejestrację ustawień.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_action( 'admin_init', array( $this, 'register_setting' ) );
	}

	/**
	 * Dodaje podstronę w menu „Produkty”.
	 */
	public function add_page(): void {
		add_submenu_page(
			'edit.php?post_type=' . Post_Type::SLUG,
			__( 'Ustawienia katalogu', 'modohome-katalog-produktow' ),
			__( 'Ustawienia katalogu', 'modohome-katalog-produktow' ),
			Roles::CAP_MANAGE,
			self::SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * Rejestruje opcję i jej sanityzację.
	 */
	public function register_setting(): void {
		register_setting(
			'modohome_catalog_settings_group',
			Settings::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( Settings::class, 'sanitize' ),
				'default'           => Settings::defaults(),
			)
		);
	}

	/**
	 * Renderuje stronę ustawień.
	 */
	public function render(): void {
		if ( ! current_user_can( Roles::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'Brak uprawnień do tej strony.', 'modohome-katalog-produktow' ) );
		}

		$s    = Settings::all();
		$name = Settings::OPTION_KEY;
		?>
		<div class="wrap modohome-settings">
			<h1><?php esc_html_e( 'Ustawienia katalogu MODOhome', 'modohome-katalog-produktow' ); ?></h1>

			<p class="modohome-settings-intro">
				<?php esc_html_e( 'Shortcode katalogu:', 'modohome-katalog-produktow' ); ?>
				<code>[modohome_catalog]</code> ·
				<?php esc_html_e( 'formularz dodawania:', 'modohome-katalog-produktow' ); ?>
				<code>[modohome_product_form]</code> ·
				<?php esc_html_e( 'panel pracownika:', 'modohome-katalog-produktow' ); ?>
				<code>[modohome_my_products]</code>
			</p>

			<form method="post" action="options.php">
				<?php settings_fields( 'modohome_catalog_settings_group' ); ?>

				<h2 class="title"><?php esc_html_e( 'Wygląd', 'modohome-katalog-produktow' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php
					$this->color_row( $name, 'primary_color', __( 'Kolor główny', 'modohome-katalog-produktow' ), (string) $s['primary_color'] );
					$this->color_row( $name, 'text_color', __( 'Kolor tekstu', 'modohome-katalog-produktow' ), (string) $s['text_color'] );
					$this->color_row( $name, 'bg_color', __( 'Kolor tła', 'modohome-katalog-produktow' ), (string) $s['bg_color'] );
					$this->color_row( $name, 'card_color', __( 'Kolor kart', 'modohome-katalog-produktow' ), (string) $s['card_color'] );
					$this->color_row( $name, 'badge_color', __( 'Kolor etykiet', 'modohome-katalog-produktow' ), (string) $s['badge_color'] );

					$this->number_row( $name, 'columns_desktop', __( 'Kolumny — komputer', 'modohome-katalog-produktow' ), (int) $s['columns_desktop'], 1, 6 );
					$this->number_row( $name, 'columns_tablet', __( 'Kolumny — tablet', 'modohome-katalog-produktow' ), (int) $s['columns_tablet'], 1, 4 );
					$this->number_row( $name, 'columns_mobile', __( 'Kolumny — telefon', 'modohome-katalog-produktow' ), (int) $s['columns_mobile'], 1, 2 );

					$this->select_row( $name, 'image_ratio', __( 'Proporcje zdjęć', 'modohome-katalog-produktow' ), Settings::image_ratios(), (string) $s['image_ratio'] );
					$this->select_row(
						$name,
						'image_fit',
						__( 'Sposób przycinania zdjęć', 'modohome-katalog-produktow' ),
						array(
							'cover'   => __( 'Wypełnij kadr (przycina)', 'modohome-katalog-produktow' ),
							'contain' => __( 'Zmieść całe zdjęcie', 'modohome-katalog-produktow' ),
						),
						(string) $s['image_fit']
					);

					$this->number_row( $name, 'card_min_height', __( 'Minimalna wysokość kart (px, 0 = automatycznie)', 'modohome-katalog-produktow' ), (int) $s['card_min_height'], 0, 1200 );
					$this->number_row( $name, 'border_radius', __( 'Promień zaokrąglenia (px)', 'modohome-katalog-produktow' ), (int) $s['border_radius'], 0, 40 );
					$this->number_row( $name, 'grid_gap', __( 'Odstęp między kartami (px)', 'modohome-katalog-produktow' ), (int) $s['grid_gap'], 0, 80 );

					$this->checkbox_row( $name, 'card_shadow', __( 'Cień kart', 'modohome-katalog-produktow' ), __( 'Włącz cień pod kartami', 'modohome-katalog-produktow' ), (bool) $s['card_shadow'] );
					$this->text_row( $name, 'catalog_heading', __( 'Nagłówek katalogu', 'modohome-katalog-produktow' ), (string) $s['catalog_heading'] );
					$this->textarea_row( $name, 'catalog_intro', __( 'Tekst nad katalogiem', 'modohome-katalog-produktow' ), (string) $s['catalog_intro'], 3 );
					?>
				</table>

				<h2 class="title"><?php esc_html_e( 'Elementy karty produktu', 'modohome-katalog-produktow' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php
					$this->checkbox_row( $name, 'show_category', __( 'Kategoria', 'modohome-katalog-produktow' ), __( 'Pokazuj kategorię na karcie', 'modohome-katalog-produktow' ), (bool) $s['show_category'] );
					$this->checkbox_row( $name, 'show_description', __( 'Opis', 'modohome-katalog-produktow' ), __( 'Pokazuj skrócony opis na karcie', 'modohome-katalog-produktow' ), (bool) $s['show_description'] );
					$this->checkbox_row( $name, 'show_old_price', __( 'Cena poprzednia', 'modohome-katalog-produktow' ), __( 'Pokazuj przekreśloną cenę poprzednią', 'modohome-katalog-produktow' ), (bool) $s['show_old_price'] );
					$this->checkbox_row( $name, 'show_badges', __( 'Etykiety', 'modohome-katalog-produktow' ), __( 'Pokazuj etykiety produktów', 'modohome-katalog-produktow' ), (bool) $s['show_badges'] );
					$this->checkbox_row( $name, 'show_availability', __( 'Status dostępności', 'modohome-katalog-produktow' ), __( 'Pokazuj status „Sprzedany” i „Zarezerwowany”', 'modohome-katalog-produktow' ), (bool) $s['show_availability'] );
					$this->checkbox_row( $name, 'show_date', __( 'Data dodania', 'modohome-katalog-produktow' ), __( 'Pokazuj datę dodania produktu', 'modohome-katalog-produktow' ), (bool) $s['show_date'] );
					$this->checkbox_row( $name, 'show_details_button', __( 'Zobacz szczegóły', 'modohome-katalog-produktow' ), __( 'Pokazuj informację „Zobacz szczegóły”', 'modohome-katalog-produktow' ), (bool) $s['show_details_button'] );
					$this->checkbox_row( $name, 'show_added_today', __( 'Oznaczenie „Dodano dzisiaj”', 'modohome-katalog-produktow' ), __( 'Wyróżniaj produkty dodane dzisiaj', 'modohome-katalog-produktow' ), (bool) $s['show_added_today'] );
					?>
				</table>

				<h2 class="title"><?php esc_html_e( 'Działanie katalogu', 'modohome-katalog-produktow' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php
					$this->number_row( $name, 'per_page', __( 'Produktów na stronę', 'modohome-katalog-produktow' ), (int) $s['per_page'], 1, 100 );

					$this->select_row(
						$name,
						'default_order',
						__( 'Domyślne sortowanie', 'modohome-katalog-produktow' ),
						array(
							'date'       => __( 'Najnowsze', 'modohome-katalog-produktow' ),
							'price_asc'  => __( 'Cena rosnąco', 'modohome-katalog-produktow' ),
							'price_desc' => __( 'Cena malejąco', 'modohome-katalog-produktow' ),
							'menu_order' => __( 'Własna kolejność', 'modohome-katalog-produktow' ),
						),
						(string) $s['default_order']
					);

					$this->select_row(
						$name,
						'show_sold',
						__( 'Produkty sprzedane', 'modohome-katalog-produktow' ),
						array(
							'show' => __( 'Pokazuj z etykietą „Sprzedany”', 'modohome-katalog-produktow' ),
							'hide' => __( 'Ukrywaj w katalogu', 'modohome-katalog-produktow' ),
						),
						(string) $s['show_sold']
					);

					$this->select_row(
						$name,
						'details_mode',
						__( 'Szczegóły produktu', 'modohome-katalog-produktow' ),
						array(
							'modal' => __( 'Okno modalne', 'modohome-katalog-produktow' ),
							'link'  => __( 'Podstrona produktu', 'modohome-katalog-produktow' ),
							'none'  => __( 'Bez szczegółów', 'modohome-katalog-produktow' ),
						),
						(string) $s['details_mode']
					);

					$this->checkbox_row( $name, 'enable_single_pages', __( 'Podstrony produktów', 'modohome-katalog-produktow' ), __( 'Włącz publiczne podstrony pojedynczych produktów', 'modohome-katalog-produktow' ), (bool) $s['enable_single_pages'] );
					$this->checkbox_row( $name, 'enable_search', __( 'Wyszukiwarka', 'modohome-katalog-produktow' ), __( 'Pokazuj pole wyszukiwania w katalogu', 'modohome-katalog-produktow' ), (bool) $s['enable_search'] );
					$this->checkbox_row( $name, 'enable_filters', __( 'Filtry', 'modohome-katalog-produktow' ), __( 'Pokazuj filtry kategorii i sortowanie', 'modohome-katalog-produktow' ), (bool) $s['enable_filters'] );
					$this->checkbox_row( $name, 'enable_load_more', __( 'Przycisk „Pokaż więcej”', 'modohome-katalog-produktow' ), __( 'Doładowuj produkty przyciskiem zamiast przeładowania strony', 'modohome-katalog-produktow' ), (bool) $s['enable_load_more'] );
					$this->checkbox_row( $name, 'show_category_counts', __( 'Licznik produktów', 'modohome-katalog-produktow' ), __( 'Pokazuj liczbę produktów przy kategoriach', 'modohome-katalog-produktow' ), (bool) $s['show_category_counts'] );

					$this->checkbox_row( $name, 'worker_autopublish', __( 'Publikacja produktów pracowników', 'modohome-katalog-produktow' ), __( 'Publikuj od razu, bez zatwierdzania', 'modohome-katalog-produktow' ), (bool) $s['worker_autopublish'] );
					$this->checkbox_row( $name, 'workers_can_create_terms', __( 'Kategorie przez pracowników', 'modohome-katalog-produktow' ), __( 'Pozwól pracownikom tworzyć kategorie', 'modohome-katalog-produktow' ), (bool) $s['workers_can_create_terms'] );

					$this->number_row( $name, 'auto_hide_days', __( 'Automatyczne ukrycie po (dni, 0 = wyłączone)', 'modohome-katalog-produktow' ), (int) $s['auto_hide_days'], 0, 3650 );

					$this->text_row( $name, 'currency', __( 'Waluta', 'modohome-katalog-produktow' ), (string) $s['currency'] );
					$this->select_row(
						$name,
						'currency_position',
						__( 'Pozycja waluty', 'modohome-katalog-produktow' ),
						array(
							'after'  => __( 'Po cenie (1 200 zł)', 'modohome-katalog-produktow' ),
							'before' => __( 'Przed ceną (zł 1 200)', 'modohome-katalog-produktow' ),
						),
						(string) $s['currency_position']
					);
					?>
				</table>

				<h2 class="title"><?php esc_html_e( 'Optymalizacja zdjęć', 'modohome-katalog-produktow' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Zdjęcie jest skalowane i konwertowane zanim WordPress zapisze je na dysku, więc na serwerze nie zostaje wielki oryginał z telefonu.', 'modohome-katalog-produktow' ); ?>
					<?php if ( ! \MODOhome\Catalog\Image_Optimizer::is_supported() ) : ?>
						<strong><?php esc_html_e( 'Uwaga: ten serwer nie obsługuje zapisu WebP — zdjęcia będą tylko skalowane.', 'modohome-katalog-produktow' ); ?></strong>
					<?php endif; ?>
				</p>
				<table class="form-table" role="presentation">
					<?php
					$this->number_row( $name, 'max_upload_size', __( 'Maksymalny rozmiar przesyłanego pliku (MB)', 'modohome-katalog-produktow' ), (int) $s['max_upload_size'], 1, 64 );
					$this->number_row( $name, 'max_image_dimension', __( 'Maksymalny wymiar obrazu (px)', 'modohome-katalog-produktow' ), (int) $s['max_image_dimension'], 400, 5000 );

					$this->checkbox_row(
						$name,
						'webp_convert',
						__( 'Konwersja do WebP', 'modohome-katalog-produktow' ),
						__( 'Zapisuj przesyłane zdjęcia jako WebP (zwykle 25–35% mniejsze od JPG)', 'modohome-katalog-produktow' ),
						(bool) $s['webp_convert']
					);

					$this->number_row( $name, 'webp_quality', __( 'Jakość WebP (40–100)', 'modohome-katalog-produktow' ), (int) $s['webp_quality'], 40, 100 );

					$this->checkbox_row(
						$name,
						'webp_convert_all',
						__( 'Optymalizuj całą stronę', 'modohome-katalog-produktow' ),
						__( 'Stosuj skalowanie i konwersję także do zdjęć przesyłanych poza katalogiem (wpisy, strony, biblioteka mediów)', 'modohome-katalog-produktow' ),
						(bool) $s['webp_convert_all']
					);
					?>
					<tr>
						<th scope="row"><?php esc_html_e( 'Obsługa WebP na serwerze', 'modohome-katalog-produktow' ); ?></th>
						<td>
							<?php if ( \MODOhome\Catalog\Image_Optimizer::is_supported() ) : ?>
								<span class="modohome-admin-status modohome-admin-status--available"><?php esc_html_e( 'Dostępna', 'modohome-katalog-produktow' ); ?></span>
							<?php else : ?>
								<span class="modohome-admin-status modohome-admin-status--sold"><?php esc_html_e( 'Niedostępna', 'modohome-katalog-produktow' ); ?></span>
								<p class="description"><?php esc_html_e( 'Poproś hosting o włączenie GD z obsługą WebP lub rozszerzenia Imagick.', 'modohome-katalog-produktow' ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
				</table>

				<h2 class="title"><?php esc_html_e( 'Dane i zaawansowane', 'modohome-katalog-produktow' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php
					$this->checkbox_row( $name, 'enable_activity_log', __( 'Log aktywności', 'modohome-katalog-produktow' ), __( 'Zapisuj zmiany produktów w logu', 'modohome-katalog-produktow' ), (bool) $s['enable_activity_log'] );
					$this->checkbox_row( $name, 'delete_data_on_uninstall', __( 'Usuwanie danych', 'modohome-katalog-produktow' ), __( 'Usuń produkty, kategorie i ustawienia przy odinstalowaniu wtyczki', 'modohome-katalog-produktow' ), (bool) $s['delete_data_on_uninstall'] );
					?>
					<tr>
						<th scope="row"><label for="modohome_custom_css"><?php esc_html_e( 'Własny CSS', 'modohome-katalog-produktow' ); ?></label></th>
						<td>
							<textarea
								id="modohome_custom_css"
								name="<?php echo esc_attr( $name ); ?>[custom_css]"
								rows="8"
								class="large-text code"
								spellcheck="false"
							><?php echo esc_textarea( (string) $s['custom_css'] ); ?></textarea>
							<p class="description">
								<?php esc_html_e( 'CSS ładuje się tylko tam, gdzie działa katalog. Zapis dostępny wyłącznie dla administratora.', 'modohome-katalog-produktow' ); ?>
							</p>
						</td>
					</tr>
				</table>

				<p class="submit">
					<?php submit_button( __( 'Zapisz ustawienia', 'modohome-katalog-produktow' ), 'primary', 'submit', false ); ?>
					<button
						type="submit"
						name="<?php echo esc_attr( $name ); ?>[modohome_catalog_reset]"
						value="1"
						class="button button-secondary"
						data-modohome-reset
					>
						<?php esc_html_e( 'Przywróć ustawienia domyślne', 'modohome-katalog-produktow' ); ?>
					</button>
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * Wiersz z polem koloru.
	 *
	 * @param string $name  Nazwa opcji.
	 * @param string $key   Klucz ustawienia.
	 * @param string $label Etykieta.
	 * @param string $value Wartość.
	 */
	private function color_row( string $name, string $key, string $label, string $value ): void {
		?>
		<tr>
			<th scope="row"><label for="modohome_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
			<td>
				<input
					type="text"
					id="modohome_<?php echo esc_attr( $key ); ?>"
					name="<?php echo esc_attr( $name ); ?>[<?php echo esc_attr( $key ); ?>]"
					value="<?php echo esc_attr( $value ); ?>"
					class="modohome-color-field"
					data-default-color="<?php echo esc_attr( (string) ( Settings::defaults()[ $key ] ?? '' ) ); ?>"
				/>
			</td>
		</tr>
		<?php
	}

	/**
	 * Wiersz z polem liczbowym.
	 *
	 * @param string $name  Nazwa opcji.
	 * @param string $key   Klucz ustawienia.
	 * @param string $label Etykieta.
	 * @param int    $value Wartość.
	 * @param int    $min   Minimum.
	 * @param int    $max   Maksimum.
	 */
	private function number_row( string $name, string $key, string $label, int $value, int $min, int $max ): void {
		?>
		<tr>
			<th scope="row"><label for="modohome_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
			<td>
				<input
					type="number"
					id="modohome_<?php echo esc_attr( $key ); ?>"
					name="<?php echo esc_attr( $name ); ?>[<?php echo esc_attr( $key ); ?>]"
					value="<?php echo esc_attr( (string) $value ); ?>"
					min="<?php echo esc_attr( (string) $min ); ?>"
					max="<?php echo esc_attr( (string) $max ); ?>"
					step="1"
					class="small-text"
				/>
			</td>
		</tr>
		<?php
	}

	/**
	 * Wiersz z polem tekstowym.
	 *
	 * @param string $name  Nazwa opcji.
	 * @param string $key   Klucz ustawienia.
	 * @param string $label Etykieta.
	 * @param string $value Wartość.
	 */
	private function text_row( string $name, string $key, string $label, string $value ): void {
		?>
		<tr>
			<th scope="row"><label for="modohome_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
			<td>
				<input
					type="text"
					id="modohome_<?php echo esc_attr( $key ); ?>"
					name="<?php echo esc_attr( $name ); ?>[<?php echo esc_attr( $key ); ?>]"
					value="<?php echo esc_attr( $value ); ?>"
					class="regular-text"
				/>
			</td>
		</tr>
		<?php
	}

	/**
	 * Wiersz z polem wielowierszowym.
	 *
	 * @param string $name  Nazwa opcji.
	 * @param string $key   Klucz ustawienia.
	 * @param string $label Etykieta.
	 * @param string $value Wartość.
	 * @param int    $rows  Liczba wierszy.
	 */
	private function textarea_row( string $name, string $key, string $label, string $value, int $rows = 4 ): void {
		?>
		<tr>
			<th scope="row"><label for="modohome_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
			<td>
				<textarea
					id="modohome_<?php echo esc_attr( $key ); ?>"
					name="<?php echo esc_attr( $name ); ?>[<?php echo esc_attr( $key ); ?>]"
					rows="<?php echo esc_attr( (string) $rows ); ?>"
					class="large-text"
				><?php echo esc_textarea( $value ); ?></textarea>
			</td>
		</tr>
		<?php
	}

	/**
	 * Wiersz z polem wyboru.
	 *
	 * @param string               $name    Nazwa opcji.
	 * @param string               $key     Klucz ustawienia.
	 * @param string               $label   Etykieta.
	 * @param array<string,string> $options Dostępne wartości.
	 * @param string               $value   Wybrana wartość.
	 */
	private function select_row( string $name, string $key, string $label, array $options, string $value ): void {
		?>
		<tr>
			<th scope="row"><label for="modohome_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
			<td>
				<select
					id="modohome_<?php echo esc_attr( $key ); ?>"
					name="<?php echo esc_attr( $name ); ?>[<?php echo esc_attr( $key ); ?>]"
				>
					<?php foreach ( $options as $option_key => $option_label ) : ?>
						<option value="<?php echo esc_attr( $option_key ); ?>" <?php selected( $value, $option_key ); ?>>
							<?php echo esc_html( $option_label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
		<?php
	}

	/**
	 * Wiersz z polem wyboru tak/nie.
	 *
	 * @param string $name        Nazwa opcji.
	 * @param string $key         Klucz ustawienia.
	 * @param string $label       Etykieta wiersza.
	 * @param string $description Opis przy polu.
	 * @param bool   $value       Wartość.
	 */
	private function checkbox_row( string $name, string $key, string $label, string $description, bool $value ): void {
		?>
		<tr>
			<th scope="row"><?php echo esc_html( $label ); ?></th>
			<td>
				<label for="modohome_<?php echo esc_attr( $key ); ?>">
					<input
						type="checkbox"
						id="modohome_<?php echo esc_attr( $key ); ?>"
						name="<?php echo esc_attr( $name ); ?>[<?php echo esc_attr( $key ); ?>]"
						value="1"
						<?php checked( $value ); ?>
					/>
					<?php echo esc_html( $description ); ?>
				</label>
			</td>
		</tr>
		<?php
	}
}
