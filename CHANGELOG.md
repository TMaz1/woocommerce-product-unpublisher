# Changelog

## [2.1.0]

### Added
- Bulk scheduled unpublishing in the native Bulk Edit panel: *No change*, *Schedule unpublishing*, *Remove scheduled unpublishing*, *Unpublish published products now*.
- JavaScript (`assets/js/bulk-edit.js`) that shows the date fields only for *Schedule unpublishing*, validates the date before submitting, and asks for confirmation before removing schedules or unpublishing immediately (native `confirm()` dialogs that state exactly what will happen and how many products are affected).
- Per-product bulk result reporting (success / skipped / failed / partial) with reasons, delivered through a verified redirect token.
- "Unpublishes" column on the Products list (shows overdue and retries-stopped states).
- Admin warning for products that were unpublished but whose schedule cleanup failed, or whose retries were exhausted (dismissible).
- `uninstall.php` removing all plugin data (multisite aware).
- Re-registration of cron events on activation and after upgrades (`Reconciler`).
- PHPUnit unit tests, WordPress-suite integration tests, Jest tests, Playwright fixture and end-to-end tests, GitHub Actions CI, ZIP build script.

### Fixed
- Bulk edit fields were read from `$_POST`, but the Products list submits Bulk Edit with GET, so no bulk action ever ran.
- The bulk date fields stayed hidden forever (no JavaScript toggled them).
- The `wpu_bulk_results` redirect argument was never added, so results were never shown. Results are now stored before the redirect instead of on `shutdown`.
- *Unpublish now* re-entered `save_post_product` and counted a false failure for every product; each product is now handled once per request.
- Non-published products were reported as failures in bulk actions; they are now reported as skipped.
- Deactivating the plugin discarded every schedule's cron event permanently.
- A failed schedule replacement did not restore an overdue previous event.
- The list column handler was never registered.
- Duplicate `_wp_http_referer` fields were emitted by the nonce fields.
- Reopening the date editor immediately after closing it could leave it hidden.
- `load_plugin_textdomain()` was called too early (before `init`); WordPress loads the text domain automatically for wordpress.org plugins.
- Failure to clean up the schedule after a successful immediate unpublish was only written to the PHP error log; it is now reported to the user and recorded in the failure log.

### Changed
- Split the two large classes into focused classes (time parsing, storage, cron, schedule management, runner, status listener, reconciler, admin rendering, bulk handling, notices). Storage and cron are behind interfaces so the logic is unit-testable.
- HTML moved into `templates/`.
- Main file renamed to `woocommerce-product-unpublisher.php` to match the plugin slug.
- Declared HPOS compatibility.
- Pending products are treated like Draft/Private/Trash: their schedules are removed.

## [2.0.0]
- Initial release (see git history for 2.0.x fixes).
