<?php

use PHPUnit\Framework\TestCase;
use ProductUnpublisher\Tests\FixedClock;
use ProductUnpublisher\Time\LocalTimeCodec;

final class LocalTimeCodecTest extends TestCase {

	private FixedClock $clock;
	private LocalTimeCodec $codec;

	protected function setUp(): void {
		$this->clock = new FixedClock( 1000 );
		$this->codec = new LocalTimeCodec( $this->clock );
	}

	public function test_round_trip(): void {
		$timestamp = $this->clock->local( '2027-06-01 14:45:00' );

		$this->assertSame( '2027-06-01 14:45:00', $this->codec->format( $timestamp ) );
		$this->assertSame( $timestamp, $this->codec->parse( '2027-06-01 14:45:00' ) );
	}

	/**
	 * @dataProvider invalid_values
	 */
	public function test_invalid_values_are_rejected( mixed $value ): void {
		$this->assertNull( $this->codec->parse( $value ) );
	}

	public static function invalid_values(): array {
		return array(
			'null'            => array( null ),
			'empty'           => array( '' ),
			'int'             => array( 12345 ),
			'array'           => array( array( '2027-06-01 14:45:00' ) ),
			'date only'       => array( '2027-06-01' ),
			'overflowing day' => array( '2027-02-31 10:00:00' ),
			'trailing text'   => array( '2027-06-01 14:45:00 UTC' ),
			'garbage'         => array( 'tomorrow' ),
		);
	}

	public function test_formatting_follows_the_site_timezone(): void {
		$utc = new FixedClock( 0, 'UTC' );

		$this->assertSame( '2027-01-01 00:00:00', ( new LocalTimeCodec( $utc ) )->format( 1798761600 ) );
		$this->assertSame( '2026-12-31 19:00:00', $this->codec->format( 1798761600 ) );
	}
}
