# Security

## Admin Actions
Manual releases in the admin panel use secure POST endpoints handled via `admin_post_*`.
Before performing a destructive release action, the plugin verifies:
1. `current_user_can('manage_woocommerce')`
2. `wp_verify_nonce()` via `check_admin_referer`

## Database Integrity
All dynamic queries are prepared using `$wpdb->prepare()`.

## Output Escaping
All variables rendered into HTML are escaped using standard WP escaping functions (`esc_html`, `esc_attr`, `esc_url`).

## Form Inputs
Configurations (like `hold_minutes`) are sanitized using `absint()` upon save.
