<?php

/**
 * Controls which product unpublishing data is excluded from duplication.
 *
 * @package ProductUnpublisher
 */

namespace ProductUnpublisher\Product;

use ProductUnpublisher\Config;

defined('ABSPATH') || exit;

final class ProductDuplicateHandler
{

    /**
     * Register WooCommerce duplication hooks.
     */
    public function register(): void
    {
        add_filter(
            'woocommerce_duplicate_product_exclude_meta',
            array($this, 'exclude_schedule_meta'),
            10,
            2
        );
    }

    /**
     * Exclude scheduling metadata when a product is duplicated.
     *
     * @param array $exclude_meta      Metadata keys already excluded.
     * @param array $existing_meta_keys Metadata keys on the original product.
     * @return array
     */
    public function exclude_schedule_meta(
        array $exclude_meta,
        array $existing_meta_keys
    ): array {
        $exclude_meta[] = Config::META_KEY;
        $exclude_meta[] = Config::ATTEMPTS_META;

        return array_unique($exclude_meta);
    }
}
