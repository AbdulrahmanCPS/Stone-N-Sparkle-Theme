# AGENTS.md

## Cursor Cloud specific instructions

### Overview

This repository is a **WordPress theme** ("Stone Sparkle" / `theme3`) for a luxury jewelry e-commerce site. It is **not** a full WordPress installation — it only contains the theme code that lives at `wp-content/themes/theme3/`.

There is **no build step**, **no package manager**, and **no automated test suite**. All CSS/JS is hand-written vanilla code committed directly.

### Development environment (Cloud Agent)

The development stack runs locally on the VM:

| Component | Details |
|-----------|---------|
| PHP | 8.2 (via `ppa:ondrej/php`) |
| Database | MariaDB (`wordpress` DB, user `wpuser` / `wppass`) |
| Web server | Apache 2 on port 80 (`http://localhost`) |
| CMS | WordPress (latest via WP-CLI) at `/var/www/html` |
| Plugins | WooCommerce + Advanced Custom Fields (free) |
| Theme | Symlinked: `/var/www/html/wp-content/themes/theme3` → `/workspace` |

### Starting services

After a fresh VM boot (update script handles dependency refresh), you must start the services:

```bash
# Start MariaDB
sudo mkdir -p /run/mysqld && sudo chown mysql:mysql /run/mysqld
sudo mysqld_safe &
sleep 3

# Start Apache
sudo apachectl start
```

The site is then available at `http://localhost`. WP Admin: `http://localhost/wp-admin/` (user: `admin`, password: `admin`).

### Gotchas

- **WooCommerce "Coming Soon" mode**: New WooCommerce installs enable "Coming Soon" by default. If the shop page shows "Great things are on the horizon" instead of products, disable it: `wp option update woocommerce_coming_soon "no" --allow-root` (run from `/var/www/html`).
- **`.htaccess` required**: WordPress pretty permalinks need an `.htaccess` in the webroot. If URLs return 404, check that `/var/www/html/.htaccess` exists with the standard WP rewrite rules.
- **Deprecation notice**: `get_page_by_title()` is deprecated in WP 6.2+. The theme uses it in `functions.php`; this produces a visible notice when `WP_DEBUG` is on but is not a fatal error.
- **No automated tests**: The project has no test suite. Validate changes by running `php -l` on modified PHP files and visually checking pages in the browser.

### Linting

```bash
cd /workspace && for f in $(find . -name '*.php'); do php -l "$f"; done
```

### Key URLs (dev)

- Shop: `http://localhost/shop/`
- Product: `http://localhost/product/<slug>/`
- Cart: `http://localhost/cart/`
- WP Admin: `http://localhost/wp-admin/`
- Appearance → Themes (verify active theme)
