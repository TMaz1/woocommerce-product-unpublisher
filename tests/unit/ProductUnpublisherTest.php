<?php

use PHPUnit\Framework\TestCase;
use ProductUnpublisher\Product\ProductUnpublisher;
use ProductUnpublisher\Product\UnpublishOutcome;
use ProductUnpublisher\Schedule\ScheduleManager;
use ProductUnpublisher\Tests\FakeCronGateway;
use ProductUnpublisher\Tests\FakeProductGateway;
use ProductUnpublisher\Tests\FixedClock;
use ProductUnpublisher\Tests\InMemoryFailureLog;
use ProductUnpublisher\Tests\InMemoryScheduleStore;

final class ProductUnpublisherTest extends TestCase {

	private FixedClock $clock;
	private InMemoryScheduleStore $store;
	private FakeCronGateway $cron;
	private FakeProductGateway $products;
	private InMemoryFailureLog $failures;
	private ProductUnpublisher $unpublisher;

	protected function setUp(): void {
		$this->clock    = new FixedClock( 1_800_000_000 );
		$this->store    = new InMemoryScheduleStore( $this->clock );
		$this->cron     = new FakeCronGateway();
		$this->products = new FakeProductGateway();
		$this->failures = new InMemoryFailureLog();

		$manager           = new ScheduleManager( $this->store, $this->cron, $this->clock, $this->failures );
		$this->unpublisher = new ProductUnpublisher( $this->products, $manager, $this->failures );
	}

	private function scheduled( int $id, string $status = 'publish' ): void {
		$this->products->statuses[ $id ] = $status;
		$this->store->set_timestamp( $id, $this->clock->now + 3600 );
		$this->cron->events[ $id ] = $this->clock->now + 3600;
	}

	public function test_published_product_is_unpublished_and_cleaned(): void {
		$this->scheduled( 5 );

		$outcome = $this->unpublisher->unpublish_now( 5 );

		$this->assertSame( UnpublishOutcome::UNPUBLISHED, $outcome->status );
		$this->assertSame( 'draft', $this->products->statuses[5] );
		$this->assertFalse( $this->store->has_data( 5 ) );
		$this->assertSame( array(), $this->cron->events );
	}

	public function test_published_product_without_a_schedule_is_unpublished(): void {
		$this->products->statuses[5] = 'publish';

		$this->assertSame( UnpublishOutcome::UNPUBLISHED, $this->unpublisher->unpublish_now( 5 )->status );
	}

	/**
	 * @dataProvider other_statuses
	 */
	public function test_non_published_products_are_left_alone( string $status ): void {
		$this->scheduled( 5, $status );

		$outcome = $this->unpublisher->unpublish_now( 5 );

		$this->assertSame( UnpublishOutcome::NOT_PUBLISHED, $outcome->status );
		$this->assertSame( $status, $this->products->statuses[5] );
		$this->assertSame( array(), $this->products->drafted );
	}

	public static function other_statuses(): array {
		return array( 'draft' => array( 'draft' ), 'future' => array( 'future' ), 'private' => array( 'private' ), 'pending' => array( 'pending' ) );
	}

	public function test_missing_product(): void {
		$this->assertSame( UnpublishOutcome::NOT_FOUND, $this->unpublisher->unpublish_now( 99 )->status );
	}

	public function test_status_change_failure_is_reported_and_schedule_is_untouched(): void {
		$this->scheduled( 5 );
		$this->products->draft_errors[5] = 'update_error';

		$outcome = $this->unpublisher->unpublish_now( 5 );

		$this->assertSame( UnpublishOutcome::FAILED, $outcome->status );
		$this->assertSame( 'update_error', $outcome->reason );
		$this->assertTrue( $this->store->has_data( 5 ) );
		$this->assertSame( array( 5 => $this->clock->now + 3600 ), $this->cron->events );
	}

	public function test_cleanup_failure_after_unpublishing_is_distinct_and_logged(): void {
		$this->scheduled( 5 );
		$this->cron->fail_clear = true;

		$outcome = $this->unpublisher->unpublish_now( 5 );

		$this->assertSame( UnpublishOutcome::CLEANUP_FAILED, $outcome->status );
		$this->assertSame( 'draft', $this->products->statuses[5], 'the product really was unpublished' );
		$this->assertSame( array( 'cleanup_failed' ), $this->failures->reasons() );
	}
}
