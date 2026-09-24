<?php
namespace WIR\Reservation;

defined( 'ABSPATH' ) || exit;

use WIR\Database\ReservationRepository;
use WIR\Support\Logger;

final class ReservationService {
	public function __construct(
		private ReservationRepository $repository,
		private AvailabilityService $availability
	) {}

	public function hold_minutes(): int {
		$settings = get_option( 'wir_settings', array( 'hold_minutes' => 15 ) );
		$minutes = isset( $settings['hold_minutes'] ) ? absint( $settings['hold_minutes'] ) : 15;
		return max( 1, min( 1440, $minutes ) );
	}

	public function session_key(): string {
		if ( function_exists( 'WC' ) && WC()->session ) {
			$key = (string) WC()->session->get_customer_id();
			if ( $key !== '' ) {
				return substr( $key, 0, 100 );
			}
		}
		return '';
	}

	/**
	 * Natively aligns with WooCommerce checkout by safely locking the exact _stock row.
	 * This serves as the master serialization mutex for ALL Custom Reservation mutators.
	 * 
	 * @throws \RuntimeException If lock cannot be acquired.
	 */
	public function acquire_stock_lock( int $stock_id ): void {
		global $wpdb;

		// Atomic initialization via a NOT EXISTS FOR UPDATE subquery
		// This prevents duplicate rows if multiple sessions race to initialize a missing _stock row
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value)
				SELECT %d, '_stock', '0' FROM DUAL
				WHERE NOT EXISTS (
					SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_stock' FOR UPDATE
				)",
				$stock_id,
				$stock_id
			)
		);

		$locked = $wpdb->get_var(
			$wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_stock' FOR UPDATE", $stock_id )
		);
		
		if ( null === $locked ) {
			throw new \RuntimeException( 'Stock owner _stock row could not be locked.' );
		}
	}

	public function reserve( string $cart_item_key, \WC_Product $product, float $quantity ): int|\WP_Error {
		$session = $this->session_key();
		$quantity = wc_stock_amount( $quantity );
		if ( $session === '' || $quantity <= 0 ) {
			return new \WP_Error( 'wir_invalid_reservation', __( 'Unable to create the reservation.', 'wc-inventory-reservation' ) );
		}

		$stock_product = $this->availability->stock_product( $product );
		if ( ! $stock_product ) {
			return 0; // Not stock managed
		}

		global $wpdb;
		$stock_id = (int) $stock_product->get_id();
		$now = current_time( 'mysql', true );
		$expires = gmdate( 'Y-m-d H:i:s', time() + ( $this->hold_minutes() * MINUTE_IN_SECONDS ) );

		$wpdb->query( 'START TRANSACTION' );
		try {
			$this->acquire_stock_lock( $stock_id );

			// 2. Check for existing cart item inside the lock (prevents duplicate rows)
			$existing = $this->repository->find_active_for_cart_item( $session, $cart_item_key );
			if ( $existing ) {
				$id = (int) $existing['id'];
				$current = (float) $existing['quantity'];
				if ( abs( $current - $quantity ) < 0.000001 ) {
					$wpdb->query( 'COMMIT' );
					return $id;
				}
				
				$available = $this->availability->get_effective_availability( $product, $id );
				if ( null !== $available && $available < $quantity ) {
					$wpdb->query( 'ROLLBACK' );
					return new \WP_Error( 'wir_insufficient_stock', __( 'There is not enough inventory for that quantity.', 'wc-inventory-reservation' ) );
				}
				
				$this->repository->update_quantity( $id, $quantity );
				$wpdb->query( 'COMMIT' );
				return $id;
			}

			// Availability is safe to read here because we hold the _stock FOR UPDATE lock
			$available = $this->availability->get_effective_availability( $product, 0 );
			if ( null === $available ) {
				$wpdb->query( 'ROLLBACK' );
				return 0;
			}

			if ( $available < $quantity ) {
				$wpdb->query( 'ROLLBACK' );
				return new \WP_Error(
					'wir_insufficient_stock',
					sprintf(
						/* translators: %s: product name */
						__( 'Only %s item(s) remain available for reservation.', 'wc-inventory-reservation' ),
						wc_format_stock_quantity_for_display( $available, $product )
					)
				);
			}

			$id = $this->repository->insert(
				array(
					'session_key'      => $session,
					'cart_item_key'    => $cart_item_key,
					'product_id'       => $product->get_id(),
					'stock_product_id' => $stock_id,
					'variation_id'     => $product->is_type( 'variation' ) ? $product->get_id() : 0,
					'quantity'         => $quantity,
					'expires_at'       => $expires,
				)
			);
			if ( ! $id ) {
				throw new \RuntimeException( 'Reservation insert failed.' );
			}

			$wpdb->query( 'COMMIT' );
			
			do_action( 'wir_reservation_created', $id, strtotime( $expires . ' UTC' ) );
			
			return $id;
		} catch ( \Throwable $e ) {
			$wpdb->query( 'ROLLBACK' );
			Logger::log( 'reservation_create_failed', array( 'message' => $e->getMessage(), 'product_id' => $stock_id ) );
			return new \WP_Error( 'wir_reservation_failed', __( 'Could not reserve inventory. Please try again.', 'wc-inventory-reservation' ) );
		}
	}

	public function adjust( int $id, float $quantity ): int|\WP_Error {
		$row = $this->repository->get_by_id( $id );
		if ( ! $row || ReservationStatus::ACTIVE !== $row['status'] ) {
			return new \WP_Error( 'wir_reservation_missing', __( 'The reservation is no longer active.', 'wc-inventory-reservation' ) );
		}
		$current = (float) $row['quantity'];
		if ( abs( $current - $quantity ) < 0.000001 ) {
			return $id;
		}

		if ( $quantity <= 0 ) {
			$this->repository->mark_status( $id, ReservationStatus::RELEASED );
			return $id;
		}

		global $wpdb;
		$stock_id = (int) $row['stock_product_id'];
		$product = wc_get_product( $row['product_id'] );
		if ( ! $product ) {
			return new \WP_Error( 'wir_invalid_product', __( 'Product not found.', 'wc-inventory-reservation' ) );
		}

		$wpdb->query( 'START TRANSACTION' );
		try {
			$this->acquire_stock_lock( $stock_id );
			
			$available = $this->availability->get_effective_availability( $product, $id );
			if ( null !== $available && $available < $quantity ) {
				$wpdb->query( 'ROLLBACK' );
				return new \WP_Error( 'wir_insufficient_stock', __( 'There is not enough inventory for that quantity.', 'wc-inventory-reservation' ) );
			}
			
			$this->repository->update_quantity( $id, $quantity );
			$wpdb->query( 'COMMIT' );
			return $id;
		} catch ( \Throwable $e ) {
			$wpdb->query( 'ROLLBACK' );
			Logger::log( 'reservation_adjust_failed', array( 'message' => $e->getMessage(), 'reservation_id' => $id ) );
			return new \WP_Error( 'wir_adjust_failed', __( 'Could not update the inventory reservation.', 'wc-inventory-reservation' ) );
		}
	}

	public function release( int $id, string $status = ReservationStatus::RELEASED ): bool {
		$row = $this->repository->get_by_id( $id );
		if ( ! $row ) {
			return false;
		}

		$current_status = $row['status'];

		// Only allow releasing from non-terminal states
		if ( ! in_array( $current_status, array( ReservationStatus::ACTIVE, ReservationStatus::CHECKOUT_HELD ), true ) ) {
			return false;
		}

		if ( ! ReservationStatus::is_valid_transition( $current_status, $status ) ) {
			return false;
		}

		global $wpdb;
		$wpdb->query( 'START TRANSACTION' );
		try {
			// Must acquire stock lock before mutating a reservation that affects availability
			$this->acquire_stock_lock( (int) $row['stock_product_id'] );

			$result = $this->repository->mark_status( $id, $status, 0, $current_status );
			$wpdb->query( 'COMMIT' );

			if ( $result ) {
				Logger::log( 'reservation_released', array( 'reservation_id' => $id, 'from' => $current_status, 'to' => $status ) );
			}
			return $result;
		} catch ( \Throwable $e ) {
			$wpdb->query( 'ROLLBACK' );
			Logger::log( 'reservation_release_failed', array( 'reservation_id' => $id, 'reason' => $e->getMessage() ) );
			return false;
		}
	}

	public function release_session(): void {
		foreach ( $this->repository->get_active_for_session( $this->session_key() ) as $row ) {
			$this->release( (int) $row['id'] );
		}
	}

	public function expire_due( int $limit = 500 ): int {
		$ids = $this->repository->get_expired_ids( $limit );
		$count = 0;
		foreach ( $ids as $id ) {
			if ( $this->release( (int) $id, ReservationStatus::EXPIRED ) ) {
				++$count;
			}
		}
		return $count;
	}

	public function confirm_order( \WC_Order $order ): bool {
		$items = $order->get_items( 'line_item' );
		$session = (string) $order->get_meta( '_wir_session_key', true );
		if ( ! $session ) {
			$session = $this->session_key();
		}
		if ( ! $session ) {
			return false;
		}

		/*
		 * Do not reduce WooCommerce stock here. WooCommerce's own
		 * woocommerce_payment_complete flow performs the authoritative stock
		 * reduction. The reservation has already protected the units.
		 */
		foreach ( $items as $item ) {
			$cart_key = (string) $item->get_meta( '_wir_cart_item_key', true );
			if ( ! $cart_key ) {
				continue;
			}
			$reservation = $this->repository->find_active_for_cart_item( $session, $cart_key );
			// Also check checkout_held since we might have transitioned it during checkout
			if ( ! $reservation ) {
				$sql = $this->repository()->table();
				global $wpdb;
				$reservation = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$sql} WHERE session_key = %s AND cart_item_key = %s AND status = 'checkout_held' LIMIT 1", $session, $cart_key ), ARRAY_A );
			}

			if ( $reservation && (float) $reservation['quantity'] >= (float) $item->get_quantity() ) {
				// Atomic state transition guarantees no terminal overwrites
				$affected = $this->repository->mark_status( (int) $reservation['id'], ReservationStatus::CONFIRMED, $order->get_id(), $reservation['status'] );
				if ( ! $affected ) {
					Logger::log( 'reservation_confirmation_already_terminal', array( 'reservation_id' => $reservation['id'], 'order_id' => $order->get_id() ) );
				}
			} else {
				Logger::log( 'reservation_confirmation_missing_or_insufficient', array( 'cart_key' => $cart_key, 'order_id' => $order->get_id() ) );
			}
		}
		return true;
	}

	public function checkout_handoff( \WC_Order $order ): void {
		global $wpdb;

		// Group items by stock_product_id to avoid deadlocks by locking in consistent order
		// For simplicity, process one by one
		foreach ( $order->get_items( 'line_item' ) as $item ) {
			$id = absint( $item->get_meta( '_wir_reservation_id', true ) );
			if ( ! $id ) {
				continue;
			}
			$row = $this->repository->get_by_id( $id );
			if ( ! $row || ReservationStatus::ACTIVE !== $row['status'] ) {
				continue;
			}

			$wpdb->query( 'START TRANSACTION' );
			try {
				$this->acquire_stock_lock( (int) $row['stock_product_id'] );
				
				// Verify WooCommerce actually holds this stock natively
				$product = $item->get_product();
				if ( $product ) {
					// Use WooCommerce native API to check held stock
					$native_held = wc_get_held_stock_quantity( $product, 0 );
					
					// As long as native_held > 0, we can safely hand off.
					// If native_held is 0, WC failed to hold it, so we leave it ACTIVE as protection.
					if ( $native_held > 0 ) {
						$this->repository->mark_status( $id, ReservationStatus::CHECKOUT_HELD, $order->get_id(), ReservationStatus::ACTIVE );
						Logger::log( 'reservation_checkout_handoff', array( 'reservation_id' => $id, 'order_id' => $order->get_id() ) );
					} else {
						Logger::log( 'checkout_handoff_skipped_no_native_hold', array( 'reservation_id' => $id, 'order_id' => $order->get_id() ) );
					}
				}
				$wpdb->query( 'COMMIT' );
			} catch ( \Throwable $e ) {
				$wpdb->query( 'ROLLBACK' );
				Logger::log( 'reservation_checkout_handoff_failed', array( 'reservation_id' => $id, 'reason' => $e->getMessage() ) );
			}
		}
	}

	public function admin_release( int $id ): bool {
		return $this->release( $id, ReservationStatus::RELEASED );
	}

	public function repository(): ReservationRepository {
		return $this->repository;
	}

	public function availability(): AvailabilityService {
		return $this->availability;
	}
}
