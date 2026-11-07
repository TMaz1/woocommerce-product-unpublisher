<?php
/**
 * WordPress-backed clock.
 *
 * @package ProductUnpublisher
 */

namespace ProductUnpublisher\Time;

defined( 'ABSPATH' ) || exit;

final class SiteClock implements Clock {

	public function now(): int {
		return time();
	}

	public function timezone(): \DateTimeZone {
		return wp_timezone();
	}
}
