<?php
/**
 * Plugin Name: Secure WooCommerce Inventory Reservation
 * Description: Transaction-safe cart inventory reservations for WooCommerce.
 * Version: 1.0.0
 * Requires at least: 6.6
 * Requires PHP: 8.1
 * Requires Plugins: woocommerce
 * Author: Arun Sudheer E
 * License: GPL-2.0-or-later
 * Text Domain: wc-inventory-reservation
 */

defined( 'ABSPATH' ) || exit;

define( 'WIR_VERSION', '1.0.0' );
define( 'WIR_FILE', __FILE__ );
define( 'WIR_DIR', plugin_dir_path( __FILE__ ) );
define( 'WIR_URL', plugin_dir_url( __FILE__ ) );

// PSR-4 Autoloader
spl_autoload_register( function ( $class ) {
	$prefix   = 'WIR\\';
	$base_dir = WIR_DIR . 'src/';
	$len      = strlen( $prefix );

	if ( strncmp( $prefix, $class, $len ) !== 0 ) {
		return;
	}

	$relative_class = substr( $class, $len );
	$file           = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

	if ( file_exists( $file ) ) {
		require $file;
	}
} );

register_activation_hook( __FILE__, array( 'WIR\\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'WIR\\Plugin', 'deactivate' ) );

add_action( 'plugins_loaded', static function () {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}
	\WIR\Plugin::boot();
} );
