<?php
/**
 * Failure log stored in a non-autoloaded option.
 *
 * @package ProductUnpublisher
 */

namespace ProductUnpublisher\Diagnostics;

use ProductUnpublisher\Config;

defined( 'ABSPATH' ) || exit;

final class OptionFailureLog implements FailureLog {

	public function record( int $product_id, string $reason, int $attempt = 0 ): void {
		$reason  = sanitize_key( $reason );
		$entries = $this->entries();

		$entries[] = array(
			'product_id' => $product_id,
			'reason'     => $reason,
			'time'       => time(),
			'attempt'    => $attempt,
		);

		update_option(
			Config::FAILURE_LOG_OPTION,
			array_slice( $entries, -Config::FAILURE_LOG_LIMIT ),
			false
		);

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Operational diagnostics for site owners.
		error_log( sprintf( 'WPU: %s (product %d, attempt %d).', $reason, $product_id, $attempt ) );
	}

	public function entries(): array {
		$stored = get_option( Config::FAILURE_LOG_OPTION, array() );

		if ( ! is_array( $stored ) ) {
			return array();
		}

		$entries = array();

		foreach ( $stored as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}

			$entries[] = array(
				'product_id' => (int) ( $entry['product_id'] ?? 0 ),
				'reason'     => (string) ( $entry['reason'] ?? '' ),
				'time'       => (int) ( $entry['time'] ?? 0 ),
				'attempt'    => (int) ( $entry['attempt'] ?? 0 ),
			);
		}

		return $entries;
	}

	public function actionable( int $since ): array {
		return array_values(
			array_filter(
				$this->entries(),
				static function ( array $entry ) use ( $since ): bool {
					return $entry['time'] >= $since
						&& in_array( $entry['reason'], Config::ACTIONABLE_FAILURES, true );
				}
			)
		);
	}

	public function clear(): void {
		delete_option( Config::FAILURE_LOG_OPTION );
	}
}
