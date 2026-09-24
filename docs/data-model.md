# Data Model

We use a custom table `{$wpdb->prefix}wir_reservations`.

## Schema
- `id` (bigint, auto_increment): Primary key.
- `session_key` (varchar): Customer ID or session token.
- `cart_item_key` (varchar): Unique WooCommerce cart key.
- `product_id` (bigint): Original requested product or variation.
- `stock_product_id` (bigint): **Crucial Field**. The ID of the item that actually manages the stock (can be a variation or its parent product).
- `variation_id` (bigint): Associated variation ID.
- `quantity` (decimal): Reserved amount.
- `status` (varchar): Enforced state machine enum.
- `expires_at` (datetime): Timestamp of expiration.
- `order_id` (bigint): Assigned when the cart transforms into an order.
- `created_at` / `updated_at` (datetime): Metadata.

## Status Lifecycle
The state machine enforced by `ReservationStatus.php`:
```text
ACTIVE
 ├── EXPIRED
 ├── RELEASED
 ├── CONFIRMED
 ├── FAILED
 └── CANCELLED
```
Transitions back to `ACTIVE` are prohibited.
