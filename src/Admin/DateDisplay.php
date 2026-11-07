<?php
/**
 * Formats schedule times using the site's date and time settings.
 *
 * @package ProductUnpublisher
 */

namespace ProductUnpublisher\Admin;

use ProductUnpublisher\Time\Clock;

defined( 'ABSPATH' ) || exit;

final class DateDisplay {

	public function __construct( private Clock $clock ) {}

	/**
	 * Format like "March 3, 2027 3:30 pm".
	 */
	public function short( int $timestamp ): string {
		return wp_date(
			get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
			$timestamp,
			$this->clock->timezone()
		);
	}

	/**
	 * Format like "March 3, 2027 at 3:30 pm" (matches the core Publish box).
	 */
	public function long( int $timestamp ): string {
		return wp_date(
			/* translators: 1: date format, 2: time format. The "at" is escaped for PHP date(). */
			sprintf( '%1$s \a\t %2$s', get_option( 'date_format' ), get_option( 'time_format' ) ),
			$timestamp,
			$this->clock->timezone()
		);
	}
}
