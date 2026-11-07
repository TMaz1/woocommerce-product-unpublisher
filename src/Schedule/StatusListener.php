<?php
/**
 * Drops schedules when a product leaves (or re-enters) a publishable state.
 *
 * @package ProductUnpublisher
 */

namespace ProductUnpublisher\Schedule;

use ProductUnpublisher\Config;
use ProductUnpublisher\Diagnostics\FailureLog;

defined( 'ABSPATH' ) || exit;

final class StatusListener {

	public function __construct(
		private ScheduleManager $schedules,
		private FailureLog $failures
	) {}

	public function register(): void {
		add_action( 'transition_post_status', array( $this, 'status_changed' ), 20, 3 );
	}

	/**
	 * @param string   $new_status New status.
	 * @param string   $old_status Old status.
	 * @param \WP_Post $post       Post.
	 */
	public function status_changed( $new_status, $old_status, $post ): void {
		if ( ! $post instanceof \WP_Post || Config::POST_TYPE !== $post->post_type ) {
			return;
		}

		if ( $new_status === $old_status ) {
			return;
		}

		$schedulable_now = in_array( $new_status, Config::SCHEDULABLE_STATUSES, true );
		$was_schedulable = in_array( $old_status, Config::SCHEDULABLE_STATUSES, true );

		// Leaving a schedulable state (draft, private, pending, trash...) invalidates the schedule.
		// Entering one from a non-schedulable state clears anything stale left behind.
		if ( $schedulable_now && $was_schedulable ) {
			return;
		}

		if ( ! $this->schedules->has_schedule( $post->ID ) ) {
			return;
		}

		if ( ! $this->schedules->remove( $post->ID ) ) {
			$this->failures->record( $post->ID, 'cleanup_failed' );
		}
	}
}
