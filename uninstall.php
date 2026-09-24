<?php
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;
$table = $wpdb->prefix . 'wir_reservations';
$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
delete_option( 'wir_settings' );
delete_option( 'wir_db_version' );

if ( function_exists( 'as_unschedule_all_actions' ) ) {
	as_unschedule_all_actions( 'wir_release_expired_reservations', array(), 'wir' );
	as_unschedule_all_actions( 'wir_release_single_reservation', array(), 'wir' );
}
wp_clear_scheduled_hook( 'wir_release_expired_reservations' );
