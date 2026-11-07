<?php
/**
 * Result of an unpublish attempt.
 *
 * @package ProductUnpublisher
 */

namespace ProductUnpublisher\Product;

defined( 'ABSPATH' ) || exit;

final class UnpublishOutcome {

	/** Product is Draft and its schedule data is gone. */
	public const UNPUBLISHED = 'unpublished';

	/** Product is Draft but schedule data could not be fully removed. */
	public const CLEANUP_FAILED = 'cleanup_failed';

	/** Product was not published, so nothing was changed. */
	public const NOT_PUBLISHED = 'not_published';

	/** Product does not exist. */
	public const NOT_FOUND = 'not_found';

	/** The status change itself failed. */
	public const FAILED = 'failed';

	private function __construct(
		public readonly string $status,
		public readonly string $reason = ''
	) {}

	public static function unpublished(): self {
		return new self( self::UNPUBLISHED );
	}

	public static function cleanup_failed(): self {
		return new self( self::CLEANUP_FAILED, 'cleanup_failed' );
	}

	public static function not_published(): self {
		return new self( self::NOT_PUBLISHED, 'not_published' );
	}

	public static function not_found(): self {
		return new self( self::NOT_FOUND, 'not_found' );
	}

	public static function failed( string $reason ): self {
		return new self( self::FAILED, $reason );
	}
}
