<?php
/**
 * Minimalne atrapy WordPressa na potrzeby testów logiki wtyczki.
 */

define( 'ABSPATH', __DIR__ . '/fake-wp/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'MB_IN_BYTES', 1048576 );

$GLOBALS['stub_options'] = array();
$GLOBALS['stub_terms']   = array();
$GLOBALS['stub_caps']    = array( 'manage_modohome_catalog' => true );

function __( $text, $domain = null ) { return $text; }
function _e( $text, $domain = null ) { echo $text; }
function esc_html__( $text, $domain = null ) { return $text; }
function esc_attr__( $text, $domain = null ) { return $text; }
function esc_html_e( $text, $domain = null ) { echo $text; }
function esc_attr_e( $text, $domain = null ) { echo $text; }
function _n( $s, $p, $n, $domain = null ) { return 1 === $n ? $s : $p; }
function esc_html( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $t ) { return filter_var( (string) $t, FILTER_SANITIZE_URL ); }
function esc_textarea( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES, 'UTF-8' ); }

function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_textarea_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function sanitize_title( $s ) { return strtolower( preg_replace( '/[^a-zA-Z0-9]+/', '-', (string) $s ) ); }
function sanitize_file_name( $s ) { return preg_replace( '/[^a-zA-Z0-9._-]/', '', (string) $s ); }
function wp_strip_all_tags( $s ) { return strip_tags( (string) $s ); }
function wp_kses_post( $s ) { return (string) $s; }
function wp_unslash( $s ) { return is_array( $s ) ? array_map( 'stripslashes', $s ) : stripslashes( (string) $s ); }
function absint( $v ) { return abs( (int) $v ); }
function size_format( $bytes ) { return round( $bytes / 1048576, 1 ) . ' MB'; }

function sanitize_hex_color( $color ) {
	$color = (string) $color;
	return preg_match( '|^#([A-Fa-f0-9]{3}){1,2}$|', $color ) ? $color : null;
}

function wp_parse_args( $args, $defaults = array() ) {
	return array_merge( $defaults, (array) $args );
}

function get_option( $key, $default = false ) {
	return $GLOBALS['stub_options'][ $key ] ?? $default;
}

function update_option( $key, $value ) { $GLOBALS['stub_options'][ $key ] = $value; return true; }
function add_option( $key, $value ) { $GLOBALS['stub_options'][ $key ] = $value; return true; }
function delete_option( $key ) { unset( $GLOBALS['stub_options'][ $key ] ); return true; }
function set_transient( $k, $v, $t = 0 ) { return true; }
function get_transient( $k ) { return false; }
function delete_transient( $k ) { return true; }

function current_user_can( $cap, ...$rest ) { return ! empty( $GLOBALS['stub_caps'][ $cap ] ); }
function get_current_user_id() { return 1; }
function current_time( $format ) { return gmdate( 'mysql' === $format ? 'Y-m-d H:i:s' : $format ); }

function add_action( ...$a ) { return true; }
function add_filter( ...$a ) { return true; }
function remove_filter( ...$a ) { return true; }
function add_shortcode( ...$a ) { return true; }
function do_action( ...$a ) { return true; }
function apply_filters( $tag, $value, ...$rest ) { return $value; }

function shortcode_atts( $pairs, $atts, $shortcode = '' ) {
	$out = array();
	foreach ( $pairs as $name => $default ) {
		$out[ $name ] = array_key_exists( $name, (array) $atts ) ? $atts[ $name ] : $default;
	}
	return $out;
}

/** Rejestr atrapy kategorii. */
function stub_add_term( int $id, string $slug, string $name ): void {
	$term = new WP_Term();
	$term->term_id = $id;
	$term->slug    = $slug;
	$term->name    = $name;

	$GLOBALS['stub_terms'][ $id ] = $term;
}

function get_term( $id, $tax = '' ) { return $GLOBALS['stub_terms'][ (int) $id ] ?? null; }

function get_term_by( $field, $value, $tax = '' ) {
	foreach ( $GLOBALS['stub_terms'] as $term ) {
		if ( 'slug' === $field && $term->slug === $value ) { return $term; }
		if ( 'name' === $field && $term->name === $value ) { return $term; }
	}
	return false;
}

function get_post_meta( $id, $key, $single = false ) { return $GLOBALS['stub_meta'][ $id ][ $key ] ?? ''; }
function update_post_meta( $id, $key, $value ) { $GLOBALS['stub_meta'][ $id ][ $key ] = $value; return true; }
function get_post_type( $id ) { return $GLOBALS['stub_post_types'][ $id ] ?? 'post'; }
function register_post_meta( ...$a ) { return true; }

class WP_Error {
	private $code;
	private $message;
	public function __construct( $code = '', $message = '' ) { $this->code = $code; $this->message = $message; }
	public function get_error_message() { return $this->message; }
	public function get_error_code() { return $this->code; }
}

function is_wp_error( $thing ) { return $thing instanceof WP_Error; }

class WP_Term {
	public $term_id = 0;
	public $slug = '';
	public $name = '';
	public $parent = 0;
}
class WP_Post {}
class WP_User { public $roles = array(); }
class WP_Role {}
class WP_Query {
	public $posts = array();
	public $found_posts = 0;
	public $max_num_pages = 0;

	public function __construct( $args = array() ) {}
	public function have_posts() { return ! empty( $this->posts ); }
}

$GLOBALS['stub_cache'] = array();

function wp_cache_get( $key, $group = '' ) { return $GLOBALS['stub_cache'][ $group ][ $key ] ?? false; }
function wp_cache_set( $key, $value, $group = '', $ttl = 0 ) { $GLOBALS['stub_cache'][ $group ][ $key ] = $value; return true; }
function wp_cache_flush_group( $group = '' ) { unset( $GLOBALS['stub_cache'][ $group ] ); return true; }
class WP_Screen { public $post_type = ''; public $taxonomy = ''; public $base = ''; }
