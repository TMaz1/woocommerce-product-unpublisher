<?php
/**
 * WP-Cron implementation of the cron gateway.
 *
 * @package ProductUnpublisher
 */

namespace ProductUnpublisher\Cron;

use ProductUnpublisher\Config;

defined( 'ABSPATH' ) || exit;

final class WpCronGateway implements CronGateway {

	/**
	 * @return int|false
	 */
	public function next_run( int $product_id ) {
		$next = wp_next_scheduled( Config::CRON_HOOK, array( $product_id ) );

		return false === $next ? false : (int) $next;
	}

	public function schedule( int $product_id, int $timestamp ): bool {
		$result = wp_schedule_single_event( $timestamp, Config::CRON_HOOK, array( $product_id ), true );

		if ( is_wp_error( $result ) || false === $result ) {
			return false;
		}

		return $this->next_run( $product_id ) === $timestamp;
	}

	public function clear( int $product_id ): bool {
		$result = wp_clear_scheduled_hook( Config::CRON_HOOK, array( $product_id ), true );

		if ( is_wp_error( $result ) || false === $result ) {
			return false;
		}

		return false === $this->next_run( $product_id );
	}
}
