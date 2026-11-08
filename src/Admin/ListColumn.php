<?php

/**
 * "Unpublishes" column on the Products list.
 *
 * @package ProductUnpublisher
 */

namespace ProductUnpublisher\Admin;

use ProductUnpublisher\Config;
use ProductUnpublisher\Product\ProductGateway;
use ProductUnpublisher\Schedule\RetryPolicy;
use ProductUnpublisher\Schedule\ScheduleManager;
use ProductUnpublisher\Time\Clock;

defined('ABSPATH') || exit;

final class ListColumn
{

	public const COLUMN = 'wpu_unpublish';

	public function __construct(
		private ScheduleManager $schedules,
		private ProductGateway $products,
		private RetryPolicy $retry,
		private DateDisplay $display,
		private Clock $clock
	) {}

	public function register(): void
	{
		add_filter('manage_edit-product_columns', array($this, 'add_column'), 20);
		add_action('manage_product_posts_custom_column', array($this, 'render'), 10, 2);

		add_filter('manage_edit-product_sortable_columns', array($this, 'sortable_column'));
		add_action('pre_get_posts', array($this, 'order_products'));
	}

	/**
	 * Insert the column before the Date column (or at the end).
	 *
	 * @param mixed $columns Existing columns.
	 * @return mixed
	 */
	public function add_column($columns)
	{
		if (! is_array($columns)) {
			return $columns;
		}

		$label   = __('Unpublish', 'woocommerce-product-unpublisher');
		$result  = array();
		$and_add = true;

		foreach ($columns as $key => $title) {
			if ('date' === $key && $and_add) {
				$result[self::COLUMN] = $label;
				$and_add                = false;
			}

			$result[$key] = $title;
		}

		if ($and_add) {
			$result[self::COLUMN] = $label;
		}

		return $result;
	}

	/**
	 * Register the Unpublishes column as sortable.
	 *
	 * @param array $columns Existing sortable columns.
	 * @return array
	 */
	public function sortable_column($columns): array
	{
		$columns[self::COLUMN] = self::COLUMN;

		return $columns;
	}

	/**
	 * Sort products by their scheduled unpublish date.
	 *
	 * @param WP_Query $query Current query.
	 */
	public function order_products($query): void
	{
		if (! is_admin() || ! $query->is_main_query()) {
			return;
		}

		if ('product' !== $query->get('post_type')) {
			return;
		}

		if (self::COLUMN !== $query->get('orderby')) {
			return;
		}

		$query->set('meta_key', Config::META_KEY);
		$query->set('orderby', 'meta_value');
	}

	/**
	 * @param string   $column     Column key.
	 * @param int|null $product_id Product ID.
	 */
	public function render($column, $product_id = null): void
	{
		if (self::COLUMN !== $column) {
			return;
		}

		$product_id = (int) $product_id;
		$timestamp  = $this->schedules->get_timestamp($product_id);

		if (null === $timestamp) {
			echo '&mdash;';
			return;
		}

		/* translators: 1: Scheduled date, 2: Scheduled time. */
		echo esc_html(
			sprintf(
				__('Scheduled %1$s at %2$s', 'woocommerce-product-unpublisher'),
				wp_date('Y/m/d', $timestamp),
				wp_date('H:i', $timestamp)
			)
		);

		if ('publish' === $this->products->status($product_id) && $timestamp <= $this->clock->now()) {
			echo '<br /><span class="wpu-overdue">' . esc_html__('Overdue', 'woocommerce-product-unpublisher') . '</span>';
		}

		if ($this->retry->exhausted($this->schedules->attempts($product_id))) {
			echo '<br /><span class="wpu-failed">' . esc_html__('Automatic retries stopped', 'woocommerce-product-unpublisher') . '</span>';
		}
	}
}
