<?php
/**
 * Outcome of a bulk operation for one product.
 *
 * @package ProductUnpublisher
 */

namespace ProductUnpublisher\Admin;

defined( 'ABSPATH' ) || exit;

final class BulkEntry {

	/** The operation completed. */
	public const SUCCESS = 'success';

	/** The product was deliberately left unchanged (e.g. not published). */
	public const SKIPPED = 'skipped';

	/** The operation failed. */
	public const FAILED = 'failed';

	/** The product was unpublished, but schedule cleanup afterwards failed. */
	public const PARTIAL = 'partial';

	private function __construct(
		public readonly int $product_id,
		public readonly string $outcome,
		public readonly string $reason = ''
	) {}

	public static function success( int $product_id ): self {
		return new self( $product_id, self::SUCCESS );
	}

	public static function skipped( int $product_id, string $reason ): self {
		return new self( $product_id, self::SKIPPED, $reason );
	}

	public static function failed( int $product_id, string $reason ): self {
		return new self( $product_id, self::FAILED, $reason );
	}

	public static function partial( int $product_id, string $reason ): self {
		return new self( $product_id, self::PARTIAL, $reason );
	}

	/**
	 * @return string[]
	 */
	public static function outcomes(): array {
		return array( self::SUCCESS, self::SKIPPED, self::FAILED, self::PARTIAL );
	}

	/**
	 * Rebuild an entry from stored data, or null when invalid.
	 *
	 * @param mixed $data Stored array( product_id, outcome, reason ).
	 */
	public static function from_array( mixed $data ): ?self {
		if ( ! is_array( $data ) || 3 !== count( $data ) ) {
			return null;
		}

		$data = array_values( $data );

		if ( ! is_int( $data[0] ) || $data[0] <= 0 || ! is_string( $data[1] ) || ! in_array( $data[1], self::outcomes(), true ) || ! is_string( $data[2] ) ) {
			return null;
		}

		return new self( $data[0], $data[1], $data[2] );
	}

	/**
	 * @return array{0:int,1:string,2:string}
	 */
	public function to_array(): array {
		return array( $this->product_id, $this->outcome, $this->reason );
	}
}
