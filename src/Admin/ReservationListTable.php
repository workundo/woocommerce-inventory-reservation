<?php
namespace WIR\Admin;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

use WIR\Reservation\ReservationService;
use WIR\Reservation\ReservationStatus;

class ReservationListTable extends \WP_List_Table {
	public function __construct( private ReservationService $service ) {
		parent::__construct(
			array(
				'singular' => 'reservation',
				'plural'   => 'reservations',
				'ajax'     => false,
			)
		);
	}

	public function get_columns(): array {
		return array(
			'id'         => __( 'ID', 'wc-inventory-reservation' ),
			'product'    => __( 'Product', 'wc-inventory-reservation' ),
			'quantity'   => __( 'Quantity', 'wc-inventory-reservation' ),
			'status'     => __( 'Status', 'wc-inventory-reservation' ),
			'expires_at' => __( 'Expires', 'wc-inventory-reservation' ),
			'order_id'   => __( 'Order ID', 'wc-inventory-reservation' ),
			'actions'    => __( 'Action', 'wc-inventory-reservation' ),
		);
	}

	public function prepare_items(): void {
		$columns  = $this->get_columns();
		$hidden   = array();
		$sortable = array();
		$this->_column_headers = array( $columns, $hidden, $sortable );

		$per_page = 50;
		$current_page = $this->get_pagenum();
		
		$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		
		$total_items = $this->service->repository()->count( $status );
		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
				'total_pages' => ceil( $total_items / $per_page ),
			)
		);

		$this->items = $this->service->repository()->get_admin_rows( $current_page, $per_page, $status );
	}

	protected function get_views(): array {
		$views = array();
		$current = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : 'all';
		$statuses = array_merge( array( 'all' ), ReservationStatus::all() );

		foreach ( $statuses as $status ) {
			$url = add_query_arg(
				array(
					'page'   => 'wir-reservations',
					'status' => 'all' === $status ? '' : $status,
				),
				admin_url( 'admin.php' )
			);
			
			$class = ( $current === $status ) ? 'current' : '';
			$name = 'all' === $status ? __( 'All', 'wc-inventory-reservation' ) : ucfirst( $status );
			
			$views[ $status ] = sprintf(
				'<a href="%s" class="%s">%s</a>',
				esc_url( $url ),
				esc_attr( $class ),
				esc_html( $name )
			);
		}
		return $views;
	}

	protected function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'id':
				return esc_html( $item['id'] );
			case 'quantity':
				return esc_html( wc_format_decimal( $item['quantity'] ) );
			case 'status':
				return esc_html( ucfirst( $item['status'] ) );
			case 'expires_at':
				return esc_html( get_date_from_gmt( $item['expires_at'], 'Y-m-d H:i:s' ) );
			case 'order_id':
				return $item['order_id'] ? esc_html( $item['order_id'] ) : '&mdash;';
			case 'product':
				$product = wc_get_product( (int) $item['product_id'] );
				return $product ? esc_html( $product->get_name() ) : esc_html__( 'Deleted product', 'wc-inventory-reservation' );
			case 'actions':
				if ( ReservationStatus::ACTIVE === $item['status'] ) {
					return sprintf(
						'<form method="post" action="%s">
							<input type="hidden" name="action" value="wir_release_reservation">
							<input type="hidden" name="reservation_id" value="%s">
							%s
							<input type="submit" class="button" value="%s">
						</form>',
						esc_url( admin_url( 'admin-post.php' ) ),
						esc_attr( $item['id'] ),
						wp_nonce_field( 'wir_release_' . $item['id'], '_wpnonce', true, false ),
						esc_attr__( 'Release', 'wc-inventory-reservation' )
					);
				}
				return '&mdash;';
			default:
				return '&mdash;';
		}
	}
}
