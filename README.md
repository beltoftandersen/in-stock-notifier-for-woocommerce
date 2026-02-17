# In-Stock Notifier for WooCommerce

Let customers subscribe to out-of-stock product notifications and automatically email them when items are back in stock.

- Stable version: 1.0.7
- Requires: WordPress 5.8+, PHP 7.4+, WooCommerce 6.0+
- Author: Chimkins IT
- Text domain: instock-notifier-for-woocommerce

## Overview

This plugin adds a subscription form to out-of-stock WooCommerce products. When a product comes back in stock, subscribers get an email notification using WooCommerce's email template — same design as your order emails.

Stock changes are detected regardless of source: admin UI, REST API, CLI, or ERP systems.

## Features

- Subscription form on out-of-stock products (simple, variable, grouped, external)
- Variable products: form shows/hides automatically per variation
- Notifications use WooCommerce email templates
- Email configurable under WooCommerce > Settings > Emails > Back In Stock
- Batch sending via WP-Cron with configurable batch size and throttle
- Detects stock changes from admin, REST API, CLI, and ERP systems
- Cache purging on stock change (WP Super Cache, W3TC, WP Rocket, LiteSpeed, WP Fastest Cache)
- Admin dashboard with stats and manual "Send Notifications" button
- Subscription list with search, filters, pagination, and bulk actions
- One-click unsubscribe link in every email
- Optional GDPR checkbox, honeypot spam protection, and rate limiting
- Shortcode `[instock_notifier]` for custom placement
- Activity logging with auto-trim
- Clean uninstall with opt-in data removal
- PSR-4 codebase, no Composer dependency

## How It Works

1. Customer visits an out-of-stock product and enters their email.
2. When the product comes back in stock, WooCommerce hooks trigger the plugin.
3. Emails are queued and sent in batches — product image, "Shop Now" button, and unsubscribe link included.
4. Product page cache is purged so customers see the correct stock status.

## Installation

1. Upload the plugin to `wp-content/plugins/` or install from a ZIP.
2. Activate the plugin.
3. Go to **WooCommerce > In-Stock Notifier** to configure.
4. Optionally adjust the email under **WooCommerce > Settings > Emails > Back In Stock**.

## Configuration

### Form Placement

The form shows automatically on out-of-stock product pages. To place it manually, disable auto-placement in settings and use the shortcode:

```
[instock_notifier]
[instock_notifier product_id="123"]
```

### Email Template

Notifications use WooCommerce's email system — same header, footer, and colours as your other store emails. Customise the subject and heading under WooCommerce > Settings > Emails > Back In Stock.

Override the template by copying `templates/emails/back-in-stock.php` to your theme's `woocommerce/emails/` folder.

### Batch Sending

- Configurable batch size (default 50) and throttle between emails.
- If more subscribers remain, the next batch runs 60 seconds later.
- For low-traffic sites, set up a real system cron for reliable scheduling.

### Logging

- View activity in the Logs tab: subscriptions, sends, errors.
- Disable logging in settings for production.
- Logs auto-trim at 2 MiB.

## Hooks & Filters

Developers can extend the plugin:

- `instock_notifier_form_html` / `instock_notifier_form_fields` / `instock_notifier_form_heading_text`
- `instock_notifier_email_body` / `instock_notifier_email_subject` / `instock_notifier_email_heading`
- `instock_notifier_before_subscription` / `instock_notifier_after_subscription`
- `instock_notifier_before_notification_sent` / `instock_notifier_after_notification_sent`
- `instock_notifier_validate_subscription` / `instock_notifier_placeholders`

## Translations

- Text domain: `instock-notifier-for-woocommerce`
- Translation template: `languages/instock-notifier-for-woocommerce.pot`

## Changelog

### 1.0.7

- Notifications now use WooCommerce email templates.
- Back In Stock email configurable under WooCommerce > Settings > Emails.
- Email preview in WooCommerce email settings.
- Manual notification trigger from the Dashboard tab.
- Variation stock status shown correctly in admin dashboard.
- Improved form design (joined email input and button).
- Pre-fill email for logged-in users.

### 1.0.0

- Initial release.

## License

GPLv2 or later. See https://www.gnu.org/licenses/gpl-2.0.html.
