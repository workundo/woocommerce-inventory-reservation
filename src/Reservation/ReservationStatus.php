<?php
namespace WIR\Reservation;

defined( 'ABSPATH' ) || exit;

final class ReservationStatus {
	public const ACTIVE        = 'active';
	public const CHECKOUT_HELD = 'checkout_held';
	public const EXPIRED       = 'expired';
	public const RELEASED      = 'released';
	public const CONFIRMED     = 'confirmed';
	public const FAILED        = 'failed';
	public const CANCELLED     = 'cancelled';

	public static function all(): array {
		return array(
			self::ACTIVE,
			self::CHECKOUT_HELD,
			self::EXPIRED,
			self::RELEASED,
			self::CONFIRMED,
			self::FAILED,
			self::CANCELLED,
		);
	}

	public static function is_valid_transition( string $from, string $to ): bool {
		if ( $from === $to ) {
			return true;
		}

		if ( self::ACTIVE === $from ) {
			return in_array( $to, array( self::CHECKOUT_HELD, self::EXPIRED, self::RELEASED ), true );
		}

		if ( self::CHECKOUT_HELD === $from ) {
			return in_array( $to, array( self::CONFIRMED, self::RELEASED, self::FAILED, self::CANCELLED ), true );
		}

		return false;
	}
}
