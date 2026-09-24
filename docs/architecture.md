# Architecture

## Domain Layer
The domain logic is contained within `src/Reservation`. 
- `ReservationService` orchestrates the creation, adjustment, and release of reservations.
- `AvailabilityService` determines how much of a product's actual stock remains available.
- `Reservation` and `ReservationStatus` define the data entity and its valid state machine transitions (e.g. `ACTIVE -> CONFIRMED`).

## Persistence
All persistence goes through `src/Database/ReservationRepository`. It safely wraps queries with `$wpdb->prepare` and executes the necessary DML operations against the custom table `{$wpdb->prefix}wir_reservations`.

## WooCommerce Integration
`src/WooCommerce` houses classes that translate WooCommerce hook events into our Domain calls:
- `CartIntegration` listens to cart additions and quantity updates to lock the inventory.
- `ProductIntegration` validates frontend availability.
- `OrderIntegration` listens for checkout flows and transitions active reservations to CONFIRMED or CANCELLED as required.

## Scheduler
`src/Scheduler/ExpirationScheduler` handles asynchronous cleanup of expired reservations using Action Scheduler and a WordPress Cron fallback sweep to guarantee integrity even when individual scheduled actions fail.

## Admin
`src/Admin` registers standard WordPress settings, menu pages, and uses a standard `WP_List_Table` (`ReservationListTable.php`) for performant paginated display of the custom database table.
