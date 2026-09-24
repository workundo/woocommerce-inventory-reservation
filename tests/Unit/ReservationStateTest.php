<?php
namespace WIR\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WIR\Reservation\ReservationStatus;
use WIR\Reservation\Reservation;

class ReservationStateTest extends TestCase {
	public function test_valid_transitions(): void {
		$this->assertTrue( ReservationStatus::is_valid_transition( ReservationStatus::ACTIVE, ReservationStatus::RELEASED ) );
		$this->assertTrue( ReservationStatus::is_valid_transition( ReservationStatus::ACTIVE, ReservationStatus::EXPIRED ) );
		$this->assertTrue( ReservationStatus::is_valid_transition( ReservationStatus::ACTIVE, ReservationStatus::CONFIRMED ) );
		$this->assertTrue( ReservationStatus::is_valid_transition( ReservationStatus::ACTIVE, ReservationStatus::FAILED ) );
		$this->assertTrue( ReservationStatus::is_valid_transition( ReservationStatus::ACTIVE, ReservationStatus::CANCELLED ) );
	}

	public function test_invalid_transitions(): void {
		$this->assertFalse( ReservationStatus::is_valid_transition( ReservationStatus::RELEASED, ReservationStatus::CONFIRMED ) );
		$this->assertFalse( ReservationStatus::is_valid_transition( ReservationStatus::EXPIRED, ReservationStatus::ACTIVE ) );
		$this->assertFalse( ReservationStatus::is_valid_transition( ReservationStatus::CONFIRMED, ReservationStatus::RELEASED ) );
	}

	public function test_idempotent_transitions(): void {
		$this->assertTrue( ReservationStatus::is_valid_transition( ReservationStatus::RELEASED, ReservationStatus::RELEASED ) );
		$this->assertTrue( ReservationStatus::is_valid_transition( ReservationStatus::CONFIRMED, ReservationStatus::CONFIRMED ) );
	}

	public function test_reservation_entity(): void {
		$data = array(
			'id'               => 1,
			'session_key'      => 'sess_123',
			'cart_item_key'    => 'cart_123',
			'product_id'       => 10,
			'stock_product_id' => 10,
			'variation_id'     => 0,
			'quantity'         => 2.5,
			'status'           => ReservationStatus::ACTIVE,
			'expires_at'       => gmdate( 'Y-m-d H:i:s', time() - 100 ), // expired
			'order_id'         => 0,
		);

		$reservation = Reservation::from_array( $data );
		$this->assertTrue( $reservation->is_active() );
		$this->assertTrue( $reservation->has_expired() );
		$this->assertEquals( 2.5, $reservation->quantity );
	}
}
