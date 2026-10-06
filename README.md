# NewGrounds project guide

PHP files are grouped by feature. Open the folder for the part you are working on:

For local setup, copy `config/local.example.php` to the ignored `config/local.php`
and fill in your database and sandbox service settings. Existing local credentials
are preserved there. See [deployment instructions](docs/DEPLOYMENT.md) for GitHub
Actions and InfinityFree setup.

| Folder | What belongs here | Main files |
| --- | --- | --- |
| `app/pages/` | Homepage and content pages | `home.php`, `page.php` |
| `app/auth/` | Login, registration, verification, password recovery, logout | `login.php`, `register.php`, `forgot_password.php` |
| `app/admin/` | Admin dashboard, admin logout, proof downloads | `admin_dashboard.php`, `download_proof.php` |
| `app/shop/` | Product browsing, product details, reviews | `products.php`, `product.php`, `submit_review.php` |
| `app/cart/` | Cart and voucher actions | `cart.php`, `add_to_cart.php`, `apply_voucher.php` |
| `app/wishlist/` | Wishlist page and actions | `wishlist.php`, `add_to_wishlist.php` |
| `app/account/` | Profile and notification actions | `profile.php`, `mark_one_notification_read.php` |
| `app/orders/` | Checkout, orders, invoices, cancellations, returns | `checkout.php`, `my_orders.php`, `print_invoice.php` |
| `app/payments/` | PayPal order creation and capture | `create_paypal_order.php`, `capture_paypal_order.php` |
| `app/support/` | FAQ and support ticket submission | `faq.php`, `submit_ticket.php` |
| `app/tools/` | PHP runtime information | `info.php` |
| `includes/` | Shared layout, authentication check, initialization, shipping, email helpers | `header.php`, `footer.php`, `auth_check.php`, `shipping_logic.php`, `notification_manager.php` |
| `config/` | Database configuration and URL-to-file routing | `db.php`, `routes.php` |
| `assets/` | Stylesheets and JavaScript | |
| `uploads/` | Product images, profile pictures, promotion images, return videos | |
| `footer_links/` | Existing standalone contact and legacy FAQ pages | |
| `PHPMailer/`, `sendgrid/` | Third-party mail libraries | |

## How requests work

`index.php` is the only PHP file at the project root. It looks up a public URL in
`config/routes.php` and runs its feature file. The original homepage is now
`app/pages/home.php`.

The public URLs are unchanged: visit `login.php`, `products.php`, `profile.php`,
and other original URLs as before. Do not link to `app/auth/login.php` or other
`app/` disk paths. Keeping the URLs stable also preserves existing forms, AJAX
requests, email links, and notification links saved in the database.

Apache uses `.htaccess` to route missing root PHP files to `index.php` without
redirecting, preserving POST data and query strings. Enable `mod_rewrite` and
allow `.htaccess` overrides for this directory. The existing HTTPS and non-www
redirects are retained, so the Apache setup needs HTTPS as before.

For local development with PHP installed, run from this folder:

```sh
php -S localhost:8000 -t . index.php
```

Then open `http://localhost:8000/`. The final `index.php` argument is required
for the old PHP URLs to work on PHP's built-in server. Database and mail/payment
settings are loaded from `config/local.php` or environment variables.

## Making changes

1. Find the feature folder in the table and edit its file.
2. For a new page or action, add a file in its feature folder and a matching
   entry in `config/routes.php`, for example:
   `'new_page.php' => 'app/pages/new_page.php',`.
3. Use the public URL (`new_page.php`) in links, redirects, forms, and JavaScript.
4. Anchor PHP includes to `__DIR__`, following the existing examples. An include
   inside a feature file can use `__DIR__ . '/../../config/db.php'`.
5. Upload paths remain relative to the project root because the entry point
   sets that working directory before running the feature file.

This reorganization changes file placement and routing; the existing business
logic and database schema are retained. Shared helper files are included by
feature code and are not public routes.

## Existing missing page

The profile's "My Reviews" link points to `my_reviews.php`, which was already
absent before the reorganization. If you implement that page, place it in
`app/account/` and register its public URL in `config/routes.php`.
