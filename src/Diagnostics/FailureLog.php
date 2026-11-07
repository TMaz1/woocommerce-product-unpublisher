<?php
/**
 * Port for the private failure log.
 *
 * @package ProductUnpublisher
 */

namespace ProductUnpublisher\Diagnostics;

defined( 'ABSPATH' ) || exit;

interface FailureLog {

	/**
	 * Record a failure.
	 *
	 * @param int    $product_id Product ID.
	 * @param string $reason     Controlled reason code.
	 * @param int    $attempt    Attempt number, when relevant.
	 */
	public function record( int $product_id, string $reason, int $attempt = 0 ): void;

	/**
	 * Logged entries, oldest first.
	 *
	 * @return array<int,array{product_id:int,reason:string,time:int,attempt:int}>
	 */
	public function entries(): array;

	/**
	 * Entries that need human attention, newer than $since.
	 *
	 * @return array<int,array{product_id:int,reason:string,time:int,attempt:int}>
	 */
	public function actionable( int $since ): array;

	public function clear(): void;
}
