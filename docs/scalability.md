# Scalability & Concurrency

## Transaction Safety
We use InnoDB row-level locking to prevent race conditions during checkout.

```sql
SELECT ID FROM wp_posts WHERE ID = %d FOR UPDATE
```
This query ensures that if two users attempt to reserve the final item of the same product at the exact same millisecond, the database serializes their requests. One will receive the lock and succeed. The second will wait for the lock, then recalculate the availability using a locking read (`FOR SHARE`/`FOR UPDATE`), see the new reservation, and safely fail to add to cart.

## Custom Table Pagination
The admin interface implements `WP_List_Table` reading from our indexed custom table using `LIMIT` and `OFFSET`. We never load all reservations into PHP memory.

## Caching
The database is the authoritative source of truth. We do not use the object cache for reservation state to prevent overselling due to cache eviction or stale snapshot reads. The actual stock calculation bypasses snapshot anomalies by explicitly doing a locking read inside the transaction boundary.
