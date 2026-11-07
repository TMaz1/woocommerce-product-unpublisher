<?php
/**
 * Per-user, per-product admin notices that survive the redirect after saving.
 *
 * @package ProductUnpublisher
 */

namespace ProductUnpublisher\Admin;

use ProductUnpublisher\Config;

defined( 'ABSPATH' ) || exit;

final class ProductNoticeStore {

	private const TYPES = array( 'success', 'error', 'warning', 'info' );

	public function add( int $post_id, string $message, string $type = 'error' ): void {
		if ( ! in_array( $type, self::TYPES, true ) ) {
			$type = 'error';
		}

		set_transient(
			$this->key( $post_id ),
			array(
				'message' => $message,
				'type'    => $type,
			),
			Config::NOTICE_TTL
		);
	}

	/**
	 * Fetch and delete the pending notice.
	 *
	 * @return array{message:string,type:string}|null
	 */
	public function pull( int $post_id ): ?array {
		$key    = $this->key( $post_id );
		$notice = get_transient( $key );

		if ( ! is_array( $notice ) ) {
			return null;
		}

		delete_transient( $key );

		$type = isset( $notice['type'] ) && in_array( $notice['type'], self::TYPES, true ) ? $notice['type'] : 'error';

		return array(
			'message' => isset( $notice['message'] ) && is_string( $notice['message'] ) ? $notice['message'] : '',
			'type'    => $type,
		);
	}

	private function key( int $post_id ): string {
		return sprintf( 'wpu_notice_%d_%d', get_current_user_id(), $post_id );
	}
}
