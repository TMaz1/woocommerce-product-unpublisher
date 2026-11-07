<?php
/**
 * Removes all plugin data when the plugin is deleted from the Plugins screen.
 *
 * @package ProductUnpublisher
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Delete plugin data for the current site.
 */
function wpu_uninstall_site(): void {
	global $wpdb;

	wp_unschedule_hook( 'wpu_unpublish_product' );

	delete_post_meta_by_key( '_wps_unpublish_time' );
	delete_post_meta_by_key( '_wpu_unpublish_attempts' );

	delete_option( 'wpu_failure_log' );
	delete_option( 'wpu_db_version' );

	// Short-lived notices and bulk results.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
			$wpdb->esc_like( '_transient_wpu_' ) . '%',
			$wpdb->esc_like( '_transient_timeout_wpu_' ) . '%'
		)
	);
}

if ( is_multisite() ) {
	foreach ( get_sites( array( 'fields' => 'ids' ) ) as $wpu_site_id ) {
		switch_to_blog( (int) $wpu_site_id );
		wpu_uninstall_site();
		restore_current_blog();
	}
} else {
	wpu_uninstall_site();
}
