<?php
/**
 * Persistence port for schedule metadata.
 *
 * @package ProductUnpublisher
 */

namespace ProductUnpublisher\Storage;

defined( 'ABSPATH' ) || exit;

interface ScheduleStore {

	/**
	 * Scheduled timestamp, or null when absent or unparsable.
	 */
	public function get_timestamp( int $product_id ): ?int;

	/**
	 * Raw stored schedule value, or null when no metadata exists. Used for rollback.
	 */
	public function get_raw( int $product_id ): ?string;

	public function set_timestamp( int $product_id, int $timestamp ): void;

	/**
	 * Put back a value previously returned by get_raw() (null deletes it).
	 */
	public function restore_raw( int $product_id, ?string $raw ): void;

	/**
	 * Delete the schedule metadata.
	 */
	public function delete( int $product_id ): void;

	public function get_attempts( int $product_id ): int;

	/**
	 * Increment and return the failed-attempt counter.
	 */
	public function increment_attempts( int $product_id ): int;

	public function clear_attempts( int $product_id ): void;

	/**
	 * Whether any schedule or retry metadata exists.
	 */
	public function has_data( int $product_id ): bool;
}
