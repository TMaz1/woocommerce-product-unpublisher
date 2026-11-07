<?php
/**
 * Short-lived storage that carries bulk results across the redirect back to the product list.
 *
 * @package ProductUnpublisher
 */

namespace ProductUnpublisher\Admin;

use ProductUnpublisher\Config;

defined( 'ABSPATH' ) || exit;

final class BulkResultStore {

	public const QUERY_ARG = 'wpu_bulk_results';

	/**
	 * Store a result and return the token to put in the redirect URL.
	 */
	public function save( int $user_id, BulkResult $result ): string {
		$token = strtolower( wp_generate_password( 12, false, false ) );

		set_transient( $this->key( $user_id, $token ), $result->to_array(), Config::NOTICE_TTL );

		return $token;
	}

	/**
	 * Fetch and delete a stored result.
	 */
	public function pull( int $user_id, string $token ): ?BulkResult {
		if ( 1 !== preg_match( '/^[a-z0-9]{12}$/', $token ) ) {
			return null;
		}

		$key  = $this->key( $user_id, $token );
		$data = get_transient( $key );

		if ( false === $data ) {
			return null;
		}

		delete_transient( $key );

		return BulkResult::from_array( $data );
	}

	private function key( int $user_id, string $token ): string {
		return sprintf( 'wpu_bulk_%d_%s', $user_id, $token );
	}
}
