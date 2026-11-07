<?php
/**
 * Post meta implementation of the schedule store.
 *
 * @package ProductUnpublisher
 */

namespace ProductUnpublisher\Storage;

use ProductUnpublisher\Config;
use ProductUnpublisher\Time\LocalTimeCodec;

defined( 'ABSPATH' ) || exit;

final class PostMetaScheduleStore implements ScheduleStore {

	public function __construct( private LocalTimeCodec $codec ) {}

	public function get_timestamp( int $product_id ): ?int {
		return $this->codec->parse( get_post_meta( $product_id, Config::META_KEY, true ) );
	}

	public function get_raw( int $product_id ): ?string {
		if ( ! metadata_exists( 'post', $product_id, Config::META_KEY ) ) {
			return null;
		}

		$value = get_post_meta( $product_id, Config::META_KEY, true );

		return is_scalar( $value ) ? (string) $value : '';
	}

	public function set_timestamp( int $product_id, int $timestamp ): void {
		update_post_meta( $product_id, Config::META_KEY, $this->codec->format( $timestamp ) );
	}

	public function restore_raw( int $product_id, ?string $raw ): void {
		if ( null === $raw ) {
			delete_post_meta( $product_id, Config::META_KEY );
			return;
		}

		update_post_meta( $product_id, Config::META_KEY, $raw );
	}

	public function delete( int $product_id ): void {
		delete_post_meta( $product_id, Config::META_KEY );
	}

	public function get_attempts( int $product_id ): int {
		return absint( get_post_meta( $product_id, Config::ATTEMPTS_META, true ) );
	}

	public function increment_attempts( int $product_id ): int {
		$attempts = $this->get_attempts( $product_id ) + 1;

		update_post_meta( $product_id, Config::ATTEMPTS_META, $attempts );

		return $attempts;
	}

	public function clear_attempts( int $product_id ): void {
		delete_post_meta( $product_id, Config::ATTEMPTS_META );
	}

	public function has_data( int $product_id ): bool {
		return metadata_exists( 'post', $product_id, Config::META_KEY )
			|| metadata_exists( 'post', $product_id, Config::ATTEMPTS_META );
	}
}
