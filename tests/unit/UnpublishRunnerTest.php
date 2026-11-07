<?php

use PHPUnit\Framework\TestCase;
use ProductUnpublisher\Product\ProductUnpublisher;
use ProductUnpublisher\Schedule\RetryPolicy;
use ProductUnpublisher\Schedule\ScheduleManager;
use ProductUnpublisher\Schedule\UnpublishRunner;
use ProductUnpublisher\Tests\FakeCronGateway;
use ProductUnpublisher\Tests\FakeProductGateway;
use ProductUnpublisher\Tests\FixedClock;
use ProductUnpublisher\Tests\InMemoryFailureLog;
use ProductUnpublisher\Tests\InMemoryScheduleStore;

final class UnpublishRunnerTest extends TestCase {

	private FixedClock $clock;
	private InMemoryScheduleStore $store;
	private FakeCronGateway $cron;
	private FakeProductGateway $products;
	private InMemoryFailureLog $failures;
	private ScheduleManager $manager;
	private UnpublishRunner $runner;

	protected function setUp(): void {
		$this->clock    = new FixedClock( 1_800_000_000 );
		$this->store    = new InMemoryScheduleStore( $this->clock );
		$this->cron     = new FakeCronGateway();
		$this->products = new FakeProductGateway();
		$this->failures = new InMemoryFailureLog();
		$this->manager  = new ScheduleManager( $this->store, $this->cron, $this->clock, $this->failures );

		// Mimic the status listener, which clears the schedule when a product becomes Draft.
		$this->products->after_draft = function ( int $id ): void {
			$this->manager->remove( $id );
		};

		$this->runner = new UnpublishRunner(
			$this->manager,
			$this->store,
			$this->products,
			new ProductUnpublisher( $this->products, $this->manager, $this->failures ),
			$this->failures,
			new RetryPolicy( 3 ),
			$this->clock
		);
	}

	/** Schedule product $id as already due. */
	private function due( int $id, string $status = 'publish' ): void {
		$this->products->statuses[ $id ] = $status;
		$this->store->set_timestamp( $id, $this->clock->now - 60 );
		$this->cron->events[ $id ] = $this->clock->now - 60;
	}

	public function test_a_due_published_product_becomes_draft_and_loses_its_schedule(): void {
		$this->due( 5 );

		$this->runner->run( 5 );

		$this->assertSame( 'draft', $this->products->statuses[5] );
		$this->assertFalse( $this->store->has_data( 5 ) );
		$this->assertSame( array(), $this->cron->events );
		$this->assertSame( array(), $this->failures->items );
	}

	public function test_accepts_numeric_strings_and_ignores_garbage(): void {
		$this->due( 5 );

		$this->runner->run( 'abc' );
		$this->runner->run( 0 );
		$this->runner->run( null );
		$this->assertSame( 'publish', $this->products->statuses[5] );

		$this->runner->run( '5' );
		$this->assertSame( 'draft', $this->products->statuses[5] );
	}

	public function test_a_missing_product_has_its_leftovers_removed(): void {
		$this->store->set_timestamp( 5, $this->clock->now - 60 );
		$this->cron->events[5] = $this->clock->now - 60;

		$this->runner->run( 5 );

		$this->assertFalse( $this->store->has_data( 5 ) );
		$this->assertSame( array(), $this->cron->events );
	}

	public function test_an_event_without_valid_metadata_is_cleaned_up(): void {
		$this->products->statuses[5] = 'publish';
		$this->cron->events[5]       = $this->clock->now - 60;

		$this->runner->run( 5 );

		$this->assertSame( 'publish', $this->products->statuses[5] );
		$this->assertSame( array(), $this->cron->events );
	}

	public function test_an_event_that_fires_early_is_re_registered_at_the_stored_time(): void {
		$later = $this->clock->now + 3600;
		$this->products->statuses[5] = 'publish';
		$this->store->set_timestamp( 5, $later );

		$this->runner->run( 5 );

		$this->assertSame( 'publish', $this->products->statuses[5] );
		$this->assertSame( array( 5 => $later ), $this->cron->events );
	}

	public function test_a_future_product_is_retried_after_its_publication_time(): void {
		$this->due( 5, 'future' );
		$this->products->publish_at[5] = $this->clock->now + 7200;

		$this->runner->run( 5 );

		$this->assertSame( 'future', $this->products->statuses[5] );
		$this->assertSame( array( 5 => $this->clock->now + 7260 ), $this->cron->events );
		$this->assertSame( array(), $this->failures->items );
	}

	public function test_a_future_product_with_unknown_publication_time_is_retried_in_a_minute(): void {
		$this->due( 5, 'future' );

		$this->runner->run( 5 );

		$this->assertSame( array( 5 => $this->clock->now + 60 ), $this->cron->events );
	}

	/**
	 * @dataProvider inactive_statuses
	 */
	public function test_a_stale_schedule_on_a_non_published_product_is_removed( string $status ): void {
		$this->due( 5, $status );

		$this->runner->run( 5 );

		$this->assertSame( $status, $this->products->statuses[5] );
		$this->assertFalse( $this->store->has_data( 5 ) );
		$this->assertSame( array(), $this->cron->events );
	}

	public static function inactive_statuses(): array {
		return array( 'draft' => array( 'draft' ), 'private' => array( 'private' ), 'pending' => array( 'pending' ), 'trash' => array( 'trash' ) );
	}

	public function test_a_failed_status_change_counts_an_attempt_and_schedules_a_retry(): void {
		$this->due( 5 );
		$this->products->draft_errors[5] = 'update_error';

		$this->runner->run( 5 );

		$this->assertSame( 1, $this->store->get_attempts( 5 ) );
		$this->assertSame( array( 5 => $this->clock->now + 30 ), $this->cron->events );
		$this->assertSame( array( 'update_error' ), $this->failures->reasons() );
		$this->assertTrue( $this->store->has_data( 5 ), 'metadata is kept for the retry' );
	}

	public function test_retry_delays_back_off(): void {
		$this->due( 5 );
		$this->products->draft_errors[5] = 'update_error';

		$this->runner->run( 5 );
		$this->runner->run( 5 );

		$this->assertSame( 2, $this->store->get_attempts( 5 ) );
		$this->assertSame( array( 5 => $this->clock->now + 60 ), $this->cron->events );
	}

	public function test_retries_stop_at_the_limit_and_are_reported(): void {
		$this->due( 5 );
		$this->products->draft_errors[5] = 'status_not_changed';

		$this->runner->run( 5 );
		$this->runner->run( 5 );
		$this->cron->events = array();
		$this->runner->run( 5 );

		$this->assertSame( 3, $this->store->get_attempts( 5 ) );
		$this->assertSame( array(), $this->cron->events, 'no further event after the limit' );
		$this->assertContains( 'retries_exhausted', $this->failures->reasons() );
		$this->assertTrue( $this->store->has_data( 5 ), 'metadata stays for manual investigation' );
	}

	public function test_failed_cleanup_after_unpublishing_is_logged_and_not_retried(): void {
		$this->due( 5 );
		$this->products->after_draft = null; // Nothing clears the schedule automatically...
		$this->cron->fail_clear      = true;  // ...and the manual cleanup fails too.

		$this->runner->run( 5 );

		$this->assertSame( 'draft', $this->products->statuses[5] );
		$this->assertSame( array( 'cleanup_failed' ), $this->failures->reasons() );
		$this->assertSame( 0, $this->store->get_attempts( 5 ), 'the product is already Draft: no retry cycle' );
	}
}
