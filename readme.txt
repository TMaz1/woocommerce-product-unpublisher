=== WooCommerce Product Unpublisher ===
Contributors: woocommerce-product-unpublisher
Tags: woocommerce, products, scheduling, draft
Requires at least: 6.5
Requires PHP: 8.2
Requires Plugins: woocommerce
Stable tag: 1.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Schedule published WooCommerce products to return to Draft status.

== Description ==
Adds a native-style unpublishing date row to the classic product editor Publish panel. Uses the site's timezone. Supports scheduling a currently published product or a product scheduled to publish later, provided unpublishing is after its publication time. The Products list includes an immediate bulk action to move selected published products to Draft.

== Scheduling reliability ==
WordPress cron is traffic-triggered by default and cannot guarantee exact execution. For production, configure a real system scheduler to run due cron events at least once per minute, then disable visitor-triggered WP-Cron only after confirming the scheduler works. Example with WP-CLI:
* * * * * cd /path/to/wordpress && wp cron event run --due-now --quiet

If a due status update fails, the plugin retries up to five times with increasing delays and records a limited private diagnostic log in the WordPress database. After the retry limit, the schedule metadata remains for manual investigation, but automatic retries stop. This does not guarantee execution when hosting, PHP, database, or cron infrastructure is unavailable.

== Privacy ==
No data is sent to external services. The private failure log contains product IDs, controlled error codes, timestamps and retry counts only.

== Installation ==
Upload the ZIP through Plugins > Add New > Upload Plugin. Install and activate WooCommerce first.
