<?php
/**
 * Keeps product metadata and cron events consistent.
 *
 * Every product has at most one cron event. Replacing a schedule is transactional: when any step
 * fails the previous metadata and cron event are put back wherever possible.
 *
 * @package ProductUnpublisher
 */

namespace ProductUnpublisher\Schedule;

use ProductUnpublisher\Cron\CronGateway;
use ProductUnpublisher\Diagnostics\FailureLog;
use ProductUnpublisher\Storage\ScheduleStore;
use ProductUnpublisher\Time\Clock;

defined( 'ABSPATH' ) || exit;

final class ScheduleManager {

	public function __construct(
		private ScheduleStore $store,
		private CronGateway $cron,
		private Clock $clock,
		private FailureLog $failures
	) {}

	/**
	 * Stored schedule timestamp, if any.
	 */
	public function get_timestamp( int $product_id ): ?int {
		return $this->store->get_timestamp( $product_id );
	}

	/**
	 * Whether the product has schedule metadata, retry data, or a cron event.
	 */
	public function has_schedule( int $product_id ): bool {
		return $this->store->has_data( $product_id ) || false !== $this->cron->next_run( $product_id );
	}

	/**
	 * Replace (or create) the schedule.
	 *
	 * @return bool True when metadata and cron event both hold the new time.
	 */
	public function replace( int $product_id, int $timestamp ): bool {
		if ( $product_id <= 0 || $timestamp <= $this->clock->now() ) {
			return false;
		}

		$old_raw  = $this->store->get_raw( $product_id );
		$old_cron = $this->cron->next_run( $product_id );

		// Remove the previous event first: WordPress refuses events within ten minutes of an identical one.
		if ( ! $this->cron->clear( $product_id ) ) {
			$this->restore_cron( $product_id, $old_cron );
			return false;
		}

		if ( ! $this->cron->schedule( $product_id, $timestamp ) ) {
			$this->cron->clear( $product_id );
			$this->restore_cron( $product_id, $old_cron );
			return false;
		}

		$this->store->set_timestamp( $product_id, $timestamp );

		if ( $this->store->get_timestamp( $product_id ) !== $timestamp ) {
			$this->cron->clear( $product_id );
			$this->store->restore_raw( $product_id, $old_raw );
			$this->restore_cron( $product_id, $old_cron );
			return false;
		}

		$this->store->clear_attempts( $product_id );

		return true;
	}

	/**
	 * Remove metadata, retry counter and cron event.
	 *
	 * Metadata is kept when the cron event cannot be cleared so no orphaned event is left behind.
	 *
	 * @return bool True when nothing remains.
	 */
	public function remove( int $product_id ): bool {
		if ( $product_id <= 0 ) {
			return false;
		}

		if ( ! $this->cron->clear( $product_id ) ) {
			return false;
		}

		$this->store->delete( $product_id );
		$this->store->clear_attempts( $product_id );

		return ! $this->store->has_data( $product_id ) && false === $this->cron->next_run( $product_id );
	}

	/**
	 * Register only the cron event (used for retries and re-registration). Metadata is untouched.
	 */
	public function register_event( int $product_id, int $timestamp ): bool {
		$existing = $this->cron->next_run( $product_id );

		if ( $existing === $timestamp ) {
			return true;
		}

		if ( false !== $existing && ! $this->cron->clear( $product_id ) ) {
			return false;
		}

		return $this->cron->schedule( $product_id, $timestamp );
	}

	/**
	 * Number of failed unpublish attempts recorded for the product.
	 */
	public function attempts( int $product_id ): int {
		return $this->store->get_attempts( $product_id );
	}

	/**
	 * Whether a cron event exists for the product.
	 */
	public function has_event( int $product_id ): bool {
		return false !== $this->cron->next_run( $product_id );
	}

	/**
	 * Put a previous cron event back after a failed replacement. Past timestamps are allowed: WordPress runs them on the next cron tick.
	 *
	 * @param int|false $old_cron Previous event timestamp.
	 */
	private function restore_cron( int $product_id, $old_cron ): void {
		if ( false === $old_cron ) {
			return;
		}

		if ( ! $this->register_event( $product_id, $old_cron ) ) {
			$this->failures->record( $product_id, 'restore_failed' );
		}
	}
}
