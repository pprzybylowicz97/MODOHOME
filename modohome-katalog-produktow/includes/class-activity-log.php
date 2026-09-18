<?php
/**
 * Prosty log aktywności katalogu.
 *
 * @package MODOhome\Catalog
 */

declare( strict_types = 1 );

namespace MODOhome\Catalog;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Log zapisuje wyłącznie identyfikator użytkownika i zdarzenie — bez adresów IP,
 * adresów e-mail i innych danych osobowych.
 */
class Activity_Log {

	public const ACTION_CREATED       = 'created';
	public const ACTION_PRICE_CHANGED = 'price_changed';
	public const ACTION_STATUS_CHANGED = 'status_changed';
	public const ACTION_DELETED       = 'deleted';
	public const ACTION_DUPLICATED    = 'duplicated';
	public const ACTION_IMPORTED      = 'imported';

	/**
	 * Pełna nazwa tabeli logu.
	 */
	public static function table_name(): string {
		global $wpdb;

		return $wpdb->prefix . 'modohome_catalog_log';
	}

	/**
	 * Tworzy tabelę logu.
	 */
	public static function create_table(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = self::table_name();
		$collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			product_id bigint(20) unsigned NOT NULL DEFAULT 0,
			product_title varchar(200) NOT NULL DEFAULT '',
			action varchar(32) NOT NULL DEFAULT '',
			details varchar(255) NOT NULL DEFAULT '',
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY product_id (product_id),
			KEY created_at (created_at)
		) {$collate};";

		dbDelta( $sql );
	}

	/**
	 * Usuwa tabelę logu.
	 */
	public static function drop_table(): void {
		global $wpdb;

		$table = self::table_name();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
	}

	/**
	 * Podpina automatyczne zapisywanie zdarzeń.
	 */
	public function register(): void {
		add_action( 'transition_post_status', array( $this, 'log_publish' ), 10, 3 );
		add_action( 'before_delete_post', array( $this, 'log_delete' ), 10, 2 );
		add_action( 'wp_trash_post', array( $this, 'log_trash' ) );
	}

	/**
	 * Zapisuje zdarzenie.
	 *
	 * @param string $action     Rodzaj zdarzenia.
	 * @param int    $product_id Identyfikator produktu.
	 * @param string $details    Krótki opis zmiany.
	 * @param int    $user_id    Identyfikator użytkownika (domyślnie bieżący).
	 */
	public static function record( string $action, int $product_id, string $details = '', int $user_id = 0 ): void {
		if ( ! Settings::bool( 'enable_activity_log' ) ) {
			return;
		}

		global $wpdb;

		$user_id = $user_id > 0 ? $user_id : get_current_user_id();
		$title   = $product_id > 0 ? (string) get_the_title( $product_id ) : '';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->insert(
			self::table_name(),
			array(
				'user_id'       => $user_id,
				'product_id'    => $product_id,
				'product_title' => mb_substr( $title, 0, 200 ),
				'action'        => mb_substr( $action, 0, 32 ),
				'details'       => mb_substr( $details, 0, 255 ),
				'created_at'    => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Log publikacji nowego produktu.
	 *
	 * @param string   $new_status Nowy status.
	 * @param string   $old_status Poprzedni status.
	 * @param \WP_Post $post       Wpis.
	 */
	public function log_publish( string $new_status, string $old_status, \WP_Post $post ): void {
		if ( Post_Type::SLUG !== $post->post_type || $new_status === $old_status ) {
			return;
		}

		if ( 'publish' === $new_status && in_array( $old_status, array( 'new', 'auto-draft', 'draft', 'pending' ), true ) ) {
			self::record( self::ACTION_CREATED, $post->ID, __( 'Produkt opublikowany', 'modohome-katalog-produktow' ) );
		}
	}

	/**
	 * Log trwałego usunięcia produktu.
	 *
	 * @param int      $post_id Identyfikator wpisu.
	 * @param \WP_Post $post    Wpis.
	 */
	public function log_delete( int $post_id, \WP_Post $post ): void {
		if ( Post_Type::SLUG !== $post->post_type ) {
			return;
		}

		self::record( self::ACTION_DELETED, $post_id, __( 'Produkt usunięty trwale', 'modohome-katalog-produktow' ) );
	}

	/**
	 * Log przeniesienia produktu do kosza.
	 *
	 * @param int $post_id Identyfikator wpisu.
	 */
	public function log_trash( int $post_id ): void {
		if ( Post_Type::SLUG !== get_post_type( $post_id ) ) {
			return;
		}

		self::record( self::ACTION_DELETED, $post_id, __( 'Produkt przeniesiony do kosza', 'modohome-katalog-produktow' ) );
	}

	/**
	 * Czytelne nazwy zdarzeń.
	 *
	 * @return array<string,string>
	 */
	public static function action_labels(): array {
		return array(
			self::ACTION_CREATED        => __( 'Dodanie produktu', 'modohome-katalog-produktow' ),
			self::ACTION_PRICE_CHANGED  => __( 'Zmiana ceny', 'modohome-katalog-produktow' ),
			self::ACTION_STATUS_CHANGED => __( 'Zmiana statusu', 'modohome-katalog-produktow' ),
			self::ACTION_DELETED        => __( 'Usunięcie produktu', 'modohome-katalog-produktow' ),
			self::ACTION_DUPLICATED     => __( 'Duplikat produktu', 'modohome-katalog-produktow' ),
			self::ACTION_IMPORTED       => __( 'Import z CSV', 'modohome-katalog-produktow' ),
		);
	}

	/**
	 * Pobiera wpisy logu.
	 *
	 * @param int $limit  Liczba wpisów.
	 * @param int $offset Przesunięcie.
	 *
	 * @return array<int,object>
	 */
	public static function get_entries( int $limit = 50, int $offset = 0 ): array {
		global $wpdb;

		$table = self::table_name();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d",
				max( 1, $limit ),
				max( 0, $offset )
			)
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Liczba wpisów w logu.
	 */
	public static function count_entries(): int {
		global $wpdb;

		$table = self::table_name();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
	}

	/**
	 * Czyści log.
	 */
	public static function clear(): void {
		global $wpdb;

		$table = self::table_name();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$wpdb->query( "TRUNCATE TABLE {$table}" );
	}
}
