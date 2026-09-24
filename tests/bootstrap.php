<?php
/**
 * PHPUnit bootstrap file.
 */

define( 'ABSPATH', sys_get_temp_dir() . '/wordpress/' );
define( 'WIR_DIR', dirname( __DIR__ ) . '/' );

require_once WIR_DIR . 'wc-inventory-reservation.php';

// Stub out some WordPress/WooCommerce functions for basic unit tests
if ( ! function_exists( 'add_action' ) ) {
	function add_action() {}
	function add_filter() {}
	function do_action() {}
	function apply_filters( $t, $v ) { return $v; }
	function __() { return func_get_arg( 0 ); }
	function esc_html() { return func_get_arg( 0 ); }
	function get_option() { return false; }
	function current_time() { return '2025-01-01 00:00:00'; }
	function wp_json_encode( $data ) { return json_encode( $data ); }
}

class WP_Error extends Exception {
	private $code;
	public function __construct( $code, $message ) {
		parent::__construct( $message );
		$this->code = $code;
	}
	public function get_error_message() { return $this->getMessage(); }
}

function is_wp_error( $thing ) {
	return $thing instanceof WP_Error;
}
