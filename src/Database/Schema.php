<?php
namespace WIR\Database;

defined( 'ABSPATH' ) || exit;

final class Schema {
	public const VERSION = '1.0.0';

	public static function install(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table = $wpdb->prefix . 'wir_reservations';
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			session_key varchar(100) NOT NULL,
			cart_item_key varchar(100) NOT NULL,
			product_id bigint(20) unsigned NOT NULL,
			stock_product_id bigint(20) unsigned NOT NULL,
			variation_id bigint(20) unsigned NOT NULL DEFAULT 0,
			quantity decimal(20,6) NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'active',
			expires_at datetime NOT NULL,
			order_id bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY stock_status_expiry (stock_product_id, status, expires_at),
			KEY session_status (session_key, status),
			KEY cart_item (cart_item_key),
			KEY order_id (order_id)
		) {$charset};";

		dbDelta( $sql );
		update_option( 'wir_db_version', self::VERSION, false );
	}
}
