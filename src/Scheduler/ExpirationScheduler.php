<?php
namespace WIR\Scheduler;

defined( 'ABSPATH' ) || exit;

use WIR\Reservation\ReservationService;
use WIR\Reservation\ReservationStatus;
use WIR\Support\Logger;

final class ExpirationScheduler {
	public function __construct( private ReservationService $service ) {}

	public function register(): void {
		add_filter( 'cron_schedules', array( $this, 'register_cron_schedule' ) );
		add_action( 'init', array( $this, 'schedule_cleanup' ), 20 );
		add_action( 'wir_release_expired_reservations', array( $this, 'release_expired' ) );
		add_action( 'wir_release_single_reservation', array( $this, 'release_expired_by_id' ) );
		add_action( 'wir_reservation_created', array( $this, 'schedule_single_expiry' ), 10, 2 );
	}

	public function register_cron_schedule( array $schedules ): array {
		if ( ! isset( $schedules['minute'] ) ) {
			$schedules['minute'] = array(
				'interval' => 60,
				'display'  => __( 'Every Minute', 'wc-inventory-reservation' ),
			);
		}
		return $schedules;
	}

	public function schedule_cleanup(): void {
		if ( function_exists( 'as_has_scheduled_action' ) ) {
			if ( ! as_has_scheduled_action( 'wir_release_expired_reservations', array(), 'wir' ) ) {
				as_schedule_recurring_action( time() + 60, 60, 'wir_release_expired_reservations', array(), 'wir', false );
			}
			return;
		}

		if ( ! wp_next_scheduled( 'wir_release_expired_reservations' ) ) {
			wp_schedule_event( time() + 60, 'minute', 'wir_release_expired_reservations' );
		}
	}

	public function schedule_single_expiry( int $id, int $timestamp ): void {
		if ( function_exists( 'as_schedule_single_action' ) && did_action( 'action_scheduler_init' ) ) {
			as_schedule_single_action( $timestamp, 'wir_release_single_reservation', array( $id ), 'wir', true );
		}
	}

	public function release_expired(): void {
		$count = $this->service->expire_due( 500 );
		if ( $count > 0 ) {
			Logger::log( 'expired_reservations_released', array( 'count' => $count ) );
		}
	}

	public function release_expired_by_id( int $id ): void {
		$row = $this->service->repository()->get_by_id( $id );
		if ( $row && ReservationStatus::ACTIVE === $row['status'] && strtotime( $row['expires_at'] . ' UTC' ) <= time() ) {
			$this->service->release( $id, ReservationStatus::EXPIRED );
		}
	}

	public static function unschedule(): void {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( 'wir_release_expired_reservations', array(), 'wir' );
		}
		wp_clear_scheduled_hook( 'wir_release_expired_reservations' );
	}
}
