# WooCommerce Product Unpublisher

Schedule published WooCommerce products to return to **Draft** automatically — individually in the product editor, or in bulk from **Products → All Products → Bulk Edit**.

See `readme.txt` for the user-facing description (WordPress.org format) and `CHANGELOG.md` for changes.

## Layout

```
woocommerce-product-unpublisher.php   Bootstrap (headers, constants, hooks)
uninstall.php                         Removes all plugin data
src/
  Plugin.php                          Composition root
  Config.php                          Keys and limits
  Time/                               Clock, local-time codec, date parser (pure PHP)
  Storage/                            ScheduleStore interface + post-meta implementation
  Cron/                               CronGateway interface + WP-Cron implementation
  Schedule/                           ScheduleManager (transactional replace/remove), UnpublishRunner,
                                      RetryPolicy, StatusListener, Reconciler
  Product/                            ProductGateway, ProductUnpublisher (shared by cron and bulk)
  Diagnostics/                        FailureLog (+ option implementation)
  Admin/                              Editor row, bulk fields/handler/results/notice, list column, alerts, assets
templates/                            HTML for the editor row and bulk panel
assets/js, assets/css                 date-utils.js (UMD), unpublisher.js (editor), bulk-edit.js
tests/unit                            PHPUnit, no WordPress needed
tests/integration                     PHPUnit + WordPress core test suite
tests/js                              Jest (jsdom)
tests/e2e                             Playwright: fixture-*.spec.js (no WordPress) and wp-*.spec.js (wp-env)
```

## Behaviour of the bulk actions

| Choice | Effect |
| --- | --- |
| No change | Nothing happens. |
| Schedule unpublishing | Applies the date to every selected **published** product. Replaces any existing schedule (one cron event per product). Non-published products are skipped. Invalid/past dates fail server-side. A failed replacement keeps the previous schedule. |
| Remove scheduled unpublishing | Deletes the schedule meta, retry counter and cron event. Confirmation dialog first. Products without a schedule are skipped. |
| Unpublish published products now | Moves published products to Draft immediately (confirmation dialog first, with counts). A product that reached Draft but whose cleanup failed is reported as a *partial* result. |

Bulk Edit is submitted by **GET** from `edit.php`, so the handler reads `$_REQUEST` and verifies its own nonce (`wpu_bulk_edit_schedule`). Results travel to the next page in a per-user transient referenced by the `wpu_bulk_results` redirect argument (added with the `wp_redirect` filter).

## Development

```bash
composer install
composer test                       # PHPUnit unit suite (no WordPress)

bin/install-wp-tests.sh wordpress_test root root 127.0.0.1:3306 latest
composer test:integration -- --configuration phpunit.integration.xml.dist

npm install
npm run test:js                     # Jest
npx playwright install chromium
npm run test:fixture                # Browser tests of the admin scripts, no WordPress

npm run env:start                   # wp-env with WooCommerce on :8889 (admin / password)
npm run test:e2e                    # Full end-to-end tests
```

A Docker-based manual environment is also provided (`docker compose up`, then http://localhost:8080).

## Building the release ZIP

```bash
bin/build-zip.sh      # writes dist/woocommerce-product-unpublisher-<version>.zip
```