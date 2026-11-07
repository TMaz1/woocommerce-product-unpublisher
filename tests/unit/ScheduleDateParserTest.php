<?php

use PHPUnit\Framework\TestCase;
use ProductUnpublisher\Tests\FixedClock;
use ProductUnpublisher\Time\InvalidScheduleDateException;
use ProductUnpublisher\Time\ScheduleDateParser;

final class ScheduleDateParserTest extends TestCase {

	private FixedClock $clock;
	private ScheduleDateParser $parser;

	protected function setUp(): void {
		// 5 Nov 2026 09:30 in New York (EST).
		$this->clock  = new FixedClock( ( new DateTimeImmutable( '2026-11-05 09:30:00', new DateTimeZone( 'America/New_York' ) ) )->getTimestamp() );
		$this->parser = new ScheduleDateParser( $this->clock );
	}

	private function fields( string $mm, string $jj, string $aa, string $hh, string $mn, string $prefix = 'wpu_' ): array {
		return array(
			$prefix . 'mm' => $mm,
			$prefix . 'jj' => $jj,
			$prefix . 'aa' => $aa,
			$prefix . 'hh' => $hh,
			$prefix . 'mn' => $mn,
		);
	}

	private function assertReason( string $reason, callable $callback ): void {
		try {
			$callback();
			$this->fail( 'Expected InvalidScheduleDateException' );
		} catch ( InvalidScheduleDateException $e ) {
			$this->assertSame( $reason, $e->reason() );
		}
	}

	public function test_valid_date_is_interpreted_in_the_site_timezone(): void {
		$timestamp = $this->parser->from_request( $this->fields( '12', '24', '2026', '10', '00' ), 'wpu_' );

		$this->assertSame( $this->clock->local( '2026-12-24 10:00:00' ), $timestamp );
	}

	public function test_bulk_prefix_is_supported(): void {
		$timestamp = $this->parser->from_request( $this->fields( '12', '24', '2026', '10', '00', 'wpu_bulk_' ), 'wpu_bulk_' );

		$this->assertSame( $this->clock->local( '2026-12-24 10:00:00' ), $timestamp );
	}

	public function test_missing_field_is_incomplete(): void {
		$fields = $this->fields( '12', '24', '2026', '10', '00' );
		unset( $fields['wpu_hh'] );

		$this->assertReason( InvalidScheduleDateException::INCOMPLETE, fn() => $this->parser->from_request( $fields, 'wpu_' ) );
	}

	public function test_non_string_field_is_incomplete(): void {
		$fields           = $this->fields( '12', '24', '2026', '10', '00' );
		$fields['wpu_jj'] = array( '24' );

		$this->assertReason( InvalidScheduleDateException::INCOMPLETE, fn() => $this->parser->from_request( $fields, 'wpu_' ) );
	}

	/**
	 * @dataProvider malformed_values
	 */
	public function test_malformed_values_are_invalid( string $mm, string $jj, string $aa, string $hh, string $mn ): void {
		$this->assertReason( InvalidScheduleDateException::INVALID, fn() => $this->parser->from_request( $this->fields( $mm, $jj, $aa, $hh, $mn ), 'wpu_' ) );
	}

	public static function malformed_values(): array {
		return array(
			'letters'        => array( '12', 'ab', '2026', '10', '00' ),
			'negative'       => array( '12', '-1', '2026', '10', '00' ),
			'decimal'        => array( '12', '1.5', '2026', '10', '00' ),
			'empty'          => array( '12', '', '2026', '10', '00' ),
			'too long'       => array( '12', '24', '20260', '10', '00' ),
			'month 13'       => array( '13', '24', '2026', '10', '00' ),
			'month 0'        => array( '00', '24', '2026', '10', '00' ),
			'day 0'          => array( '12', '0', '2026', '10', '00' ),
			'feb 30'         => array( '02', '30', '2027', '10', '00' ),
			'feb 29 non-leap' => array( '02', '29', '2027', '10', '00' ),
			'apr 31'         => array( '04', '31', '2027', '10', '00' ),
			'hour 24'        => array( '12', '24', '2026', '24', '00' ),
			'minute 60'      => array( '12', '24', '2026', '10', '60' ),
			'year before min' => array( '12', '24', '1999', '10', '00' ),
		);
	}

	public function test_leap_day_is_accepted(): void {
		$timestamp = $this->parser->from_request( $this->fields( '02', '29', '2028', '10', '00' ), 'wpu_' );

		$this->assertSame( $this->clock->local( '2028-02-29 10:00:00' ), $timestamp );
	}

	public function test_nonexistent_local_time_in_a_dst_gap_is_invalid(): void {
		// 14 March 2027 02:30 does not exist in New York.
		$this->assertReason( InvalidScheduleDateException::INVALID, fn() => $this->parser->from_request( $this->fields( '03', '14', '2027', '02', '30' ), 'wpu_' ) );
	}

	public function test_past_and_current_minute_are_rejected(): void {
		$this->assertReason( InvalidScheduleDateException::PAST, fn() => $this->parser->from_request( $this->fields( '11', '05', '2026', '09', '29' ), 'wpu_' ) );
		$this->assertReason( InvalidScheduleDateException::PAST, fn() => $this->parser->from_request( $this->fields( '11', '05', '2026', '09', '30' ), 'wpu_' ) );
		$this->assertReason( InvalidScheduleDateException::PAST, fn() => $this->parser->from_request( $this->fields( '01', '01', '2026', '00', '00' ), 'wpu_' ) );
	}

	public function test_next_minute_is_accepted(): void {
		$this->assertSame(
			$this->clock->local( '2026-11-05 09:31:00' ),
			$this->parser->from_request( $this->fields( '11', '05', '2026', '09', '31' ), 'wpu_' )
		);
	}
}
