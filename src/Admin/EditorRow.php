<?php
/**
 * Renders the unpublishing row in the classic product editor Publish box.
 *
 * @package ProductUnpublisher
 */

namespace ProductUnpublisher\Admin;

use ProductUnpublisher\Config;
use ProductUnpublisher\Schedule\ScheduleManager;
use ProductUnpublisher\Time\Clock;

defined( 'ABSPATH' ) || exit;

final class EditorRow {

	public const NONCE_ACTION = 'wpu_save_schedule';
	public const NONCE_FIELD  = 'wpu_nonce';

	public function __construct(
		private ScheduleManager $schedules,
		private DateParts $parts,
		private DateDisplay $display,
		private Clock $clock
	) {}

	public function register(): void {
		add_action( 'post_submitbox_misc_actions', array( $this, 'render' ), 4 );
	}

	/**
	 * @param mixed $post Current post.
	 */
	public function render( $post ): void {
		if (
			! $post instanceof \WP_Post ||
			Config::POST_TYPE !== $post->post_type ||
			! current_user_can( 'edit_post', $post->ID )
		) {
			return;
		}

		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD, false );

		$timestamp = $this->schedules->get_timestamp( $post->ID );
		$now       = $this->clock->now();

		$label = ( null !== $timestamp && $timestamp <= $now )
			? __( 'Unpublishing overdue:', 'woocommerce-product-unpublisher' )
			: __( 'Unpublish on:', 'woocommerce-product-unpublisher' );

		View::render(
			'editor-row',
			array(
				'timestamp' => $timestamp,
				'label'     => $label,
				'current'   => null !== $timestamp
					? $this->display->long( $timestamp )
					: __( 'Not scheduled', 'woocommerce-product-unpublisher' ),
				'parts'     => $this->parts->from_timestamp( $timestamp ?? $now ),
				'months'    => Months::names(),
			)
		);
	}
}
