# Secure WooCommerce Inventory Reservation

A transaction-safe WooCommerce cart inventory reservation plugin designed to securely manage temporary stock allocations without modifying WooCommerce core code.

## Features
- **Transaction Safe**: Uses native InnoDB row-level locking to completely eliminate race conditions and overselling.
- **Cart Synchronization**: Hooks natively into cart additions, adjustments, removals, and abandonments.
- **Variation Aware**: Automatically detects if a variation manages its own stock or defers to the parent product.
- **Action Scheduler Integration**: Clean asynchronous expiration sweeps natively utilizing `as_schedule_recurring_action`.
- **Idempotency Built-In**: Payment hook retries and failed callbacks will safely exit instead of creating double allocations.

## Architecture
See `docs/architecture.md` for a complete overview.

## Setup
1. Zip the plugin folder.
2. Upload via WordPress Admin -> Plugins -> Add New.
3. Activate the plugin.
4. Settings can be configured under `WooCommerce -> Reservations`.

## Requirements
- PHP 8.1+
- WordPress 6.6+
- WooCommerce 8.0+

## Security & Concurrency
See `docs/security.md` and `docs/scalability.md` for our lock management strategy against race conditions.
