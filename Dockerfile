FROM wordpress:php8.4-apache

# Fail the build rather than accidentally testing on a PHP runtime below the
# security-fix level for the October 2026 critical PHP advisories.
RUN php -r "if (version_compare(PHP_VERSION, '8.4.27', '<')) { fwrite(STDERR, 'PHP 8.4.27+ required for this development image; current: ' . PHP_VERSION . PHP_EOL); exit(1); }"
