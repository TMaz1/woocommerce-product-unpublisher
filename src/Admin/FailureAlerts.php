<?php
/**
 * Warns on the Products list about unpublish operations that need attention.
 *
 * @package ProductUnpublisher
 */

namespace ProductUnpublisher\Admin;

use ProductUnpublisher\Config;
use ProductUnpublisher\Diagnostics\FailureLog;

defined( 'ABSPATH' ) || exit;

final class FailureAlerts {

	private const DISMISS_ARG   = 'wpu_dismiss_alerts';
	private const LOOKBACK_DAYS = 7;

	public function __construct( private FailureLog $failures ) {}

	public function register(): void {
		add_action( 'admin_init', array( $this, 'maybe_dismiss' ) );
		add_action( 'admin_notices', array( $this, 'render' ) );
	}

	public function maybe_dismiss(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified on the next line.
		if ( ! isset( $_GET[ self::DISMISS_ARG ], $_GET['_wpnonce'] ) || ! is_string( $_GET['_wpnonce'] ) ) {
			return;
		}

		if (
			! current_user_can( 'edit_products' ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), self::DISMISS_ARG )
		) {
			return;
		}

		$this->failures->clear();

		wp_safe_redirect( $this->list_url() );
		exit;
	}

	public function render(): void {
		$screen = get_current_screen();

		if ( ! $screen instanceof \WP_Screen || Config::POST_TYPE !== $screen->post_type || 'edit' !== $screen->base || ! current_user_can( 'edit_products' ) ) {
			return;
		}

		$entries = $this->failures->actionable( time() - self::LOOKBACK_DAYS * DAY_IN_SECONDS );

		if ( ! $entries ) {
			return;
		}

		$ids = array_values( array_unique( array_map( static fn( array $entry ): int => $entry['product_id'], $entries ) ) );

		printf(
			'<div class="notice notice-warning wpu-alerts"><p>%1$s</p><p>%2$s <a href="%3$s">%4$s</a></p></div>',
			esc_html(
				sprintf(
					/* translators: %d: number of products. */
					_n(
						'Product Unpublisher: %d product needs attention. It was either moved to Draft but its schedule could not be fully cleaned up, or its automatic retries were used up.',
						'Product Unpublisher: %d products need attention. They were either moved to Draft but their schedules could not be fully cleaned up, or their automatic retries were used up.',
						count( $ids ),
						'woocommerce-product-unpublisher'
					),
					count( $ids )
				)
			),
			esc_html(
				sprintf(
					/* translators: %s: comma separated product IDs. */
					__( 'Product IDs: %s.', 'woocommerce-product-unpublisher' ),
					implode( ', ', array_slice( $ids, 0, 20 ) )
				)
			),
			esc_url( wp_nonce_url( add_query_arg( self::DISMISS_ARG, '1', $this->list_url() ), self::DISMISS_ARG ) ),
			esc_html__( 'Dismiss', 'woocommerce-product-unpublisher' )
		);
	}

	private function list_url(): string {
		return admin_url( 'edit.php?post_type=' . Config::POST_TYPE );
	}
}
