<?php
namespace WIR\WooCommerce;

defined( 'ABSPATH' ) || exit;

use WIR\Reservation\AvailabilityService;
use WIR\Database\ReservationRepository;

final class ProductIntegration {
	public function __construct(
		private AvailabilityService $availability,
		private ReservationRepository $repository
	) {}

	public function register(): void {
		add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'validate_add_to_cart' ), 20, 5 );
	}

	public function validate_add_to_cart( bool $passed, int $product_id, float $quantity, int $variation_id = 0, array $variations = array() ): bool {
		if ( ! $passed ) {
			return false;
		}
		
		$product = $variation_id ? wc_get_product( $variation_id ) : wc_get_product( $product_id );
		if ( ! $product ) {
			return false;
		}
		
		$available = $this->availability->get_effective_availability( $product );
		
		if ( null !== $available && $available < wc_stock_amount( $quantity ) ) {
			wc_add_notice( __( 'Sorry, that quantity is no longer available due to existing reservations.', 'wc-inventory-reservation' ), 'error' );
			return false;
		}
		
		return $passed;
	}
}
