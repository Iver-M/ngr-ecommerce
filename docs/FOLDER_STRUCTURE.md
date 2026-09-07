# NGR Folder Structure

The PHP files are grouped by feature so team members can quickly find the pages and handlers they own.

```text
ngr-ecommerce/
|-- actions/
|   |-- account/       # Returns, reviews, cancellations, notifications
|   |-- auth/          # Registration processing and logout
|   |-- cart/          # Cart update handlers
|   |-- checkout/      # Vouchers and PayPal endpoints
|   |-- support/       # Support ticket submission
|   `-- wishlist/      # Wishlist update handlers
|-- modules/
|   |-- account/       # Profile, orders, and invoices
|   |-- admin/         # Admin dashboard and server information
|   |-- auth/          # Login, registration, verification, password reset
|   |-- cart/          # Cart and wishlist pages
|   |-- catalog/       # Product listing and product details
|   |-- checkout/      # Checkout page
|   `-- support/       # FAQ and content pages
|-- core/              # Shared path setup, authentication check, initialization
|-- services/          # Email notifications and shipping calculations
|-- config/            # Local database and payment configuration
|-- includes/          # Shared header, footer, and view helpers
|-- assets/            # CSS and JavaScript
|-- uploads/           # Product media and ignored customer uploads
|-- PHPMailer/         # Bundled email library
|-- sendgrid/          # Bundled SendGrid library
|-- index.php          # Home page and public entry point
`-- .htaccess          # Maps existing URLs to the organized files
```

## Public URLs

Existing links do not need to change. For example:

- `login.php` loads `modules/auth/login.php`.
- `products.php` loads `modules/catalog/products.php`.
- `checkout.php` loads `modules/checkout/checkout.php`.
- `add_to_cart.php` runs `actions/cart/add_to_cart.php`.
- `admin_dashboard.php` loads `modules/admin/admin_dashboard.php`.

The mappings are defined in the root `.htaccess` file. Root-level `index.php` remains in place because it is the website's default entry point.

## Working as a group

- Authentication members work primarily in `modules/auth/` and `actions/auth/`.
- Storefront members work in `modules/catalog/`, `modules/cart/`, and their actions.
- Checkout members work in `modules/checkout/` and `actions/checkout/`.
- Account members work in `modules/account/` and `actions/account/`.
- Administrators work in `modules/admin/`.
- Shared behavior belongs in `core/`, `services/`, or `includes/` rather than being duplicated between modules.
