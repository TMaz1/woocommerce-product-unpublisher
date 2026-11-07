<?php
/**
 * Plugin Name: WooCommerce Product Unpublisher
 * Description: Schedule published WooCommerce products to automatically return to draft status, individually or in bulk.
 * Version: 2.1.0
 * Requires at least: 6.5
 * Requires PHP: 8.2
 * Requires Plugins: woocommerce
 * Author: WooCommerce Product Unpublisher
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: woocommerce-product-unpublisher
 *
 * @package ProductUnpublisher
 */

defined( 'ABSPATH' ) || exit;

define( 'WPU_VERSION', '2.1.0' );
define( 'WPU_FILE', __FILE__ );
define( 'WPU_PATH', plugin_dir_path( __FILE__ ) );
define( 'WPU_URL', plugin_dir_url( __FILE__ ) );

require_once WPU_PATH . 'src/autoload.php';

// This plugin only touches product posts and post meta, so it is compatible with High-Performance Order Storage.
add_action(
	'before_woocommerce_init',
	static function (): void {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', WPU_FILE, true );
		}
	}
);

register_activation_hook( WPU_FILE, array( \ProductUnpublisher\Plugin::class, 'activate' ) );
register_deactivation_hook( WPU_FILE, array( \ProductUnpublisher\Plugin::class, 'deactivate' ) );

add_action( 'plugins_loaded', array( \ProductUnpublisher\Plugin::class, 'boot_if_ready' ), 20 );
