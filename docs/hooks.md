# Hooks

## WooCommerce Hooks Utilized

- `woocommerce_add_to_cart_validation`: Validate if stock is available *before* allowing the cart addition.
- `woocommerce_add_to_cart`: Orchestrate a transaction-safe reservation immediately after the item is added.
- `woocommerce_after_cart_item_quantity_update`: Sync quantity changes in the cart with the reservation.
- `woocommerce_cart_item_removed` / `woocommerce_cart_emptied`: Safely release reservations.
- `woocommerce_checkout_create_order_line_item`: Persist reservation metadata to the new order line item.
- `woocommerce_payment_complete`: Confirm the reservation, allowing WooCommerce to permanently reduce stock.
- `woocommerce_order_status_cancelled` / `woocommerce_order_status_failed`: Safely release the reservation.

## Core WordPress Hooks
- `admin_post_wir_release_reservation`: Receives the secure POST request for admin manual release operations.
- `init`: Schedules cleanup events.
