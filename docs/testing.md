# Testing Strategy

## Test Matrix

1. **Simple Product**: Validated that one simple product reduces effective availability correctly.
2. **Variable Products**: Validated that variations sharing parent stock deduct from the parent, while independent variations deduct from themselves. Handled by checking `get_stock_managed_by_id`.
3. **Concurrency Test**: MySQL row-level locks prevent two simultaneous requests from successfully reserving the same final unit. 
4. **Cart Synchronization**: Verified quantity increments/decrements in cart translate into equivalent logic in the DB, and complete item removal fully releases the lock.
5. **Abandoned Carts / Expiration**: Fallback `ExpirationScheduler` releases stranded reservations if the user abandons the checkout flow.
6. **Order Lifecycle**: Verified that if payment fails or the order is cancelled, reservations are released. Pending status leaves them intact until expiration.

## Automated Testing
PHPUnit tests are available in `tests/Unit`. They enforce idempotency on state machine transitions. Full integration testing is intended to run using WooCommerce core testing libraries to mock database interactions.
