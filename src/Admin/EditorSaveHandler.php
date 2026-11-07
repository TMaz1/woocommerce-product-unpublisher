<?php
/**
 * Saves the schedule submitted from the classic product editor.
 *
 * @package ProductUnpublisher
 */

namespace ProductUnpublisher\Admin;

use ProductUnpublisher\Config;
use ProductUnpublisher\Product\ProductGateway;
use ProductUnpublisher\Schedule\ScheduleManager;
use ProductUnpublisher\Time\InvalidScheduleDateException;
use ProductUnpublisher\Time\ScheduleDateParser;

defined( 'ABSPATH' ) || exit;

final class EditorSaveHandler {

	public function __construct(
		private ScheduleManager $schedules,
		private ProductGateway $products,
		private ScheduleDateParser $parser,
		private ProductNoticeStore $notices,
		private DateDisplay $display
	) {}

	public function register(): void {
		add_action( 'save_post_product', array( $this, 'handle' ), 30, 3 );
	}

	/**
	 * @param int|string $post_id Post ID.
	 * @param mixed      $post    Post object.
	 * @param mixed      $update  Whether this is an update.
	 */
	public function handle( $post_id, $post = null, $update = false ): void {
		$post_id = (int) $post_id;

		if (
			! $post instanceof \WP_Post ||
			Config::POST_TYPE !== $post->post_type ||
			( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ||
			wp_is_post_revision( $post_id ) ||
			wp_is_post_autosave( $post_id ) ||
			! current_user_can( 'edit_post', $post_id )
		) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified immediately below.
		if ( ! isset( $_POST[ EditorRow::NONCE_FIELD ] ) || ! is_string( $_POST[ EditorRow::NONCE_FIELD ] ) ) {
			return;
		}

		$request = wp_unslash( $_POST );
		// phpcs:enable

		if ( ! wp_verify_nonce( sanitize_text_field( $request[ EditorRow::NONCE_FIELD ] ), EditorRow::NONCE_ACTION ) ) {
			return;
		}

		$action = isset( $request['wpu_action'] ) && is_string( $request['wpu_action'] )
			? sanitize_key( $request['wpu_action'] )
			: '';

		if ( 'remove' === $action ) {
			$this->remove( $post_id );
			return;
		}

		// Any other value means: update the product without touching its schedule.
		if ( 'save' !== $action ) {
			return;
		}

		$this->save( $post_id, $post, $request );
	}

	private function remove( int $post_id ): void {
		if ( $this->schedules->remove( $post_id ) ) {
			$this->notices->add(
				$post_id,
				__( 'The product unpublishing schedule has been removed.', 'woocommerce-product-unpublisher' ),
				'success'
			);
			return;
		}

		$this->notices->add(
			$post_id,
			__( 'Could not completely remove the unpublishing schedule. Please try again.', 'woocommerce-product-unpublisher' ),
			'error'
		);
	}

	/**
	 * @param array<string,mixed> $request Unslashed request data.
	 */
	private function save( int $post_id, \WP_Post $post, array $request ): void {
		$status = $this->products->status( $post_id );

		if ( ! in_array( $status, Config::SCHEDULABLE_STATUSES, true ) ) {
			$this->notices->add(
				$post_id,
				__( 'Only published or scheduled products can have an unpublishing schedule.', 'woocommerce-product-unpublisher' ),
				'error'
			);
			return;
		}

		try {
			$timestamp = $this->parser->from_request( $request, 'wpu_' );
		} catch ( InvalidScheduleDateException $e ) {
			$this->notices->add( $post_id, $this->date_error_message( $e->reason() ), 'error' );
			return;
		}

		if ( 'future' === $status ) {
			$publish_at = $this->products->publication_timestamp( $post_id );

			if ( null !== $publish_at && $timestamp <= $publish_at ) {
				$this->notices->add(
					$post_id,
					__( 'Unpublishing must be later than scheduled publication. The previous schedule was retained.', 'woocommerce-product-unpublisher' ),
					'error'
				);
				return;
			}
		}

		if ( ! $this->schedules->replace( $post_id, $timestamp ) ) {
			$this->notices->add(
				$post_id,
				__( 'Could not schedule unpublishing. Check WordPress cron configuration. Your previous schedule was kept where possible.', 'woocommerce-product-unpublisher' ),
				'error'
			);
			return;
		}

		$preview_url = get_preview_post_link( $post_id );
		$link        = $preview_url
			? sprintf(
				'<a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>',
				esc_url( $preview_url ),
				esc_html__( 'Preview product', 'woocommerce-product-unpublisher' )
			)
			: '';

		$this->notices->add(
			$post_id,
			trim(
				sprintf(
					/* translators: 1: date and time, 2: preview link. */
					__( 'Product scheduled unpublishing for: %1$s. %2$s', 'woocommerce-product-unpublisher' ),
					esc_html( $this->display->short( $timestamp ) ),
					$link
				)
			),
			'success'
		);
	}

	private function date_error_message( string $reason ): string {
		if ( InvalidScheduleDateException::INCOMPLETE === $reason ) {
			return __( 'The unpublishing date is incomplete. The previous schedule was retained.', 'woocommerce-product-unpublisher' );
		}

		return __( 'Choose a valid unpublishing date and time in the future. The previous schedule was retained.', 'woocommerce-product-unpublisher' );
	}
}
