<?php
/**
 * WordPress implementation of the product gateway.
 *
 * @package ProductUnpublisher
 */

namespace ProductUnpublisher\Product;

use ProductUnpublisher\Config;

defined( 'ABSPATH' ) || exit;

final class WpProductGateway implements ProductGateway {

	public function exists( int $product_id ): bool {
		$post = get_post( $product_id );

		return $post instanceof \WP_Post && Config::POST_TYPE === $post->post_type;
	}

	public function status( int $product_id ): ?string {
		$status = get_post_status( $product_id );

		return is_string( $status ) ? $status : null;
	}

	public function publication_timestamp( int $product_id ): ?int {
		$timestamp = get_post_timestamp( $product_id, 'date' );

		return $timestamp ? (int) $timestamp : null;
	}

	public function set_draft( int $product_id ): ?string {
		$result = wp_update_post(
			array(
				'ID'          => $product_id,
				'post_status' => 'draft',
			),
			true
		);

		if ( is_wp_error( $result ) || ! $result ) {
			return 'update_error';
		}

		clean_post_cache( $product_id );

		return 'draft' === get_post_status( $product_id ) ? null : 'status_not_changed';
	}
}
