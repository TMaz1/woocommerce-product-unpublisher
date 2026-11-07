<?php
/**
 * Moves a published product to Draft and cleans up its schedule.
 *
 * Shared by the cron runner and the bulk "unpublish now" action so both behave identically.
 *
 * @package ProductUnpublisher
 */

namespace ProductUnpublisher\Product;

use ProductUnpublisher\Diagnostics\FailureLog;
use ProductUnpublisher\Schedule\ScheduleManager;

defined( 'ABSPATH' ) || exit;

final class ProductUnpublisher {

	public function __construct(
		private ProductGateway $products,
		private ScheduleManager $schedules,
		private FailureLog $failures
	) {}

	public function unpublish_now( int $product_id ): UnpublishOutcome {
		if ( ! $this->products->exists( $product_id ) ) {
			return UnpublishOutcome::not_found();
		}

		if ( 'publish' !== $this->products->status( $product_id ) ) {
			return UnpublishOutcome::not_published();
		}

		$error = $this->products->set_draft( $product_id );

		if ( null !== $error ) {
			return UnpublishOutcome::failed( $error );
		}

		// The status listener normally clears the schedule already; this is a verified, idempotent second pass.
		if ( ! $this->schedules->remove( $product_id ) ) {
			$this->failures->record( $product_id, 'cleanup_failed' );

			return UnpublishOutcome::cleanup_failed();
		}

		return UnpublishOutcome::unpublished();
	}
}
