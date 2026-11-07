<?php
/**
 * Parsed, nonce-verified bulk edit instruction.
 *
 * @package ProductUnpublisher
 */

namespace ProductUnpublisher\Admin;

defined( 'ABSPATH' ) || exit;

final class BulkRequest {

	/**
	 * @param string      $action     One of the BulkAction constants.
	 * @param int|null    $timestamp  Validated future timestamp for the schedule action.
	 * @param string|null $date_error Reason code when the schedule date was rejected.
	 */
	public function __construct(
		public readonly string $action,
		public readonly ?int $timestamp = null,
		public readonly ?string $date_error = null
	) {}
}
