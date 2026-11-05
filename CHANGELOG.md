# Changelog

All notable changes to WooCommerce Product Unpublisher will be documented in this file.

## \[Unreleased\]

### Fixed

- Fixed stale unpublishing schedules remaining after products transition to Draft, Private or Trash.
- Fixed previously unpublished products potentially retaining old cron events when republished.
- Fixed unnecessary date validation errors when publishing products without an active unpublishing schedule.
- Fixed admin form submission so valid dates can be saved by clicking Update without first clicking OK in the date editor.
- Fixed schedule removal handling to ensure associated cron events and metadata are cleaned up.
- Improved preservation of existing schedules when replacing a schedule fails.
- Improved handling of products with existing schedules when revisiting and updating their product pages.

### Improved

 #### [2.0.1]
- Improved cron event registration and verification.
- Improved cron event cleanup and schedule restoration.
- Added logging for failed schedule registration, restoration and cleanup operations.
- Improved retry handling and failure reporting for scheduled unpublishing.
- Improved consistency checks between stored schedule metadata and registered cron events.
- Updated admin JavaScript to handle date validation, schedule saving and removal more reliably.

 #### [2.0.2]
- Move the Unpublish on control above WooCommerce Catalogue visibility.
- Add WordPress-style slide animations when opening and closing the unpublishing date editor.
- Respect reduced-motion accessibility preferences.
- Allow first-time users to undo an unsaved unpublishing date selection without confirmation.
- Improve schedule removal visibility and editor accessibility.

### Development

- Added WP-CLI to the Docker development environment.
- Added an Apache virtual host configuration for port 8080.
- Updated the Dockerfile to support the additional Apache configuration.
- Added a PHP version requirement check for PHP 8.4.27 or later.
