<?php
/**
 * Reports the outcome of a bulk edit on the Products list.
 *
 * @package ProductUnpublisher
 */

namespace ProductUnpublisher\Admin;

use ProductUnpublisher\Config;

defined( 'ABSPATH' ) || exit;

final class BulkNotice {

	public function __construct( private BulkResultStore $store ) {}

	public function register(): void {
		add_action( 'admin_notices', array( $this, 'render' ) );
	}

	public function render(): void {
		$screen = get_current_screen();

		if ( ! $screen instanceof \WP_Screen || Config::POST_TYPE !== $screen->post_type || 'edit' !== $screen->base ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Opaque token for a per-user transient; read-only.
		$token = isset( $_GET[ BulkResultStore::QUERY_ARG ] ) ? sanitize_key( wp_unslash( $_GET[ BulkResultStore::QUERY_ARG ] ) ) : '';

		if ( '' === $token || ! current_user_can( 'edit_products' ) ) {
			return;
		}

		$result = $this->store->pull( get_current_user_id(), $token );

		if ( null === $result || $result->is_empty() ) {
			return;
		}

		echo $this->build_html( $result ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in build_html().
	}

	/**
	 * Build the notice markup (escaped).
	 */
	public function build_html( BulkResult $result ): string {
		$lines = $this->summary_lines( $result );
		$html  = '';

		foreach ( $lines as $line ) {
			$html .= '<p>' . esc_html( $line ) . '</p>';
		}

		$problems = $this->problem_items( $result );

		if ( $problems ) {
			$html .= '<ul class="wpu-bulk-problems">';

			foreach ( $problems as $item ) {
				$html .= '<li>' . esc_html( $item ) . '</li>';
			}

			$html .= '</ul>';
		}

		return sprintf(
			'<div class="notice notice-%1$s is-dismissible wpu-bulk-notice">%2$s</div>',
			esc_attr( $this->notice_type( $result ) ),
			$html
		);
	}

	public function notice_type( BulkResult $result ): string {
		$failed  = $result->count( BulkEntry::FAILED );
		$partial = $result->count( BulkEntry::PARTIAL );
		$success = $result->count( BulkEntry::SUCCESS );

		if ( $failed && ! $success && ! $partial ) {
			return 'error';
		}

		if ( $failed || $partial ) {
			return 'warning';
		}

		return $success ? 'success' : 'info';
	}

	/**
	 * One sentence per non-zero outcome.
	 *
	 * @return string[]
	 */
	public function summary_lines( BulkResult $result ): array {
		$lines = array();

		$success = $result->count( BulkEntry::SUCCESS );
		$partial = $result->count( BulkEntry::PARTIAL );
		$failed  = $result->count( BulkEntry::FAILED );
		$skipped = $result->count( BulkEntry::SKIPPED );

		if ( $success ) {
			$lines[] = $this->success_line( $result->action(), $success );
		}

		if ( $partial ) {
			$lines[] = sprintf(
				/* translators: %d: number of products. */
				_n(
					'%d product was moved to Draft, but its unpublishing schedule data could not be fully removed. Use "Remove scheduled unpublishing" on it to try again.',
					'%d products were moved to Draft, but their unpublishing schedule data could not be fully removed. Use "Remove scheduled unpublishing" on them to try again.',
					$partial,
					'woocommerce-product-unpublisher'
				),
				$partial
			);
		}

		if ( $failed ) {
			$lines[] = sprintf(
				/* translators: %d: number of products. */
				_n( '%d product could not be processed.', '%d products could not be processed.', $failed, 'woocommerce-product-unpublisher' ),
				$failed
			);
		}

		if ( $skipped ) {
			$lines[] = sprintf(
				/* translators: %d: number of products. */
				_n( '%d product was skipped and left unchanged.', '%d products were skipped and left unchanged.', $skipped, 'woocommerce-product-unpublisher' ),
				$skipped
			);
		}

		return $lines;
	}

	private function success_line( string $action, int $count ): string {
		switch ( $action ) {
			case BulkAction::SCHEDULE:
				/* translators: %d: number of products. */
				return sprintf( _n( 'Unpublishing scheduled for %d product.', 'Unpublishing scheduled for %d products.', $count, 'woocommerce-product-unpublisher' ), $count );

			case BulkAction::REMOVE:
				/* translators: %d: number of products. */
				return sprintf( _n( 'Scheduled unpublishing removed from %d product.', 'Scheduled unpublishing removed from %d products.', $count, 'woocommerce-product-unpublisher' ), $count );

			case BulkAction::UNPUBLISH_NOW:
				/* translators: %d: number of products. */
				return sprintf( _n( '%d product unpublished and moved to Draft.', '%d products unpublished and moved to Draft.', $count, 'woocommerce-product-unpublisher' ), $count );
		}

		/* translators: %d: number of products. */
		return sprintf( _n( '%d product processed successfully.', '%d products processed successfully.', $count, 'woocommerce-product-unpublisher' ), $count );
	}

	/**
	 * "Product name: reason" for each detailed entry.
	 *
	 * @return string[]
	 */
	private function problem_items( BulkResult $result ): array {
		$items = array();

		foreach ( $result->details() as $entry ) {
			$title = get_the_title( $entry->product_id );

			if ( '' === $title ) {
				/* translators: %d: product ID. */
				$title = sprintf( __( 'Product #%d', 'woocommerce-product-unpublisher' ), $entry->product_id );
			}

			$items[] = sprintf(
				/* translators: 1: product name, 2: reason. */
				__( '%1$s: %2$s', 'woocommerce-product-unpublisher' ),
				$title,
				$this->reason_label( $entry->reason )
			);
		}

		return $items;
	}

	public function reason_label( string $reason ): string {
		$labels = array(
			'not_published'       => __( 'not published', 'woocommerce-product-unpublisher' ),
			'no_schedule'         => __( 'no schedule to remove', 'woocommerce-product-unpublisher' ),
			'invalid_date'        => __( 'the date was invalid', 'woocommerce-product-unpublisher' ),
			'past_date'           => __( 'the date was not in the future', 'woocommerce-product-unpublisher' ),
			'schedule_failed'     => __( 'the schedule could not be registered; the previous schedule was kept where possible', 'woocommerce-product-unpublisher' ),
			'remove_failed'       => __( 'the schedule could not be completely removed', 'woocommerce-product-unpublisher' ),
			'cleanup_failed'      => __( 'moved to Draft, but schedule cleanup failed', 'woocommerce-product-unpublisher' ),
			'no_permission'       => __( 'you are not allowed to edit this product', 'woocommerce-product-unpublisher' ),
			'not_found'           => __( 'product not found', 'woocommerce-product-unpublisher' ),
			'update_error'        => __( 'WordPress could not update the product', 'woocommerce-product-unpublisher' ),
			'status_not_changed'  => __( 'the status did not change', 'woocommerce-product-unpublisher' ),
		);

		return $labels[ $reason ] ?? __( 'unexpected error', 'woocommerce-product-unpublisher' );
	}
}
