<?php

namespace ProductUnpublisher\Tests;

use ProductUnpublisher\Cron\CronGateway;

final class FakeCronGateway implements CronGateway {

	/** @var array<int,int> One event per product, as WP-Cron would hold after clear + schedule. */
	public array $events = array();

	/** Timestamps for which schedule() must fail. */
	public array $fail_schedule_for = array();

	public bool $fail_clear = false;

	/** Log of calls, for ordering assertions. */
	public array $calls = array();

	public function next_run( int $product_id ) {
		return $this->events[ $product_id ] ?? false;
	}

	public function schedule( int $product_id, int $timestamp ): bool {
		$this->calls[] = "schedule:$product_id:$timestamp";

		if ( in_array( $timestamp, $this->fail_schedule_for, true ) ) {
			return false;
		}

		$this->events[ $product_id ] = $timestamp;

		return true;
	}

	public function clear( int $product_id ): bool {
		$this->calls[] = "clear:$product_id";

		if ( $this->fail_clear ) {
			return false;
		}

		unset( $this->events[ $product_id ] );

		return true;
	}
}
