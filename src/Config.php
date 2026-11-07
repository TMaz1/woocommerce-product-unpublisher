<?php
/**
 * Shared constants.
 *
 * @package ProductUnpublisher
 */

namespace ProductUnpublisher;

defined( 'ABSPATH' ) || exit;

/**
 * Central place for storage keys and limits.
 */
final class Config {

	/** Post meta holding the scheduled unpublish time (site-local "Y-m-d H:i:s"). Kept for backwards compatibility. */
	public const META_KEY = '_wps_unpublish_time';

	/** Post meta holding the number of failed unpublish attempts. */
	public const ATTEMPTS_META = '_wpu_unpublish_attempts';

	/** Cron hook fired for each product. Args: array( product_id ). */
	public const CRON_HOOK = 'wpu_unpublish_product';

	/** Maximum automatic attempts before retries stop. */
	public const MAX_ATTEMPTS = 5;

	/** Option holding the private failure log. */
	public const FAILURE_LOG_OPTION = 'wpu_failure_log';

	/** Maximum failure log entries kept. */
	public const FAILURE_LOG_LIMIT = 100;

	/** Option holding the last reconciled plugin version. */
	public const DB_VERSION_OPTION = 'wpu_db_version';

	/** Post type handled by this plugin. */
	public const POST_TYPE = 'product';

	/** Earliest year accepted for a schedule. */
	public const MIN_YEAR = 2000;

	/** Statuses that may carry an unpublishing schedule. */
	public const SCHEDULABLE_STATUSES = array( 'publish', 'future' );

	/** Failure reasons that need a human to look at them. */
	public const ACTIONABLE_FAILURES = array( 'cleanup_failed', 'retries_exhausted' );

	/** Seconds a stored notice or bulk result lives. */
	public const NOTICE_TTL = 300;

	private function __construct() {}
}
