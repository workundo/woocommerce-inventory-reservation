<?php
namespace WIR\Admin;

defined( 'ABSPATH' ) || exit;

use WIR\Reservation\ReservationService;

final class Admin {
	public function __construct( private ReservationService $service ) {}

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_post_wir_release_reservation', array( $this, 'release' ) );
		add_action( 'admin_init', array( $this, 'settings' ) );
	}

	public function menu(): void {
		add_submenu_page(
			'woocommerce',
			__( 'Inventory Reservations', 'wc-inventory-reservation' ),
			__( 'Reservations', 'wc-inventory-reservation' ),
			'manage_woocommerce',
			'wir-reservations',
			array( $this, 'page' )
		);
	}

	public function settings(): void {
		register_setting(
			'wir_settings_group',
			'wir_settings',
			array(
				'type'              => 'array',
				'sanitize_callback' => function ( $value ) {
					$minutes = isset( $value['hold_minutes'] ) ? absint( $value['hold_minutes'] ) : 15;
					return array( 'hold_minutes' => max( 1, min( 1440, $minutes ) ) );
				},
				'default'           => array( 'hold_minutes' => 15 ),
			)
		);
	}

	public function page(): void {
		$page = new ReservationsPage( $this->service );
		$page->render();
	}

	public function release(): void {
		$id = isset( $_POST['reservation_id'] ) ? absint( $_POST['reservation_id'] ) : 0;
		if ( ! current_user_can( 'manage_woocommerce' ) || ! $id ) {
			wp_die( esc_html__( 'Unauthorized request.', 'wc-inventory-reservation' ), 403 );
		}
		check_admin_referer( 'wir_release_' . $id );
		$this->service->admin_release( $id );
		wp_safe_redirect( wp_get_referer() ?: admin_url( 'admin.php?page=wir-reservations' ) );
		exit;
	}
}
