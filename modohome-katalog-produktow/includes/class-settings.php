<?php
/**
 * Przechowywanie i walidacja ustawień wtyczki.
 *
 * @package MODOhome\Catalog
 */

declare( strict_types = 1 );

namespace MODOhome\Catalog;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wszystkie ustawienia trzymamy w jednej opcji, żeby ograniczyć liczbę zapytań.
 */
class Settings {

	public const OPTION_KEY = 'modohome_catalog_settings';

	/**
	 * Pamięć podręczna na czas jednego żądania.
	 *
	 * @var array<string,mixed>|null
	 */
	private static ?array $cache = null;

	/**
	 * Wartości domyślne.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults(): array {
		return array(
			// Wygląd.
			'primary_color'              => '#ef1616',
			'text_color'                 => '#111111',
			'bg_color'                   => '#ffffff',
			'card_color'                 => '#ffffff',
			'badge_color'                => '#ef1616',
			'columns_desktop'            => 4,
			'columns_tablet'             => 2,
			'columns_mobile'             => 2,
			'image_ratio'                => '3:4',
			'card_min_height'            => 0,
			'border_radius'              => 4,
			'grid_gap'                   => 20,
			'image_fit'                  => 'cover',
			'card_shadow'                => false,
			'catalog_heading'            => '',
			'catalog_intro'              => '',

			// Elementy karty.
			'show_category'              => true,
			'show_description'           => false,
			'show_old_price'             => true,
			'show_badges'                => true,
			'show_availability'          => true,
			'show_date'                  => false,
			'show_details_button'        => true,

			// Działanie katalogu.
			'per_page'                   => 12,
			'default_order'              => 'date',
			'show_sold'                  => 'show',
			'worker_autopublish'         => true,
			'workers_can_create_terms'   => false,
			'max_upload_size'            => 10,
			'max_image_dimension'        => 1600,
			'webp_convert'               => true,
			'webp_quality'               => 82,
			'webp_convert_all'           => false,
			'currency'                   => 'zł',
			'currency_position'          => 'after',
			'details_mode'               => 'modal',
			'enable_search'              => true,
			'enable_filters'             => true,
			'enable_load_more'           => true,
			'enable_single_pages'        => false,

			// Udogodnienia.
			'auto_hide_days'             => 0,
			'show_added_today'           => true,
			'show_category_counts'       => true,
			'enable_activity_log'        => true,

			// Deinstalacja i CSS.
			'delete_data_on_uninstall'   => false,
			'custom_css'                 => '',
		);
	}

	/**
	 * Zwraca komplet ustawień (domyślne nadpisane zapisanymi).
	 *
	 * @return array<string,mixed>
	 */
	public static function all(): array {
		if ( null === self::$cache ) {
			$stored = get_option( self::OPTION_KEY, array() );

			if ( ! is_array( $stored ) ) {
				$stored = array();
			}

			self::$cache = wp_parse_args( $stored, self::defaults() );
		}

		return self::$cache;
	}

	/**
	 * Pojedyncze ustawienie.
	 *
	 * @param string $key     Klucz ustawienia.
	 * @param mixed  $default Wartość zwracana, gdy klucz nie istnieje.
	 *
	 * @return mixed
	 */
	public static function get( string $key, mixed $default = null ): mixed {
		$all = self::all();

		return array_key_exists( $key, $all ) ? $all[ $key ] : $default;
	}

	/**
	 * Ustawienie traktowane jako wartość logiczna.
	 *
	 * @param string $key Klucz ustawienia.
	 */
	public static function bool( string $key ): bool {
		return (bool) self::get( $key, false );
	}

	/**
	 * Ustawienie traktowane jako liczba całkowita.
	 *
	 * @param string $key Klucz ustawienia.
	 */
	public static function int( string $key ): int {
		return (int) self::get( $key, 0 );
	}

	/**
	 * Zapisuje ustawienia po sanityzacji.
	 *
	 * @param array<string,mixed> $values Nowe wartości.
	 */
	public static function update( array $values ): void {
		$clean = self::sanitize( $values );

		update_option( self::OPTION_KEY, $clean );
		self::flush();
	}

