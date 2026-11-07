<?php
/**
 * Splits a timestamp into the zero-padded parts used by the form fields.
 *
 * @package ProductUnpublisher
 */

namespace ProductUnpublisher\Admin;

use ProductUnpublisher\Time\Clock;

defined( 'ABSPATH' ) || exit;

final class DateParts {

	public function __construct( private Clock $clock ) {}

	/**
	 * @return array{mm:string,jj:string,aa:string,hh:string,mn:string}
	 */
	public function from_timestamp( int $timestamp ): array {
		$date = ( new \DateTimeImmutable( '@' . $timestamp ) )->setTimezone( $this->clock->timezone() );

		return array(
			'mm' => $date->format( 'm' ),
			'jj' => $date->format( 'd' ),
			'aa' => $date->format( 'Y' ),
			'hh' => $date->format( 'H' ),
			'mn' => $date->format( 'i' ),
		);
	}
}
