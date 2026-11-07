<?php
/**
 * Clock abstraction.
 *
 * @package ProductUnpublisher
 */

namespace ProductUnpublisher\Time;

defined( 'ABSPATH' ) || exit;

interface Clock {

	/**
	 * Current Unix timestamp.
	 */
	public function now(): int;

	/**
	 * Timezone used to interpret stored and submitted local times.
	 */
	public function timezone(): \DateTimeZone;
}
