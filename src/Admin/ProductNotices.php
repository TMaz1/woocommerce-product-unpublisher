<?php
/**
 * Prints the saved notice on the product editor screen.
 *
 * @package ProductUnpublisher
 */

namespace ProductUnpublisher\Admin;

use ProductUnpublisher\Config;

defined( 'ABSPATH' ) || exit;

final class ProductNotices {

	public function __construct( private ProductNoticeStore $store ) {}

	public function register(): void {
		add_action( 'admin_notices', array( $this, 'render' ) );
	}

	public function render(): void {
		$screen = get_current_screen();

		if ( ! $screen instanceof \WP_Screen || Config::POST_TYPE !== $screen->post_type || 'post' !== $screen->base ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display of a per-user transient.
		$post_id = isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0;

		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$notice = $this->store->pull( $post_id );

		if ( null === $notice || '' === $notice['message'] ) {
			return;
		}

		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $notice['type'] ),
			wp_kses(
				$notice['message'],
				array(
					'a' => array(
						'href'   => array(),
						'target' => array(),
						'rel'    => array(),
					),
				)
			)
		);
	}
}
