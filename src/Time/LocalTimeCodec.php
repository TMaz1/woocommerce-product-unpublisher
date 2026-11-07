<?php
/**
 * Converts between timestamps and the stored site-local date string.
 *
 * @package ProductUnpublisher
 */

namespace ProductUnpublisher\Time;

defined( 'ABSPATH' ) || exit;

final class LocalTimeCodec {

	private const FORMAT = 'Y-m-d H:i:s';

	public function __construct( private Clock $clock ) {}

	/**
	 * Parse a stored value. Returns null for anything that is not an exact, valid local date-time.
	 *
	 * @param mixed $value Stored meta value.
	 */
	public function parse( mixed $value ): ?int {
		if ( ! is_string( $value ) || '' === $value ) {
			return null;
		}

		$date = \DateTimeImmutable::createFromFormat( '!' . self::FORMAT, $value, $this->clock->timezone() );

		if ( ! $date instanceof \DateTimeImmutable || self::has_errors() ) {
			return null;
		}

		if ( $date->format( self::FORMAT ) !== $value ) {
			return null;
		}

		return $date->getTimestamp();
	}

	/**
	 * Format a timestamp for storage.
	 */
	public function format( int $timestamp ): string {
		return ( new \DateTimeImmutable( '@' . $timestamp ) )
			->setTimezone( $this->clock->timezone() )
			->format( self::FORMAT );
	}

	/**
	 * Whether the last date parse produced warnings or errors.
	 */
	public static function has_errors(): bool {
		$errors = \DateTimeImmutable::getLastErrors();

		return is_array( $errors ) && ( $errors['warning_count'] > 0 || $errors['error_count'] > 0 );
	}
}
