<?php
/**
 * Exponential back-off for failed unpublish attempts.
 *
 * @package ProductUnpublisher
 */

namespace ProductUnpublisher\Schedule;

use ProductUnpublisher\Config;

defined( 'ABSPATH' ) || exit;

final class RetryPolicy {

	public function __construct(
		private int $max_attempts = Config::MAX_ATTEMPTS,
		private int $base_delay = 30,
		private int $max_delay = 900
	) {}

	/**
	 * Whether automatic retries are used up.
	 */
	public function exhausted( int $attempts ): bool {
		return $attempts >= $this->max_attempts;
	}

	/**
	 * Seconds to wait before the next attempt, given the number of attempts so far (>= 1).
	 */
	public function delay( int $attempts ): int {
		$exponent = max( 0, min( $attempts - 1, 20 ) );

		return min( $this->max_delay, $this->base_delay * ( 2 ** $exponent ) );
	}
}
