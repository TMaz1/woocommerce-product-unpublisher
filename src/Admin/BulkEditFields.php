<?php
/**
 * Adds the unpublishing controls to the native Bulk Edit panel of the Products list.
 *
 * @package ProductUnpublisher
 */

namespace ProductUnpublisher\Admin;

use ProductUnpublisher\Config;
use ProductUnpublisher\Time\Clock;

defined( 'ABSPATH' ) || exit;

final class BulkEditFields {

	private bool $rendered = false;

	public function __construct(
		private DateParts $parts,
		private Clock $clock
	) {}

	public function register(): void {
		add_action( 'bulk_edit_custom_box', array( $this, 'render' ), 10, 2 );
	}

	/**
	 * WordPress calls this once per list column; render only once.
	 *
	 * @param string $column_name Column being rendered.
	 * @param string $post_type   Post type.
	 */
	public function render( $column_name, $post_type ): void {
		if (
			$this->rendered ||
			Config::POST_TYPE !== $post_type ||
			! in_array( $column_name, array( ListColumn::COLUMN, 'name' ), true ) ||
			! current_user_can( 'edit_products' )
		) {
			return;
		}

		$this->rendered = true;

		// Default to this time tomorrow so the pre-filled date is already valid.
		$default = $this->clock->now() + DAY_IN_SECONDS;

		// The nonce travels with the (GET) bulk form; no referer field, core already adds one.
		wp_nonce_field( BulkEditHandler::NONCE_ACTION, BulkEditHandler::NONCE_FIELD, false );

		View::render(
			'bulk-edit-fields',
			array(
				'parts'  => $this->parts->from_timestamp( $default ),
				'months' => Months::names(),
			)
		);
	}
}
