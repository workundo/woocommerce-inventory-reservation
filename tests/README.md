# Test Evidence Plan

The repository should be run against a disposable WordPress + WooCommerce test site.

## Required integration matrix
- PHP 8.1, 8.2, 8.3
- WordPress 6.6+
- Current WooCommerce version used by the employer
- HPOS enabled and disabled
- Action Scheduler healthy and intentionally delayed
- Redis/object cache enabled and disabled

## Concurrency test
Create one simple product with stock = 1. Start two independent browser sessions and submit add-to-cart at nearly the same time. Assert one active reservation exists and available inventory never becomes negative.

For an automated implementation, use two concurrent HTTP clients against a test site and query:
`SELECT COUNT(*) FROM wp_wir_reservations WHERE stock_product_id = ? AND status = 'active'`.

## Payment lifecycle
Test successful, failed, cancelled, and pending orders. Assert reservation status and WooCommerce `_order_stock_reduced` behavior remain consistent and stock is not reduced twice.

## Repeated callbacks
Trigger cart recalculation and repeated payment callbacks. The reservation must remain idempotent.

## Scheduled actions
Move an expiry timestamp into the past, run the cleanup action manually, and verify the row changes from `active` to `expired`. Mark a scheduled action failed and run the recovery sweep.
