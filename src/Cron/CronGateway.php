<?php
/**
 * Port for the per-product cron events.
 *
 * @package ProductUnpublisher
 */

namespace ProductUnpublisher\Cron;

defined( 'ABSPATH' ) || exit;

interface CronGateway {

	/**
	 * Timestamp of the next event for the product, or false when none.
	 *
	 * @return int|false
	 */
	public function next_run( int $product_id );

	/**
	 * Register a single event and verify that it exists.
	 */
	public function schedule( int $product_id, int $timestamp ): bool;

	/**
	 * Remove every event for the product and verify none remain.
	 */
	public function clear( int $product_id ): bool;
}
