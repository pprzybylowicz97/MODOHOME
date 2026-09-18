<?php
/**
 * Aktywacja i dezaktywacja wtyczki.
 *
 * @package MODOhome\Catalog
 */

declare( strict_types = 1 );

namespace MODOhome\Catalog;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Czynności jednorazowe: role, tabela logu, ustawienia domyślne, zadania cykliczne.
 */
class Activator {

	public const CRON_HOOK = 'modohome_catalog_auto_hide_event';

	/**
	 * Uruchamiane przy aktywacji wtyczki.
	 */
	public static function activate(): void {
		if ( ! get_option( Settings::OPTION_KEY ) ) {
			add_option( Settings::OPTION_KEY, Settings::defaults() );
		}

		Roles::install();
		Activity_Log::create_table();

		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
		}

		// Typ wpisu rejestruje się na init, więc przy aktywacji robimy to ręcznie
		// przed odświeżeniem reguł przepisywania.
		( new Post_Type() )->register_post_type();
		( new Taxonomy() )->register_taxonomy();

		flush_rewrite_rules();

		update_option( 'modohome_catalog_version', MODOHOME_CATALOG_VERSION );
	}

	/**
	 * Uruchamiane przy dezaktywacji wtyczki. Dane pozostają nietknięte.
	 */
	public static function deactivate(): void {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );

		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
		}

		flush_rewrite_rules();
	}
}
