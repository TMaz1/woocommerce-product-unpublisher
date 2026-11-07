<?php
/**
 * Cron callback: moves a due product to Draft.
 *
 * @package ProductUnpublisher
 */

namespace ProductUnpublisher\Schedule;

use ProductUnpublisher\Config;
use ProductUnpublisher\Diagnostics\FailureLog;
use ProductUnpublisher\Product\ProductGateway;
use ProductUnpublisher\Product\ProductUnpublisher;
use ProductUnpublisher\Product\UnpublishOutcome;
use ProductUnpublisher\Storage\ScheduleStore;
use ProductUnpublisher\Time\Clock;

defined( 'ABSPATH' ) || exit;

final class UnpublishRunner {

	public function __construct(
		private ScheduleManager $schedules,
		private ScheduleStore $store,
		private ProductGateway $products,
		private ProductUnpublisher $unpublisher,
		private FailureLog $failures,
		private RetryPolicy $retry,
		private Clock $clock
	) {}

	public function register(): void {
		add_action( Config::CRON_HOOK, array( $this, 'run' ), 10, 1 );
	}

	/**
	 * Execute a scheduled unpublish.
	 *
	 * @param mixed $product_id Product ID from the cron event.
	 */
	public function run( $product_id ): void {
		$product_id = is_numeric( $product_id ) ? max( 0, (int) $product_id ) : 0;

		if ( ! $product_id ) {
			return;
		}

		// Product deleted, or not a product: drop any leftover data.
		if ( ! $this->products->exists( $product_id ) ) {
			$this->schedules->remove( $product_id );
			return;
		}

		$timestamp = $this->store->get_timestamp( $product_id );

		if ( null === $timestamp ) {
			// Event without a valid schedule: clean it up.
			if ( ! $this->schedules->remove( $product_id ) ) {
				$this->failures->record( $product_id, 'cleanup_failed' );
			}
			return;
		}

		$now = $this->clock->now();

		if ( $timestamp > $now ) {
			// Event fired early (e.g. site timezone changed): re-register at the stored time.
			if ( ! $this->schedules->register_event( $product_id, $timestamp ) ) {
				$this->record_failure( $product_id, 'reschedule_failed' );
			}
			return;
		}

		$status = $this->products->status( $product_id );

		if ( 'future' === $status ) {
			// Not yet published: check again shortly after publication.
			$publish_at = $this->products->publication_timestamp( $product_id );
			$retry_at   = max( $now + 60, null !== $publish_at ? $publish_at + 60 : 0 );

			if ( ! $this->schedules->register_event( $product_id, $retry_at ) ) {
				$this->record_failure( $product_id, 'publication_retry_failed' );
			}
			return;
		}

		if ( 'publish' !== $status ) {
			// Draft, private, trashed...: a stale schedule.
			if ( ! $this->schedules->remove( $product_id ) ) {
				$this->failures->record( $product_id, 'cleanup_failed' );
			}
			return;
		}

		$outcome = $this->unpublisher->unpublish_now( $product_id );

		if ( UnpublishOutcome::FAILED === $outcome->status ) {
			$this->record_failure( $product_id, '' !== $outcome->reason ? $outcome->reason : 'update_error' );
		}

		// UNPUBLISHED needs nothing more. CLEANUP_FAILED has already been logged by the unpublisher; the
		// product is Draft, so retrying the status change would be pointless.
	}

	/**
	 * Count a failed attempt, log it, and schedule a retry unless the limit is reached.
	 */
	private function record_failure( int $product_id, string $reason ): void {
		$attempts = $this->store->increment_attempts( $product_id );

		$this->failures->record( $product_id, $reason, $attempts );

		if ( $this->retry->exhausted( $attempts ) ) {
			$this->failures->record( $product_id, 'retries_exhausted', $attempts );
			return;
		}

		$retry_at = $this->clock->now() + $this->retry->delay( $attempts );

		if ( ! $this->schedules->register_event( $product_id, $retry_at ) ) {
			$this->failures->record( $product_id, 'retry_registration_failed', $attempts );
		}
	}
}
