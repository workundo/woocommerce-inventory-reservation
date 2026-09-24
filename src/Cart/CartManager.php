<?php
namespace WIR\Cart;

defined( 'ABSPATH' ) || exit;

use WIR\Reservation\ReservationService;

final class CartManager {
	public function __construct( private ReservationService $service ) {}

	public function register(): void {
		add_filter( 'woocommerce_cart_item_name', array( $this, 'append_timer' ), 20, 3 );
		add_action( 'wp_enqueue_scripts', array( $this, 'assets' ) );
	}

	public function append_timer( string $name, array $cart_item, string $cart_item_key ): string {
		$reservation = $this->service->repository()->find_active_for_cart_item( $this->service->session_key(), $cart_item_key );
		if ( ! $reservation ) {
			return $name;
		}
		$expiry = strtotime( $reservation['expires_at'] . ' UTC' );
		return $name . ' <span class="wir-reservation-timer" data-expires="' . esc_attr( $expiry ) . '">' .
			esc_html__( 'Reserved for ', 'wc-inventory-reservation' ) .
			'<strong>' . esc_html( $this->format_remaining( max( 0, $expiry - time() ) ) ) . '</strong></span>';
	}

	public function assets(): void {
		if ( ! function_exists( 'is_cart' ) || ! is_cart() ) {
			return;
		}
		wp_register_script( 'wir-cart-timer', WIR_URL . 'assets/cart-timer.js', array(), WIR_VERSION, true );
		wp_enqueue_script( 'wir-cart-timer' );
		wp_register_style( 'wir-cart-timer', WIR_URL . 'assets/cart-timer.css', array(), WIR_VERSION );
		wp_enqueue_style( 'wir-cart-timer' );
	}

	private function format_remaining( int $seconds ): string {
		$minutes = intdiv( $seconds, 60 );
		$seconds %= 60;
		return sprintf( '%02d:%02d', $minutes, $seconds );
	}
}