	/**
	 * Czyści pamięć podręczną (po zapisie lub przywróceniu domyślnych).
	 */
	public static function flush(): void {
		self::$cache = null;
	}

	/**
	 * Przywraca ustawienia domyślne.
	 */
	public static function reset(): void {
		update_option( self::OPTION_KEY, self::defaults() );
		self::flush();
	}

	/**
	 * Sanityzacja całej tablicy ustawień. Używana też jako callback register_setting().
	 *
	 * @param mixed $input Dane z formularza.
	 *
	 * @return array<string,mixed>
	 */
	public static function sanitize( mixed $input ): array {
		$defaults = self::defaults();

		if ( ! is_array( $input ) ) {
			return $defaults;
		}

		// Pole „przywróć domyślne” obsługujemy zanim cokolwiek zapiszemy.
		if ( ! empty( $input['modohome_catalog_reset'] ) ) {
			return $defaults;
		}

		$out = array();

		$hex = static function ( mixed $value, string $fallback ): string {
			$color = sanitize_hex_color( is_string( $value ) ? $value : '' );

			return $color ?: $fallback;
		};

		$out['primary_color'] = $hex( $input['primary_color'] ?? '', $defaults['primary_color'] );
		$out['text_color']    = $hex( $input['text_color'] ?? '', $defaults['text_color'] );
		$out['bg_color']      = $hex( $input['bg_color'] ?? '', $defaults['bg_color'] );
		$out['card_color']    = $hex( $input['card_color'] ?? '', $defaults['card_color'] );
		$out['badge_color']   = $hex( $input['badge_color'] ?? '', $defaults['badge_color'] );

		$out['columns_desktop'] = self::clamp_int( $input['columns_desktop'] ?? 4, 1, 6, 4 );
		$out['columns_tablet']  = self::clamp_int( $input['columns_tablet'] ?? 2, 1, 4, 2 );
		$out['columns_mobile']  = self::clamp_int( $input['columns_mobile'] ?? 2, 1, 2, 2 );

		$out['image_ratio'] = self::pick( $input['image_ratio'] ?? '', array_keys( self::image_ratios() ), $defaults['image_ratio'] );
		$out['image_fit']   = self::pick( $input['image_fit'] ?? '', array( 'cover', 'contain' ), $defaults['image_fit'] );

		$out['card_min_height'] = self::clamp_int( $input['card_min_height'] ?? 0, 0, 1200, 0 );
		$out['border_radius']   = self::clamp_int( $input['border_radius'] ?? 4, 0, 40, 4 );
		$out['grid_gap']        = self::clamp_int( $input['grid_gap'] ?? 20, 0, 80, 20 );
		$out['card_shadow']     = ! empty( $input['card_shadow'] );

		$out['catalog_heading'] = sanitize_text_field( (string) ( $input['catalog_heading'] ?? '' ) );
		$out['catalog_intro']   = wp_kses_post( (string) ( $input['catalog_intro'] ?? '' ) );

		foreach ( array( 'show_category', 'show_description', 'show_old_price', 'show_badges', 'show_availability', 'show_date', 'show_details_button' ) as $flag ) {
			$out[ $flag ] = ! empty( $input[ $flag ] );
		}

		$out['per_page']      = self::clamp_int( $input['per_page'] ?? 12, 1, 100, 12 );
		$out['default_order'] = self::pick( $input['default_order'] ?? '', array( 'date', 'price_asc', 'price_desc', 'menu_order' ), $defaults['default_order'] );
		$out['show_sold']     = self::pick( $input['show_sold'] ?? '', array( 'show', 'hide' ), $defaults['show_sold'] );

		$out['worker_autopublish']       = ! empty( $input['worker_autopublish'] );
		$out['workers_can_create_terms'] = ! empty( $input['workers_can_create_terms'] );

		$out['max_upload_size']     = self::clamp_int( $input['max_upload_size'] ?? 10, 1, 64, 10 );
		$out['max_image_dimension'] = self::clamp_int( $input['max_image_dimension'] ?? 1600, 400, 5000, 1600 );
		$out['webp_quality']        = self::clamp_int( $input['webp_quality'] ?? 82, 40, 100, 82 );

		$currency           = sanitize_text_field( (string) ( $input['currency'] ?? '' ) );
		$out['currency']    = '' !== $currency ? mb_substr( $currency, 0, 8 ) : $defaults['currency'];
		$out['currency_position'] = self::pick( $input['currency_position'] ?? '', array( 'after', 'before' ), $defaults['currency_position'] );

		$out['details_mode']  = self::pick( $input['details_mode'] ?? '', array( 'modal', 'link', 'none' ), $defaults['details_mode'] );

		foreach ( array( 'enable_search', 'enable_filters', 'enable_load_more', 'enable_single_pages', 'show_added_today', 'show_category_counts', 'enable_activity_log', 'delete_data_on_uninstall', 'webp_convert', 'webp_convert_all' ) as $flag ) {
			$out[ $flag ] = ! empty( $input[ $flag ] );
		}

		$out['auto_hide_days'] = self::clamp_int( $input['auto_hide_days'] ?? 0, 0, 3650, 0 );

		// Własny CSS może zapisać wyłącznie administrator.
		if ( current_user_can( Roles::CAP_MANAGE ) ) {
			$out['custom_css'] = self::sanitize_css( (string) ( $input['custom_css'] ?? '' ) );
		} else {
			$out['custom_css'] = (string) self::get( 'custom_css', '' );
		}

		// Zmiana trybu podstron produktu wymaga odświeżenia reguł przepisywania.
		if ( self::bool( 'enable_single_pages' ) !== $out['enable_single_pages'] ) {
			set_transient( 'modohome_catalog_flush_rewrite', 1, MINUTE_IN_SECONDS );
		}

		return wp_parse_args( $out, $defaults );
	}

