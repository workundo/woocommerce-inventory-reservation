<?php
namespace WIR\Support;

defined( 'ABSPATH' ) || exit;

final class Logger {
	public static function log( string $event, array $context = array() ): void {
		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->info(
				$event . ' ' . wp_json_encode( $context ),
				array( 'source' => 'wc-inventory-reservation' )
			);
		}
	}
}
