<?php
namespace WIR;

defined( 'ABSPATH' ) || exit;

use WIR\Database\Schema;
use WIR\Database\ReservationRepository;
use WIR\Reservation\AvailabilityService;
use WIR\Reservation\ReservationService;
use WIR\Cart\CartManager;
use WIR\WooCommerce\CartIntegration;
use WIR\WooCommerce\ProductIntegration;
use WIR\WooCommerce\OrderIntegration;
use WIR\Scheduler\ExpirationScheduler;
use WIR\Admin\Admin;

final class Plugin {
	private static ?ReservationService $service = null;

	public static function activate(): void {
		Schema::install();
		if ( false === get_option( 'wir_settings' ) ) {
			add_option( 'wir_settings', array( 'hold_minutes' => 15 ), '', false );
		}
	}

	public static function deactivate(): void {
		ExpirationScheduler::unschedule();
	}

	public static function boot(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		$repository = new ReservationRepository();
		$availability = new AvailabilityService( $repository );
		self::$service = new ReservationService( $repository, $availability );

		( new CartIntegration( self::$service ) )->register();
		( new ProductIntegration( $availability, $repository ) )->register();
		( new OrderIntegration( self::$service ) )->register();
		( new CartManager( self::$service ) )->register();
		( new ExpirationScheduler( self::$service ) )->register();

		if ( is_admin() ) {
			( new Admin( self::$service ) )->register();
		}
	}
}
