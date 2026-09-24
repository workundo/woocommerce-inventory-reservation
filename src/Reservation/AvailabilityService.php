<?php
namespace WIR\Reservation;

defined( 'ABSPATH' ) || exit;

use WIR\Database\ReservationRepository;

final class AvailabilityService {
	public function __construct( private ReservationRepository $repository ) {}

	/**
	 * Identifies the actual stock-owning product.
	 */
	public function stock_product( \WC_Product $product ): ?\WC_Product {
		if ( ! $product->managing_stock() || $product->backorders_allowed() ) {
			return null;
		}
		
		$stock_id = (int) $product->get_stock_managed_by_id();
		$stock_product = wc_get_product( $stock_id );
		
		if ( ! $stock_product || ! $stock_product->managing_stock() || $stock_product->backorders_allowed() ) {
			return null;
		}
		
		return $stock_product;
	}

	/**
	 * Calculates the effective availability of a product, subtracting active reservations.
	 * Returns null if the product does not manage stock or allows backorders.
	 *
	 * Concurrency is guaranteed by the caller holding the _stock FOR UPDATE lock
	 * via ReservationService::acquire_stock_lock(). No additional locking is needed here.
	 */
	public function get_effective_availability( \WC_Product $product, int $exclude_id = 0 ): ?float {
		$stock_product = $this->stock_product( $product );
		if ( ! $stock_product ) {
			return null;
		}

		global $wpdb;

		// 1. Physical Stock — read directly from postmeta (already locked by caller)
		$stock = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_stock'",
				$stock_product->get_id()
			)
		);
		if ( null === $stock || '' === $stock ) {
			return null;
		}
		$stock = (float) $stock;

		// 2. WC Native Held Stock
		$native_held = 0;
		$reserved_table = $wpdb->prefix . 'wc_reserved_stock';
		// Check if WC reserved stock table exists (WC 4.3+)
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $reserved_table ) ) === $reserved_table ) {
			$native_held = (float) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT SUM(stock_quantity) FROM {$reserved_table} WHERE product_id = %d",
					$stock_product->get_id()
				)
			);
		}

		// 3. Custom Held Stock
		$custom_held = $this->repository->sum_active_for_stock_product( $stock_product->get_id(), $exclude_id );
		
		return max( 0, $stock - $native_held - $custom_held );
	}
}
