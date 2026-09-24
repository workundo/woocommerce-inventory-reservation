<?php
namespace WIR\Admin;

defined( 'ABSPATH' ) || exit;

use WIR\Reservation\ReservationService;

final class ReservationsPage {
	public function __construct( private ReservationService $service ) {}

	public function render(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'wc-inventory-reservation' ), 403 );
		}
		
		$list_table = new ReservationListTable( $this->service );
		$list_table->prepare_items();
		
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Inventory Reservations', 'wc-inventory-reservation' ); ?></h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'wir_settings_group' ); ?>
				<table class="form-table">
					<tr>
						<th><label for="wir_hold_minutes"><?php echo esc_html__( 'Reservation period (minutes)', 'wc-inventory-reservation' ); ?></label></th>
						<td>
							<input id="wir_hold_minutes" type="number" min="1" max="1440" name="wir_settings[hold_minutes]" value="<?php echo esc_attr( $this->service->hold_minutes() ); ?>">
							<p class="description"><?php echo esc_html__( 'Default: 15 minutes.', 'wc-inventory-reservation' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>

			<hr>

			<form id="reservations-filter" method="get">
				<input type="hidden" name="page" value="wir-reservations" />
				<?php
				if ( isset( $_GET['status'] ) ) {
					echo '<input type="hidden" name="status" value="' . esc_attr( sanitize_key( wp_unslash( $_GET['status'] ) ) ) . '" />';
				}
				?>
				<?php $list_table->views(); ?>
				<?php $list_table->display(); ?>
			</form>
		</div>
		<?php
	}
}
