<?php
namespace WIR\Database;

defined( 'ABSPATH' ) || exit;

final class ReservationRepository {
	private \wpdb $db;
	private string $table;

	public function __construct() {
		global $wpdb;
		$this->db    = $wpdb;
		$this->table = $wpdb->prefix . 'wir_reservations';
	}

	public function table(): string {
		return $this->table;
	}

	public function find_active_for_cart_item( string $session_key, string $cart_item_key ): ?array {
		$sql = $this->db->prepare(
			"SELECT * FROM {$this->table} WHERE session_key = %s AND cart_item_key = %s AND status = 'active' LIMIT 1",
			$session_key,
			$cart_item_key
		);
		$row = $this->db->get_row( $sql, ARRAY_A );
		return $row ?: null;
	}

	public function get_active_for_session( string $session_key ): array {
		$sql = $this->db->prepare(
			"SELECT * FROM {$this->table} WHERE session_key = %s AND status = 'active' ORDER BY id ASC",
			$session_key
		);
		return $this->db->get_results( $sql, ARRAY_A ) ?: array();
	}

	public function get_by_id( int $id ): ?array {
		$sql = $this->db->prepare( "SELECT * FROM {$this->table} WHERE id = %d", $id );
		$row = $this->db->get_row( $sql, ARRAY_A );
		return $row ?: null;
	}

	/**
	 * Calculates the sum of active reservations for a given stock product.
	 * Concurrency is guaranteed by the parent _stock FOR UPDATE lock, so FOR SHARE is no longer needed.
	 */
	public function sum_active_for_stock_product( int $stock_product_id, int $exclude_id = 0 ): float {
		$sql = $this->db->prepare(
			"SELECT COALESCE(SUM(quantity), 0) FROM {$this->table}
			WHERE stock_product_id = %d AND status = 'active' AND expires_at > %s AND id <> %d",
			$stock_product_id,
			gmdate( 'Y-m-d H:i:s' ),
			$exclude_id
		);
		return (float) $this->db->get_var( $sql );
	}

	public function insert( array $data ): int {
		$now = current_time( 'mysql', true );
		$result = $this->db->insert(
			$this->table,
			array(
				'session_key'      => $data['session_key'],
				'cart_item_key'    => $data['cart_item_key'],
				'product_id'       => (int) $data['product_id'],
				'stock_product_id' => (int) $data['stock_product_id'],
				'variation_id'     => (int) $data['variation_id'],
				'quantity'         => (float) $data['quantity'],
				'status'           => 'active',
				'expires_at'       => $data['expires_at'],
				'order_id'         => (int) ( $data['order_id'] ?? 0 ),
				'created_at'       => $now,
				'updated_at'       => $now,
			),
			array( '%s', '%s', '%d', '%d', '%d', '%f', '%s', '%s', '%d', '%s', '%s' )
		);
		return $result ? (int) $this->db->insert_id : 0;
	}

	public function update_quantity( int $id, float $quantity ): bool {
		return false !== $this->db->update(
			$this->table,
			array(
				'quantity'   => $quantity,
				'updated_at' => current_time( 'mysql', true ),
			),
			array( 'id' => $id ),
			array( '%f', '%s' ),
			array( '%d' )
		);
	}

	public function mark_status( int $id, string $status, int $order_id = 0, string $expected_status = '' ): bool {
		$allowed = array( 'active', 'checkout_held', 'released', 'confirmed', 'expired', 'cancelled', 'failed' );
		if ( ! in_array( $status, $allowed, true ) ) {
			return false;
		}

		$where = array( 'id' => $id );
		$where_format = array( '%d' );

		// Enforce atomic state transitions
		if ( $expected_status ) {
			$where['status'] = $expected_status;
			$where_format[] = '%s';
		} elseif ( 'active' !== $status ) {
			$where['status'] = 'active';
			$where_format[] = '%s';
		}

		$result = $this->db->update(
			$this->table,
			array(
				'status'     => $status,
				'order_id'   => $order_id,
				'updated_at' => current_time( 'mysql', true ),
			),
			$where,
			array( '%s', '%d', '%s' ),
			$where_format
		);
		
		return $result > 0;
	}

	public function get_expired_ids( int $limit = 500 ): array {
		$limit = max( 1, min( 5000, $limit ) );
		$sql = "SELECT id FROM {$this->table}
			WHERE status = 'active' AND expires_at <= %s
			ORDER BY expires_at ASC LIMIT {$limit}";
		return $this->db->get_col( $this->db->prepare( $sql, gmdate( 'Y-m-d H:i:s' ) ) );
	}

	public function get_admin_rows( int $page, int $per_page, string $status = '' ): array {
		$offset = max( 0, ( $page - 1 ) * $per_page );
		$where = '';
		$args = array();
		if ( $status && in_array( $status, array( 'active', 'checkout_held', 'expired', 'released', 'confirmed', 'cancelled', 'failed' ), true ) ) {
			$where = ' WHERE status = %s';
			$args[] = $status;
		}
		$args[] = $per_page;
		$args[] = $offset;
		$sql = "SELECT * FROM {$this->table}{$where} ORDER BY id DESC LIMIT %d OFFSET %d";
		return $this->db->get_results( $this->db->prepare( $sql, ...$args ), ARRAY_A ) ?: array();
	}

	public function count( string $status = '' ): int {
		$sql = "SELECT COUNT(*) FROM {$this->table}";
		if ( $status && in_array( $status, array( 'active', 'checkout_held', 'expired', 'released', 'confirmed', 'cancelled', 'failed' ), true ) ) {
			$sql .= $this->db->prepare( ' WHERE status = %s', $status );
		}
		return (int) $this->db->get_var( $sql );
	}
}
