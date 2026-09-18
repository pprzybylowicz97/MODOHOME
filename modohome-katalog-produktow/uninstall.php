<?php
/**
 * Deinstalacja wtyczki.
 *
 * Produkty i zdjęcia NIE są usuwane automatycznie. Dane znikają tylko wtedy,
 * gdy administrator włączył taką opcję w ustawieniach katalogu.
 *
 * @package MODOhome\Catalog
 */

declare( strict_types = 1 );

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$modohome_settings = get_option( 'modohome_catalog_settings', array() );

if ( ! is_array( $modohome_settings ) || empty( $modohome_settings['delete_data_on_uninstall'] ) ) {
	// Administrator nie zgodził się na usunięcie danych — zostawiamy wszystko nietknięte.
	return;
}

global $wpdb;

// 1. Produkty wraz z polami własnymi i powiązaniami taksonomii.
$modohome_product_ids = get_posts(
	array(
		'post_type'      => 'modohome_product',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
	)
);

foreach ( $modohome_product_ids as $modohome_product_id ) {
	// Zdjęcia zostają w bibliotece mediów — mogą być używane w innych miejscach.
	wp_delete_post( (int) $modohome_product_id, true );
}

// 2. Kategorie produktów.
$modohome_terms = get_terms(
	array(
		'taxonomy'   => 'modohome_product_category',
		'hide_empty' => false,
		'fields'     => 'ids',
	)
);

if ( ! is_wp_error( $modohome_terms ) ) {
	foreach ( $modohome_terms as $modohome_term_id ) {
		wp_delete_term( (int) $modohome_term_id, 'modohome_product_category' );
	}
}

// 3. Tabela logu aktywności.
$modohome_log_table = $wpdb->prefix . 'modohome_catalog_log';

// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
$wpdb->query( "DROP TABLE IF EXISTS {$modohome_log_table}" );

// 4. Rola pracownika i uprawnienia dodane rolom systemowym.
remove_role( 'modohome_catalog_worker' );

$modohome_caps = array(
	'manage_modohome_catalog',
	'manage_modohome_product_categories',
	'edit_modohome_product_categories',
	'delete_modohome_product_categories',
	'assign_modohome_product_categories',
	'edit_modohome_product',
	'read_modohome_product',
	'delete_modohome_product',
	'edit_modohome_products',
	'edit_others_modohome_products',
	'publish_modohome_products',
	'read_private_modohome_products',
	'delete_modohome_products',
	'delete_private_modohome_products',
	'delete_published_modohome_products',
	'delete_others_modohome_products',
	'edit_private_modohome_products',
	'edit_published_modohome_products',
);

foreach ( array( 'administrator', 'editor' ) as $modohome_role_name ) {
	$modohome_role = get_role( $modohome_role_name );

	if ( ! $modohome_role instanceof WP_Role ) {
		continue;
	}

	foreach ( $modohome_caps as $modohome_cap ) {
		$modohome_role->remove_cap( $modohome_cap );
	}
}

// 5. Ustawienia i zadanie cykliczne.
delete_option( 'modohome_catalog_settings' );
delete_option( 'modohome_catalog_version' );

$modohome_timestamp = wp_next_scheduled( 'modohome_catalog_auto_hide_event' );

if ( $modohome_timestamp ) {
	wp_unschedule_event( $modohome_timestamp, 'modohome_catalog_auto_hide_event' );
}
