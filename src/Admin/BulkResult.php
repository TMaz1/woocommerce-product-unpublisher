<?php
/**
 * Collects the per-product outcomes of one bulk edit request.
 *
 * Totals are exact; individual details are kept only for products that did not simply succeed, and capped
 * so very large selections cannot bloat the transient.
 *
 * @package ProductUnpublisher
 */

namespace ProductUnpublisher\Admin;

defined( 'ABSPATH' ) || exit;

final class BulkResult {

	public const MAX_DETAILS = 50;

	/** @var array<string,int> */
	private array $counts;

	/** @var BulkEntry[] */
	private array $details = array();

	public function __construct( private string $action = '' ) {
		$this->counts = array_fill_keys( BulkEntry::outcomes(), 0 );
	}

	public function action(): string {
		return $this->action;
	}

	public function set_action( string $action ): void {
		$this->action = $action;
	}

	public function add( BulkEntry $entry ): void {
		++$this->counts[ $entry->outcome ];

		if ( BulkEntry::SUCCESS !== $entry->outcome && count( $this->details ) < self::MAX_DETAILS ) {
			$this->details[] = $entry;
		}
	}

	public function is_empty(): bool {
		return 0 === $this->total();
	}

	public function total(): int {
		return array_sum( $this->counts );
	}

	public function count( string $outcome ): int {
		return $this->counts[ $outcome ] ?? 0;
	}

	/**
	 * Detailed entries for skipped, failed and partial products.
	 *
	 * @return BulkEntry[]
	 */
	public function details(): array {
		return $this->details;
	}

	/**
	 * @return array{action:string,counts:array<string,int>,details:array<int,array{0:int,1:string,2:string}>}
	 */
	public function to_array(): array {
		return array(
			'action'  => $this->action,
			'counts'  => $this->counts,
			'details' => array_map(
				static function ( BulkEntry $entry ): array {
					return $entry->to_array();
				},
				$this->details
			),
		);
	}

	/**
	 * @param mixed $data Stored data.
	 */
	public static function from_array( mixed $data ): ?self {
		if ( ! is_array( $data ) || ! isset( $data['action'], $data['counts'], $data['details'] ) || ! is_string( $data['action'] ) || ! is_array( $data['counts'] ) || ! is_array( $data['details'] ) ) {
			return null;
		}

		$result = new self( $data['action'] );

		foreach ( BulkEntry::outcomes() as $outcome ) {
			$result->counts[ $outcome ] = isset( $data['counts'][ $outcome ] ) ? max( 0, (int) $data['counts'][ $outcome ] ) : 0;
		}

		foreach ( $data['details'] as $detail ) {
			$entry = BulkEntry::from_array( $detail );

			if ( null !== $entry && count( $result->details ) < self::MAX_DETAILS ) {
				$result->details[] = $entry;
			}
		}

		return $result;
	}
}
