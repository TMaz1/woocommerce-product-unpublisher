<?php

use ProductUnpublisher\Config;
use ProductUnpublisher\Cron\CronGateway;
use ProductUnpublisher\Cron\WpCronGateway;
use ProductUnpublisher\Tests\Integration\ProductTestCase;
use ProductUnpublisher\Tests\Integration\Services;

final class ScheduleManagerIntegrationTest extends ProductTestCase {

	public function test_replace_registers_exactly_one_real_cron_event_and_metadata(): void {
		$id = $this->create_product();
		$at = $this->future_minute();

		$this->assertTrue( $this->services->schedules->replace( $id, $at ) );

		$this->assertSame( 1, $this->event_count( $id ) );
		$this->assertSame( $at, wp_next_scheduled( Config::CRON_HOOK, array( $id ) ) );
		$this->assertSame( $at, $this->services->schedules->get_timestamp( $id ) );
		$this->assertSame( wp_date( 'Y-m-d H:i:00', $at, wp_timezone() ), $this->raw_meta( $id ) );
	}

	public function test_replacing_within_ten_minutes_does_not_duplicate_or_fail(): void {
		$id = $this->create_product();
		$at = $this->future_minute();

		$this->services->schedules->replace( $id, $at );

		// WordPress rejects identical events within 10 minutes unless the old one is cleared first.
		$this->assertTrue( $this->services->schedules->replace( $id, $at + 300 ) );

		$this->assertSame( 1, $this->event_count( $id ) );
		$this->assertSame( $at + 300, wp_next_scheduled( Config::CRON_HOOK, array( $id ) ) );
	}

	public function test_every_product_gets_its_own_event(): void {
		$ids = array( $this->create_product(), $this->create_product(), $this->create_product() );
		$at  = $this->future_minute();

		foreach ( $ids as $id ) {
			$this->assertTrue( $this->services->schedules->replace( $id, $at ) );
		}

		foreach ( $ids as $id ) {
			$this->assertSame( 1, $this->event_count( $id ) );
		}
	}

	public function test_failed_replacement_keeps_the_previous_schedule(): void {
		$id  = $this->create_product();
		$old = $this->future_minute( 2 );
		$new = $this->future_minute( 4 );

		$this->services->schedules->replace( $id, $old );

		// A cron gateway that refuses to register the new time.
		$real     = new WpCronGateway();
		$failing  = new class( $real, $new ) implements CronGateway {
			public function __construct( private WpCronGateway $real, private int $refuse ) {}

			public function next_run( int $product_id ) {
				return $this->real->next_run( $product_id );
			}

			public function schedule( int $product_id, int $timestamp ): bool {
				return $timestamp === $this->refuse ? false : $this->real->schedule( $product_id, $timestamp );
			}

			public function clear( int $product_id ): bool {
				return $this->real->clear( $product_id );
			}
		};
		$services = new Services( $failing );

		$this->assertFalse( $services->schedules->replace( $id, $new ) );

		$this->assertSame( $old, wp_next_scheduled( Config::CRON_HOOK, array( $id ) ) );
		$this->assertSame( $old, $this->services->schedules->get_timestamp( $id ) );
		$this->assertSame( 1, $this->event_count( $id ) );
	}

	public function test_remove_clears_event_schedule_and_retry_information(): void {
		$id = $this->create_product();

		$this->services->schedules->replace( $id, $this->future_minute() );
		update_post_meta( $id, Config::ATTEMPTS_META, 3 );

		$this->assertTrue( $this->services->schedules->remove( $id ) );

		$this->assertSame( 0, $this->event_count( $id ) );
		$this->assertFalse( metadata_exists( 'post', $id, Config::META_KEY ) );
		$this->assertFalse( metadata_exists( 'post', $id, Config::ATTEMPTS_META ) );
	}
}
