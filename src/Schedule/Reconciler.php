<?php
/**
 * Re-registers missing cron events from stored metadata and removes stale metadata.
 *
 * Needed after deactivation (which clears all cron events) and after upgrades.
 *
 * @package ProductUnpublisher
 */

namespace ProductUnpublisher\Schedule;

use ProductUnpublisher\Config;
use ProductUnpublisher\Storage\ScheduleStore;
use ProductUnpublisher\Time\Clock;

defined( 'ABSPATH' ) || exit;

final class Reconciler {

	private const BATCH_SIZE = 200;

	public function __construct(
		private ScheduleManager $schedules,
		private ScheduleStore $store,
		private Clock $clock
	) {}

	/**
	 * @return array{registered:int,removed:int,failed:int}
	 */
	public function reconcile(): array {
		$summary = array(
			'registered' => 0,
			'removed'    => 0,
			'failed'     => 0,
		);

		$page = 1;

		do {
			$ids = $this->product_ids_with_schedule( $page );

			foreach ( $ids as $product_id ) {
				$this->reconcile_product( (int) $product_id, $summary );
			}

			++$page;
		} while ( count( $ids ) === self::BATCH_SIZE );

		return $summary;
	}

	/**
	 * @param array{registered:int,removed:int,failed:int} $summary Running totals.
	 */
	private function reconcile_product( int $product_id, array &$summary ): void {
		$status    = get_post_status( $product_id );
		$timestamp = $this->store->get_timestamp( $product_id );

		if ( null === $timestamp || ! in_array( $status, Config::SCHEDULABLE_STATUSES, true ) ) {
			if ( $this->schedules->remove( $product_id ) ) {
				++$summary['removed'];
			} else {
				++$summary['failed'];
			}
			return;
		}

		if ( $this->schedules->has_event( $product_id ) ) {
			return;
		}

		// Overdue schedules run shortly, not in the past.
		$run_at = max( $timestamp, $this->clock->now() + 60 );

		if ( $this->schedules->register_event( $product_id, $run_at ) ) {
			++$summary['registered'];
		} else {
			++$summary['failed'];
		}
	}

	/**
	 * @return int[]
	 */
	private function product_ids_with_schedule( int $page ): array {
		$query = new \WP_Query(
			array(
				'post_type'              => Config::POST_TYPE,
				'post_status'            => 'any',
				'meta_key'               => Config::META_KEY, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'fields'                 => 'ids',
				'posts_per_page'         => self::BATCH_SIZE,
				'paged'                  => $page,
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
				'update_post_meta_cache' => false,
				'suppress_filters'       => true,
			)
		);

		return array_map( 'intval', $query->posts );
	}
}
