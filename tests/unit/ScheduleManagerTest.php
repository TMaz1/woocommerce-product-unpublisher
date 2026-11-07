<?php

use PHPUnit\Framework\TestCase;
use ProductUnpublisher\Schedule\ScheduleManager;
use ProductUnpublisher\Tests\FakeCronGateway;
use ProductUnpublisher\Tests\FixedClock;
use ProductUnpublisher\Tests\InMemoryFailureLog;
use ProductUnpublisher\Tests\InMemoryScheduleStore;

final class ScheduleManagerTest extends TestCase {

	private FixedClock $clock;
	private InMemoryScheduleStore $store;
	private FakeCronGateway $cron;
	private InMemoryFailureLog $failures;
	private ScheduleManager $manager;

	protected function setUp(): void {
		$this->clock    = new FixedClock( 1_800_000_000 );
		$this->store    = new InMemoryScheduleStore( $this->clock );
		$this->cron     = new FakeCronGateway();
		$this->failures = new InMemoryFailureLog();
		$this->manager  = new ScheduleManager( $this->store, $this->cron, $this->clock, $this->failures );
	}

	private function future( int $seconds ): int {
		// Whole minutes keep the stored "Y-m-d H:i:s" string exact.
		return $this->clock->now + ( intdiv( $seconds, 60 ) * 60 );
	}

	public function test_replace_creates_metadata_and_exactly_one_event(): void {
		$at = $this->future( 3600 );

		$this->assertTrue( $this->manager->replace( 7, $at ) );

		$this->assertSame( $at, $this->store->get_timestamp( 7 ) );
		$this->assertSame( array( 7 => $at ), $this->cron->events );
	}

	public function test_replace_swaps_an_existing_schedule_without_duplicating_events(): void {
		$first  = $this->future( 3600 );
		$second = $this->future( 7200 );

		$this->manager->replace( 7, $first );
		$this->assertTrue( $this->manager->replace( 7, $second ) );

		$this->assertSame( array( 7 => $second ), $this->cron->events );
		$this->assertSame( $second, $this->store->get_timestamp( 7 ) );
		$this->assertCount( 1, $this->cron->events );
	}

	public function test_replace_clears_the_old_event_before_registering_the_new_one(): void {
		$this->manager->replace( 7, $this->future( 3600 ) );
		$this->cron->calls = array();

		$new = $this->future( 3660 );
		$this->manager->replace( 7, $new );

		$this->assertSame( array( 'clear:7', "schedule:7:$new" ), $this->cron->calls );
	}

	public function test_replace_clears_retry_attempts(): void {
		$this->store->attempts[7] = 3;

		$this->manager->replace( 7, $this->future( 3600 ) );

		$this->assertSame( 0, $this->store->get_attempts( 7 ) );
	}

	public function test_failed_registration_preserves_previous_metadata_and_event(): void {
		$old = $this->future( 3600 );
		$new = $this->future( 7200 );
		$this->manager->replace( 7, $old );

		$this->cron->fail_schedule_for = array( $new );

		$this->assertFalse( $this->manager->replace( 7, $new ) );
		$this->assertSame( $old, $this->store->get_timestamp( 7 ) );
		$this->assertSame( array( 7 => $old ), $this->cron->events );
	}

	public function test_failed_metadata_write_rolls_back_cron_and_previous_metadata(): void {
		$old = $this->future( 3600 );
		$new = $this->future( 7200 );
		$this->manager->replace( 7, $old );

		$this->store->fail_writes = true;

		$this->assertFalse( $this->manager->replace( 7, $new ) );
		$this->assertSame( $old, $this->store->get_timestamp( 7 ) );
		$this->assertSame( array( 7 => $old ), $this->cron->events );
	}

	public function test_failed_first_schedule_leaves_nothing_behind(): void {
		$new = $this->future( 7200 );
		$this->cron->fail_schedule_for = array( $new );

		$this->assertFalse( $this->manager->replace( 9, $new ) );
		$this->assertNull( $this->store->get_raw( 9 ) );
		$this->assertSame( array(), $this->cron->events );
	}

	public function test_an_overdue_previous_event_is_restored_too(): void {
		// Previous event is already due (e.g. waiting on a retry): it must not be lost.
		$overdue = $this->clock->now - 600;
		$this->cron->events[7] = $overdue;
		$this->store->set_timestamp( 7, $overdue );

		$new = $this->future( 7200 );
		$this->cron->fail_schedule_for = array( $new );

		$this->assertFalse( $this->manager->replace( 7, $new ) );
		$this->assertSame( array( 7 => $overdue ), $this->cron->events );
	}

	public function test_replace_rejects_past_timestamps_and_invalid_ids(): void {
		$this->assertFalse( $this->manager->replace( 7, $this->clock->now - 60 ) );
		$this->assertFalse( $this->manager->replace( 0, $this->future( 3600 ) ) );
		$this->assertSame( array(), $this->cron->events );
	}

	public function test_failure_to_clear_keeps_the_previous_schedule(): void {
		$old = $this->future( 3600 );
		$this->manager->replace( 7, $old );

		$this->cron->fail_clear = true;

		$this->assertFalse( $this->manager->replace( 7, $this->future( 7200 ) ) );
		$this->assertSame( $old, $this->store->get_timestamp( 7 ) );
		$this->assertSame( array( 7 => $old ), $this->cron->events );
	}

	public function test_remove_clears_metadata_attempts_and_event(): void {
		$this->manager->replace( 7, $this->future( 3600 ) );
		$this->store->attempts[7] = 2;

		$this->assertTrue( $this->manager->remove( 7 ) );

		$this->assertFalse( $this->store->has_data( 7 ) );
		$this->assertSame( array(), $this->cron->events );
		$this->assertFalse( $this->manager->has_schedule( 7 ) );
	}

	public function test_remove_keeps_metadata_when_the_event_cannot_be_cleared(): void {
		$at = $this->future( 3600 );
		$this->manager->replace( 7, $at );
		$this->cron->fail_clear = true;

		$this->assertFalse( $this->manager->remove( 7 ) );
		$this->assertSame( $at, $this->store->get_timestamp( 7 ) );
	}

	public function test_remove_cleans_an_orphaned_event_without_metadata(): void {
		$this->cron->events[7] = $this->future( 3600 );

		$this->assertTrue( $this->manager->has_schedule( 7 ) );
		$this->assertTrue( $this->manager->remove( 7 ) );
		$this->assertSame( array(), $this->cron->events );
	}

	public function test_remove_on_a_product_without_data_succeeds(): void {
		$this->assertFalse( $this->manager->has_schedule( 7 ) );
		$this->assertTrue( $this->manager->remove( 7 ) );
		$this->assertFalse( $this->manager->remove( 0 ) );
	}

	public function test_register_event_is_idempotent_for_the_same_time(): void {
		$at = $this->future( 3600 );

		$this->assertTrue( $this->manager->register_event( 7, $at ) );
		$this->cron->calls = array();
		$this->assertTrue( $this->manager->register_event( 7, $at ) );

		$this->assertSame( array(), $this->cron->calls );
	}

	public function test_register_event_replaces_a_different_time(): void {
		$this->manager->register_event( 7, $this->future( 3600 ) );
		$later = $this->future( 7200 );

		$this->assertTrue( $this->manager->register_event( 7, $later ) );
		$this->assertSame( array( 7 => $later ), $this->cron->events );
	}
}