	/**
	 * Usuwa z własnego CSS elementy, które mogłyby posłużyć do wstrzyknięcia skryptu.
	 *
	 * @param string $css Surowy CSS.
	 */
	public static function sanitize_css( string $css ): string {
		$css = wp_strip_all_tags( $css );
		$css = preg_replace( '#(javascript|expression|behaviour|behavior|vbscript|@import)\s*:?#i', '', $css ) ?? '';
		$css = str_replace( array( '<', '>' ), '', $css );

		return trim( $css );
	}

	/**
	 * Dostępne proporcje zdjęć.
	 *
	 * @return array<string,string>
	 */
	public static function image_ratios(): array {
		return array(
			'1:1'  => '1:1 (kwadrat)',
			'4:3'  => '4:3 (poziome)',
			'3:4'  => '3:4 (pionowe)',
			'16:9' => '16:9 (panorama)',
			'2:3'  => '2:3 (wysokie)',
		);
	}

	/**
	 * Zamienia proporcję na wartość CSS aspect-ratio.
	 *
	 * @param string $ratio Proporcja w formacie „3:4”.
	 */
	public static function ratio_to_css( string $ratio ): string {
		$parts = explode( ':', $ratio );

		if ( 2 !== count( $parts ) ) {
			return '3 / 4';
		}

		$w = max( 1, (int) $parts[0] );
		$h = max( 1, (int) $parts[1] );

		return $w . ' / ' . $h;
	}

	/**
	 * Liczba całkowita ograniczona do zakresu.
	 *
	 * @param mixed $value    Wartość wejściowa.
	 * @param int   $min      Minimum.
	 * @param int   $max      Maksimum.
	 * @param int   $fallback Wartość awaryjna.
	 */
	private static function clamp_int( mixed $value, int $min, int $max, int $fallback ): int {
		if ( ! is_numeric( $value ) ) {
			return $fallback;
		}

		return max( $min, min( $max, (int) $value ) );
	}

	/**
	 * Wybór z listy dozwolonych wartości.
	 *
	 * @param mixed         $value    Wartość wejściowa.
	 * @param array<string> $allowed  Dozwolone wartości.
	 * @param string        $fallback Wartość awaryjna.
	 */
	private static function pick( mixed $value, array $allowed, string $fallback ): string {
		$value = is_string( $value ) ? $value : '';

		return in_array( $value, $allowed, true ) ? $value : $fallback;
	}
}
