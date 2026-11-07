=== WooCommerce Product Unpublisher ===
Contributors: woocommerce-product-unpublisher
Tags: woocommerce, products, scheduling, draft, bulk edit
Requires at least: 6.5
Requires PHP: 8.2
Requires Plugins: woocommerce
Stable tag: 2.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Schedule published WooCommerce products to return to Draft automatically, one at a time or in bulk from All Products.

== Description ==

Adds an "Unpublish on" row to the classic product editor Publish box, and an "Unpublishing schedule" section to the native Bulk Edit panel on Products > All Products.

**Product editor**

* Native-style Edit / OK / Cancel date editor using the site's timezone.
* Works for published products and for products scheduled to publish later (unpublishing must be after publication).
* Remove a schedule at any time; changes are applied when you click Update.

**Bulk Edit (Products > All Products)**

Select products, choose Bulk actions > Edit, and pick one of:

* **No change to existing schedules** (default).
* **Schedule unpublishing** – the date and time fields appear only for this choice. The date is applied to every selected *published* product. Existing schedules are replaced, never duplicated. Products that are not published are skipped.
* **Remove scheduled unpublishing** – removes the scheduled date, the cron event and any retry information from each selected product. A confirmation dialog explains exactly what will happen.
* **Unpublish published products now** – moves selected published products to Draft immediately, after a confirmation dialog showing how many products are affected.

After a bulk edit you see how many products succeeded, were skipped or failed, with the reason for each problem. If a product was moved to Draft but its schedule data could not be fully cleaned up, you are told so explicitly.

**Reliability**

* Every product has its own cron event. Replacing a schedule is transactional: if the new schedule cannot be saved, the previous one is kept.
* Schedules are removed automatically when a product becomes Draft, Private, Pending or Trashed.
* Invalid or past dates are rejected on the server, not only in the browser.
* Deactivating the plugin removes cron events but keeps schedules; reactivating restores them.

== Scheduling reliability ==

WordPress cron is traffic-triggered by default and cannot guarantee exact execution. For production, configure a real system scheduler to run due cron events at least once per minute, then disable visitor-triggered WP-Cron only after confirming the scheduler works. Example with WP-CLI:

`* * * * * cd /path/to/wordpress && wp cron event run --due-now --quiet`

If a due status update fails, the plugin retries up to five times with increasing delays and records a limited private diagnostic log in the WordPress database. After the retry limit, the schedule remains for manual investigation, the Products list shows "Automatic retries stopped", and a notice asks you to review the product. This does not guarantee execution when hosting, PHP, database, or cron infrastructure is unavailable.

Schedules are stored as site-local times. If you change the site timezone after scheduling products, review existing schedules.

== Privacy ==

No data is sent to external services. The private failure log contains product IDs, controlled error codes, timestamps and retry counts only. All plugin data is removed when the plugin is deleted.

== Installation ==

1. Install and activate WooCommerce.
2. Upload the plugin ZIP through Plugins > Add New > Upload Plugin and activate it.

== Frequently Asked Questions ==

= Does it work with the new product editor? =

The scheduling row targets the classic product editor. Bulk scheduling works from the Products list regardless of the editor.

= Does it support HPOS? =

Yes. The plugin only uses product posts and post meta.

== Changelog ==

= 2.1.0 =
* New: bulk scheduled unpublishing in Bulk Edit (schedule, remove, unpublish now) with confirmations and a per-product result report.
* New: "Unpublishes" column on the Products list.
* New: warning notice when a product was unpublished but its schedule cleanup failed, or retries were exhausted.
* Fixed: bulk edit data was read from POST although the Products list submits by GET, so bulk actions never ran.
* Fixed: bulk date fields could never be shown; added JavaScript to toggle them.
* Fixed: results redirect now carries the results token; results are stored before the redirect.
* Fixed: unpublishing from Bulk Edit no longer reports a false failure.
* Fixed: schedules were lost on deactivate/activate; they are now re-registered.
* Fixed: overdue events are restored after a failed replacement.
* Fixed: reopening the date editor straight after closing it could leave it invisible.
* Changed: code reorganised into small single-purpose classes; added uninstall cleanup, unit, integration and end-to-end tests, and CI.

= 2.0.0 =
* Initial release.

== Upgrade Notice ==

= 2.1.0 =
Adds bulk scheduled unpublishing and fixes several reliability issues. The main plugin file was renamed to woocommerce-product-unpublisher.php; reactivate the plugin if WordPress deactivates it after the update.
