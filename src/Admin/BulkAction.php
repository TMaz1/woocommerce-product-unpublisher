<?php
/**
 * Bulk edit operations.
 *
 * @package ProductUnpublisher
 */

namespace ProductUnpublisher\Admin;

defined( 'ABSPATH' ) || exit;

final class BulkAction {

	public const NONE          = 'none';
	public const SCHEDULE      = 'schedule';
	public const REMOVE        = 'remove';
	public const UNPUBLISH_NOW = 'unpublish_now';

	/**
	 * @return string[]
	 */
	public static function all(): array {
		return array( self::NONE, self::SCHEDULE, self::REMOVE, self::UNPUBLISH_NOW );
	}

	public static function is_valid( string $action ): bool {
		return in_array( $action, self::all(), true );
	}

	private function __construct() {}
}
