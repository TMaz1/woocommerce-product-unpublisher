<?php

use ProductUnpublisher\Config;
use ProductUnpublisher\Plugin;
use ProductUnpublisher\Tests\Integration\ProductTestCase;

final class StatusAndCronIntegrationTest extends ProductTestCase {

	public function set_up(): void {
		parent::set_up();

		$this->services->status_listener()->register();
		$this->services->runner()->register();
	}

	private function schedule( int $id, int $at = 0 ): void {
		$this->assertTrue( $this->services->schedules->replace( $id, $at ?: $this->future_minute() ) );
	}

	/** @dataProvider leaving_statuses */
	public function test_leaving_publish_removes_the_schedule( string $new_status ): void {
		$id = $this->create_product();
		$this->schedule( $id );

		wp_update_post( array( 'ID' => $id, 'post_status' => $new_status ) );

		$this->assertSame( 0, $this->event_count( $id ) );
		$this->assertFalse( metadata_exists( 'post', $id, Config::META_KEY ) );
	}

	public static function leaving_statuses(): array {
		return array( 'draft' => array( 'draft' ), 'private' => array( 'private' ), 'pending' => array( 'pending' ) );
	}

	public function test_trashing_removes_the_schedule(): void {
		$id = $this->create_product();
		$this->schedule( $id );

		wp_trash_post( $id );

		$this->assertSame( 0, $this->event_count( $id ) );
		$this->assertFalse( metadata_exists( 'post', $id, Config::META_KEY ) );
	}

	public function test_ordinary_updates_keep_the_schedule(): void {
		$id = $this->create_product();
		$at = $this->future_minute();
		$this->schedule( $id, $at );

		wp_update_post( array( 'ID' => $id, 'post_title' => 'Renamed' ) );

		$this->assertSame( 1, $this->event_count( $id ) );
		$this->assertSame( $at, $this->services->schedules->get_timestamp( $id ) );
	}

	public function test_republishing_a_draft_clears_stale_schedule_data(): void {
		$id = $this->create_product( 'draft' );
		update_post_meta( $id, Config::META_KEY, wp_date( 'Y-m-d H:i:s', $this->future_minute(), wp_timezone() ) );
		wp_schedule_single_event( $this->future_minute(), Config::CRON_HOOK, array( $id ) );

		wp_update_post( array( 'ID' => $id, 'post_status' => 'publish' ) );

		$this->assertSame( 0, $this->event_count( $id ) );
		$this->assertFalse( metadata_exists( 'post', $id, Config::META_KEY ) );
	}

	public function test_a_due_cron_event_moves_the_product_to_draft_and_cleans_up(): void {
		$id = $this->create_product();
		$this->schedule( $id );

		// Make it due by rewriting metadata and the event.
		update_post_meta( $id, Config::META_KEY, wp_date( 'Y-m-d H:i:s', time() - 120, wp_timezone() ) );

		do_action( Config::CRON_HOOK, $id );

		$this->assertSame( 'draft', get_post_status( $id ) );
		$this->assertSame( 0, $this->event_count( $id ) );
		$this->assertFalse( metadata_exists( 'post', $id, Config::META_KEY ) );
		$this->assertFalse( metadata_exists( 'post', $id, Config::ATTEMPTS_META ) );
	}

	public function test_an_event_that_fires_early_does_not_unpublish(): void {
		$id = $this->create_product();
		$at = $this->future_minute();
		$this->schedule( $id, $at );

		do_action( Config::CRON_HOOK, $id );

		$this->assertSame( 'publish', get_post_status( $id ) );
		$this->assertSame( $at, wp_next_scheduled( Config::CRON_HOOK, array( $id ) ) );
	}

	public function test_a_due_event_for_a_future_product_is_retried_not_unpublished(): void {
		$id = $this->create_product( 'future', array( 'post_date' => gmdate( 'Y-m-d H:i:s', time() + 3 * DAY_IN_SECONDS ) ) );
		update_post_meta( $id, Config::META_KEY, wp_date( 'Y-m-d H:i:s', time() - 60, wp_timezone() ) );

		do_action( Config::CRON_HOOK, $id );

		$this->assertSame( 'future', get_post_status( $id ) );
		$this->assertSame( 1, $this->event_count( $id ) );
		$this->assertGreaterThan( time(), wp_next_scheduled( Config::CRON_HOOK, array( $id ) ) );
	}

	public function test_a_deleted_product_leaves_no_event_behind(): void {
		$id = $this->create_product();
		$this->schedule( $id );

		wp_delete_post( $id, true );
		do_action( Config::CRON_HOOK, $id );

		$this->assertSame( 0, $this->event_count( $id ) );
	}

	public function test_reconcile_restores_events_after_deactivation_and_activation(): void {
		$keep  = $this->create_product();
		$stale = $this->create_product( 'draft' );
		$at    = $this->future_minute();
		$this->schedule( $keep, $at );
		update_post_meta( $stale, Config::META_KEY, wp_date( 'Y-m-d H:i:s', $at, wp_timezone() ) );

		Plugin::deactivate();
		$this->assertSame( 0, $this->event_count( $keep ) );
		$this->assertTrue( metadata_exists( 'post', $keep, Config::META_KEY ), 'deactivation keeps the schedule data' );

		$summary = Plugin::instance()->reconcile();

		$this->assertSame( 1, $this->event_count( $keep ) );
		$this->assertSame( $at, wp_next_scheduled( Config::CRON_HOOK, array( $keep ) ) );
		$this->assertSame( 0, $this->event_count( $stale ) );
		$this->assertFalse( metadata_exists( 'post', $stale, Config::META_KEY ), 'stale metadata on a Draft is dropped' );
		$this->assertSame( 1, $summary['registered'] );
		$this->assertSame( 1, $summary['removed'] );
	}

	public function test_reconcile_runs_overdue_schedules_soon_rather_than_in_the_past(): void {
		$id = $this->create_product();
		update_post_meta( $id, Config::META_KEY, wp_date( 'Y-m-d H:i:s', time() - 3600, wp_timezone() ) );

		$this->services->reconciler()->reconcile();

		$this->assertGreaterThan( time(), wp_next_scheduled( Config::CRON_HOOK, array( $id ) ) );
	}
}
