<?php

namespace ProductUnpublisher\Tests\Integration;

use ProductUnpublisher\Config;

abstract class ProductTestCase extends \WP_UnitTestCase {

	protected Services $services;

	public function set_up(): void {
		parent::set_up();

		$this->services = new Services();
	}

	public function tear_down(): void {
		$_POST    = array();
		$_GET     = array();
		$_REQUEST = array();

		parent::tear_down();
	}

	protected function create_product( string $status = 'publish', array $args = array() ): int {
		return (int) self::factory()->post->create(
			array_merge(
				array(
					'post_type'   => 'product',
					'post_status' => $status,
					'post_title'  => 'Product ' . wp_generate_password( 6, false ),
				),
				$args
			)
		);
	}

	protected function login_as( string $role ): int {
		$user_id = self::factory()->user->create( array( 'role' => $role ) );
		wp_set_current_user( $user_id );

		return $user_id;
	}

	/** A timestamp on a whole minute, $days days ahead. */
	protected function future_minute( int $days = 2 ): int {
		$timestamp = time() + $days * DAY_IN_SECONDS;

		return $timestamp - ( $timestamp % 60 );
	}

	/**
	 * Request fields for a timestamp, in the site timezone.
	 *
	 * @return array<string,string>
	 */
	protected function date_fields( int $timestamp, string $prefix ): array {
		$zone = wp_timezone();

		return array(
			$prefix . 'mm' => wp_date( 'm', $timestamp, $zone ),
			$prefix . 'jj' => wp_date( 'd', $timestamp, $zone ),
			$prefix . 'aa' => wp_date( 'Y', $timestamp, $zone ),
			$prefix . 'hh' => wp_date( 'H', $timestamp, $zone ),
			$prefix . 'mn' => wp_date( 'i', $timestamp, $zone ),
		);
	}

	/** Number of cron events registered for the product. */
	protected function event_count( int $product_id ): int {
		$count = 0;

		foreach ( (array) _get_cron_array() as $events ) {
			foreach ( (array) ( $events[ Config::CRON_HOOK ] ?? array() ) as $event ) {
				if ( array( $product_id ) === $event['args'] ) {
					++$count;
				}
			}
		}

		return $count;
	}

	protected function raw_meta( int $product_id ): string {
		return (string) get_post_meta( $product_id, Config::META_KEY, true );
	}
}
