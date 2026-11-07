<?php
/**
 * Thrown when submitted date parts cannot form a valid future schedule.
 *
 * @package ProductUnpublisher
 */

namespace ProductUnpublisher\Time;

defined( 'ABSPATH' ) || exit;

final class InvalidScheduleDateException extends \InvalidArgumentException {

	public const INCOMPLETE = 'incomplete';
	public const INVALID    = 'invalid';
	public const PAST       = 'past';

	private string $reason;

	public function __construct( string $reason ) {
		parent::__construct( 'Invalid schedule date: ' . $reason );
		$this->reason = $reason;
	}

	public function reason(): string {
		return $this->reason;
	}
}
