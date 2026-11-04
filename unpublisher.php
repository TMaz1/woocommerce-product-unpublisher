<?php
/**
 * Plugin Name: WooCommerce Product Unpublisher
 * Description: Schedule published WooCommerce products to automatically return to draft status.
 * Version: 2.0.0
 * Requires at least: 6.5
 * Requires PHP: 8.2
 * Requires Plugins: woocommerce
 * Author: WooCommerce Product Unpublisher
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: woocommerce-product-unpublisher
 */

defined('ABSPATH') || exit;

define('WPU_VERSION', '2.0.0');
define('WPU_FILE', __FILE__);
define('WPU_PATH', plugin_dir_path(__FILE__));
define('WPU_URL', plugin_dir_url(__FILE__));

define('WPU_META_KEY', '_wps_unpublish_time');
define('WPU_CRON_HOOK', 'wpu_unpublish_product');
define('WPU_ATTEMPTS_META', '_wpu_unpublish_attempts');
define('WPU_MAX_ATTEMPTS', 5);

require_once WPU_PATH . 'includes/class-wpu-scheduler.php';
require_once WPU_PATH . 'includes/class-wpu-admin.php';

final class WPU_Product_Unpublisher
{

	private WPU_Scheduler $scheduler;
	private WPU_Admin $admin;

	public function __construct()
	{

		load_plugin_textdomain(
			'woocommerce-product-unpublisher',
			false,
			dirname(plugin_basename(WPU_FILE)) . '/languages'
		);

		$this->scheduler = new WPU_Scheduler();
		$this->admin = new WPU_Admin($this->scheduler);
	}

	public static function deactivate(): void
	{

		wp_unschedule_hook(WPU_CRON_HOOK);
	}
}

register_deactivation_hook(
	WPU_FILE,
	array('WPU_Product_Unpublisher', 'deactivate')
);

add_action(
	'plugins_loaded',
	static function () {

		if (! class_exists('WooCommerce')) {
			return;
		}

		new WPU_Product_Unpublisher();
	},
	20
);
