<?php
/**
 * Integration test bootstrap (WordPress PHPUnit test suite).
 *
 * Requires WP_TESTS_DIR (default /tmp/wordpress-tests-lib), prepared by bin/install-wp-tests.sh.
 * WooCommerce itself is not needed: a minimal "product" post type stands in for it.
 */

$wpu_tests_dir = getenv( 'WP_TESTS_DIR' ) ?: '/tmp/wordpress-tests-lib';

if ( ! file_exists( $wpu_tests_dir . '/includes/functions.php' ) ) {
	fwrite( STDERR, "WordPress test library not found in {$wpu_tests_dir}. Run bin/install-wp-tests.sh first.\n" );
	exit( 1 );
}

if ( ! defined( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' ) ) {
	define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', dirname( __DIR__, 2 ) . '/vendor/yoast/phpunit-polyfills' );
}

require_once $wpu_tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	static function (): void {
		define( 'WPU_VERSION', '2.1.0' );
		define( 'WPU_FILE', dirname( __DIR__, 2 ) . '/woocommerce-product-unpublisher.php' );
		define( 'WPU_PATH', dirname( __DIR__, 2 ) . '/' );
		define( 'WPU_URL', 'http://example.org/wp-content/plugins/woocommerce-product-unpublisher/' );

		require_once WPU_PATH . 'src/autoload.php';
	}
);

tests_add_filter(
	'init',
	static function (): void {
		register_post_type(
			'product',
			array(
				'public'          => true,
				'show_ui'         => true,
				'capability_type' => 'post',
				'supports'        => array( 'title', 'editor', 'custom-fields' ),
			)
		);

		// WooCommerce grants this to administrators and shop managers.
		$role = get_role( 'administrator' );

		if ( $role ) {
			$role->add_cap( 'edit_products' );
		}
	},
	0
);

require $wpu_tests_dir . '/includes/bootstrap.php';

require_once __DIR__ . '/Support/Services.php';
require_once __DIR__ . '/Support/ProductTestCase.php';
