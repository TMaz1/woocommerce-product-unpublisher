<?php

namespace ProductUnpublisher\Tests;

use ProductUnpublisher\Time\Clock;

final class FixedClock implements Clock {

	public function __construct( public int $now, private string $zone = 'America/New_York' ) {}

	public function now(): int {
		return $this->now;
	}

	public function timezone(): \DateTimeZone {
		return new \DateTimeZone( $this->zone );
	}

	/** Timestamp for a local wall-clock time in this clock's timezone. */
	public function local( string $datetime ): int {
		return ( new \DateTimeImmutable( $datetime, $this->timezone() ) )->getTimestamp();
	}
}
