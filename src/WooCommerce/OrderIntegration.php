<?php
namespace WIR\WooCommerce;

defined( 'ABSPATH' ) || exit;

use WIR\Reservation\ReservationService;
use WIR\Reservation\ReservationStatus;

final class OrderIntegration {
	public function __construct( private ReservationService $service ) {}

	public function register(): void {
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'copy_cart_reservation_to_order_item' ), 20, 4 );
		add_action( 'woocommerce_checkout_create_order', array( $this, 'copy_session_to_order' ), 20, 2 );
		add_action( 'woocommerce_checkout_order_created', array( $this, 'checkout_order_created' ), 20 );
		
		add_action( 'woocommerce_payment_complete', array( $this, 'payment_complete' ), 20, 2 );
		add_action( 'woocommerce_order_status_cancelled', array( $this, 'order_cancelled' ), 5 );
		add_action( 'woocommerce_order_status_failed', array( $this, 'order_failed' ), 5 );
	}

	public function copy_cart_reservation_to_order_item( \WC_Order_Item_Product $item, string $cart_item_key, array $values, \WC_Order $order ): void {
		if ( ! empty( $values['_wir_reservation_id'] ) ) {
			$item->add_meta_data( '_wir_reservation_id', absint( $values['_wir_reservation_id'] ), true );
		}
		$item->add_meta_data( '_wir_cart_item_key', sanitize_key( $cart_item_key ), true );
	}

	public function copy_session_to_order( \WC_Order $order, array $data ): void {
		$session = $this->service->session_key();
		if ( $session ) {
			$order->update_meta_data( '_wir_session_key', $session );
		}
	}

	public function checkout_order_created( \WC_Order $order ): void {
		$this->service->checkout_handoff( $order );
	}

	public function payment_complete( int $order_id, string $transaction_id = '' ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		$this->service->confirm_order( $order );
	}

	public function order_cancelled( int $order_id ): void {
		$this->release_order_reservations( $order_id, ReservationStatus::CANCELLED );
	}

	public function order_failed( int $order_id ): void {
		$this->release_order_reservations( $order_id, ReservationStatus::FAILED );
	}

	private function release_order_reservations( int $order_id, string $status ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		foreach ( $order->get_items( 'line_item' ) as $item ) {
			$id = absint( $item->get_meta( '_wir_reservation_id', true ) );
			if ( $id ) {
				$this->service->release( $id, $status );
			}
		}
	}
}
