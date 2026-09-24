<?php
namespace WIR\WooCommerce;

defined( 'ABSPATH' ) || exit;

use WIR\Reservation\ReservationService;

final class CartIntegration {
	public function __construct( private ReservationService $service ) {}

	public function register(): void {
		add_action( 'woocommerce_add_to_cart', array( $this, 'reserve_after_add' ), 20, 6 );
		add_action( 'woocommerce_after_cart_item_quantity_update', array( $this, 'quantity_updated' ), 20, 4 );
		add_action( 'woocommerce_cart_item_removed', array( $this, 'item_removed' ), 20, 2 );
		add_action( 'woocommerce_cart_emptied', array( $this, 'cart_emptied' ) );
		add_action( 'woocommerce_check_cart_items', array( $this, 'validate_cart_reservations' ) );
	}

	public function reserve_after_add( string $cart_item_key, int $product_id, float $quantity, int $variation_id, array $variation, array $cart_item_data ): void {
		if ( ! WC()->cart ) {
			return;
		}
		
		$product = $variation_id ? wc_get_product( $variation_id ) : wc_get_product( $product_id );
		if ( ! $product ) {
			return;
		}
		
		$result = $this->service->reserve( $cart_item_key, $product, $quantity );
		if ( is_wp_error( $result ) ) {
			WC()->cart->remove_cart_item( $cart_item_key );
			wc_add_notice( $result->get_error_message(), 'error' );
			return;
		}
		
		if ( $result ) {
			WC()->cart->cart_contents[ $cart_item_key ]['_wir_reservation_id'] = (int) $result;
		}
	}

	public function quantity_updated( string $cart_item_key, int $quantity, int $old_quantity, \WC_Cart $cart ): void {
		$reservation = $this->service->repository()->find_active_for_cart_item( $this->service->session_key(), $cart_item_key );
		if ( ! $reservation ) {
			return;
		}
		$result = $this->service->adjust( (int) $reservation['id'], $quantity );
		if ( is_wp_error( $result ) ) {
			$cart->set_quantity( $cart_item_key, $old_quantity, false );
			wc_add_notice( $result->get_error_message(), 'error' );
		}
	}

	public function item_removed( string $cart_item_key, \WC_Cart $cart ): void {
		$reservation = $this->service->repository()->find_active_for_cart_item( $this->service->session_key(), $cart_item_key );
		if ( $reservation ) {
			$this->service->release( (int) $reservation['id'] );
		}
	}

	public function cart_emptied(): void {
		$this->service->release_session();
	}

	public function validate_cart_reservations(): void {
		if ( ! WC()->cart ) {
			return;
		}
		
		foreach ( WC()->cart->get_cart() as $key => $item ) {
			$product = $item['data'] ?? null;
			if ( ! $product instanceof \WC_Product ) {
				continue;
			}
			
			$stock_product = $this->service->availability()->stock_product( $product );
			if ( ! $stock_product ) {
				continue;
			}
			
			$reservation = $this->service->repository()->find_active_for_cart_item( $this->service->session_key(), $key );
			if ( ! $reservation || (float) $reservation['quantity'] < (float) $item['quantity'] ) {
				wc_add_notice(
					sprintf( __( 'Your reservation for "%s" has expired. Please add it to your cart again.', 'wc-inventory-reservation' ), $product->get_name() ),
					'error'
				);
				WC()->cart->remove_cart_item( $key );
			}
		}
	}
}
