<?php
/**
 * Validates submitted date parts and converts them to a future timestamp.
 *
 * Pure PHP: no WordPress functions are called, so it can be unit tested in isolation.
 *
 * @package ProductUnpublisher
 */

namespace ProductUnpublisher\Time;

use ProductUnpublisher\Config;

defined( 'ABSPATH' ) || exit;

final class ScheduleDateParser {

	/** Request field suffix => part name. */
	private const FIELDS = array(
		'mm' => 'month',
		'jj' => 'day',
		'aa' => 'year',
		'hh' => 'hour',
		'mn' => 'minute',
	);

	public function __construct( private Clock $clock ) {}

	/**
	 * Parse the five date fields from request-style data.
	 *
	 * @param array<string,mixed> $source Already-unslashed request data.
	 * @param string              $prefix Field name prefix, e.g. "wpu_" or "wpu_bulk_".
	 *
	 * @throws InvalidScheduleDateException When a field is missing, malformed, or the date is not in the future.
	 */
	public function from_request( array $source, string $prefix ): int {
		$parts = array();

		foreach ( self::FIELDS as $suffix => $name ) {
			$key = $prefix . $suffix;

			if ( ! isset( $source[ $key ] ) || ! is_string( $source[ $key ] ) ) {
				throw new InvalidScheduleDateException( InvalidScheduleDateException::INCOMPLETE );
			}

			$raw = trim( $source[ $key ] );

			if ( 1 !== preg_match( '/^\d{1,4}$/', $raw ) ) {
				throw new InvalidScheduleDateException( InvalidScheduleDateException::INVALID );
			}

			$parts[ $name ] = (int) $raw;
		}

		return $this->from_parts( $parts );
	}

	/**
	 * Convert integer date parts to a timestamp strictly in the future.
	 *
	 * @param array<string,int> $parts Keys month, day, year, hour, minute (site timezone).
	 *
	 * @throws InvalidScheduleDateException When invalid or not in the future.
	 */
	public function from_parts( array $parts ): int {
		$month  = (int) ( $parts['month'] ?? 0 );
		$day    = (int) ( $parts['day'] ?? 0 );
		$year   = (int) ( $parts['year'] ?? 0 );
		$hour   = (int) ( $parts['hour'] ?? -1 );
		$minute = (int) ( $parts['minute'] ?? -1 );

		if (
			$year < Config::MIN_YEAR || $year > 9999 ||
			! checkdate( $month, $day, $year ) ||
			$hour < 0 || $hour > 23 ||
			$minute < 0 || $minute > 59
		) {
			throw new InvalidScheduleDateException( InvalidScheduleDateException::INVALID );
		}

		$value = sprintf( '%04d-%02d-%02d %02d:%02d:00', $year, $month, $day, $hour, $minute );
		$date  = \DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $value, $this->clock->timezone() );

		// A nonexistent local time (e.g. inside a DST gap) is shifted by PHP, so the round trip no longer matches.
		if (
			! $date instanceof \DateTimeImmutable ||
			LocalTimeCodec::has_errors() ||
			$date->format( 'Y-m-d H:i:s' ) !== $value
		) {
			throw new InvalidScheduleDateException( InvalidScheduleDateException::INVALID );
		}

		$timestamp = $date->getTimestamp();

		if ( $timestamp <= $this->clock->now() ) {
			throw new InvalidScheduleDateException( InvalidScheduleDateException::PAST );
		}

		return $timestamp;
	}
}
