<?php
/**
 * Harness for the Update Policy unit tests.
 *
 * There is no WordPress here, so this defines the small slice of it the plugin
 * touches and the tests require the real files. Stub state lives in globals so
 * a test can rearrange the "site" between assertions.
 *
 * Run: php tests/policy-test.php
 */

define( 'ABSPATH', '/tmp/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );

$GLOBALS['up_test_pass'] = 0;
$GLOBALS['up_test_fail'] = 0;

function up_test_ok( $cond, $label ) {
	if ( $cond ) {
		$GLOBALS['up_test_pass']++;
		return;
	}
	$GLOBALS['up_test_fail']++;
	echo "FAIL: $label\n";
}

function up_test_eq( $actual, $expected, $label ) {
	if ( $actual === $expected ) {
		$GLOBALS['up_test_pass']++;
		return;
	}
	$GLOBALS['up_test_fail']++;
	echo "FAIL: $label\n";
	echo '  expected: ' . var_export( $expected, true ) . "\n";
	echo '  actual:   ' . var_export( $actual, true ) . "\n";
}

function up_test_summary() {
	printf( "\n%d passed, %d failed\n", $GLOBALS['up_test_pass'], $GLOBALS['up_test_fail'] );
	return $GLOBALS['up_test_fail'];
}

/* ------------------------------------------------------------ WP stubs */

class WP_Error {
	public $code; public $message;
	public function __construct( $code = '', $message = '' ) { $this->code = $code; $this->message = $message; }
	public function get_error_message() { return $this->message; }
}
function is_wp_error( $thing ) { return $thing instanceof WP_Error; }

function __( $text, $domain = null ) { return $text; }
function esc_html__( $text, $domain = null ) { return $text; }
function apply_filters( $tag, $value ) { return $value; }
function add_action() {}

// Filters are recorded, not run: a test can ask whether something was hooked.
$GLOBALS['up_stub_filters'] = array();
function add_filter( $tag = '', $callback = null ) { $GLOBALS['up_stub_filters'][ $tag ] = true; }
function remove_filter( $tag = '', $callback = null ) { unset( $GLOBALS['up_stub_filters'][ $tag ] ); }
function up_stub_has_filter( $tag ) { return isset( $GLOBALS['up_stub_filters'][ $tag ] ); }

$GLOBALS['up_stub_options'] = array();
function get_option( $key, $default = false ) {
	return array_key_exists( $key, $GLOBALS['up_stub_options'] ) ? $GLOBALS['up_stub_options'][ $key ] : $default;
}
function update_option( $key, $value ) { $GLOBALS['up_stub_options'][ $key ] = $value; return true; }
// On a single site core's site-option functions are the option functions.
function get_site_option( $key, $default = false ) { return get_option( $key, $default ); }
function update_site_option( $key, $value ) { return update_option( $key, $value ); }

$GLOBALS['up_stub_site_transients'] = array();
function get_site_transient( $key ) {
	return array_key_exists( $key, $GLOBALS['up_stub_site_transients'] ) ? $GLOBALS['up_stub_site_transients'][ $key ] : false;
}
function set_site_transient( $key, $value, $ttl = 0 ) { $GLOBALS['up_stub_site_transients'][ $key ] = $value; return true; }

$GLOBALS['up_stub_denied_caps'] = array();
function current_user_can( $cap ) { return ! in_array( $cap, $GLOBALS['up_stub_denied_caps'], true ); }
$GLOBALS['up_stub_multisite'] = false;
function is_multisite() { return (bool) $GLOBALS['up_stub_multisite']; }
