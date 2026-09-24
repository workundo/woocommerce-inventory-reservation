<?php
namespace WIR\Reservation;

defined( 'ABSPATH' ) || exit;

final class Reservation {
	public function __construct(
		public readonly int $id,
		public readonly string $session_key,
		public readonly string $cart_item_key,
		public readonly int $product_id,
		public readonly int $stock_product_id,
		public readonly int $variation_id,
		public readonly float $quantity,
		public readonly string $status,
		public readonly string $expires_at,
		public readonly int $order_id = 0,
		public readonly string $created_at = '',
		public readonly string $updated_at = ''
	) {}

	public static function from_array( array $data ): self {
		return new self(
			(int) $data['id'],
			$data['session_key'],
			$data['cart_item_key'],
			(int) $data['product_id'],
			(int) $data['stock_product_id'],
			(int) $data['variation_id'],
			(float) $data['quantity'],
			$data['status'],
			$data['expires_at'],
			(int) ( $data['order_id'] ?? 0 ),
			$data['created_at'] ?? '',
			$data['updated_at'] ?? ''
		);
	}

	public function is_active(): bool {
		return ReservationStatus::ACTIVE === $this->status;
	}

	public function has_expired(): bool {
		return strtotime( $this->expires_at . ' UTC' ) <= time();
	}
}
