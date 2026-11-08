<?php

use ProductUnpublisher\Config;
use ProductUnpublisher\Product\ProductDuplicateHandler;
use ProductUnpublisher\Tests\Integration\ProductTestCase;

final class ProductDuplicationIntegrationTest extends ProductTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        (new ProductDuplicateHandler())->register();
    }

    public function test_duplication_excludes_unpublishing_metadata(): void
    {
        $original_id = $this->create_product();

        $schedule_value = '2026-11-07 00:20:00';
        $attempts_value = 3;

        update_post_meta($original_id, Config::META_KEY, $schedule_value);
        update_post_meta($original_id, Config::ATTEMPTS_META, $attempts_value);

        $existing_exclusions = array(
            '_regular_price',
            '_price',
        );

        $excluded_meta = apply_filters(
            'woocommerce_duplicate_product_exclude_meta',
            $existing_exclusions,
            array(
                Config::META_KEY,
                Config::ATTEMPTS_META,
                '_regular_price',
                '_price',
            )
        );

        // Preserve WooCommerce's existing exclusions.
        $this->assertContains('_regular_price', $excluded_meta);
        $this->assertContains('_price', $excluded_meta);

        // Exclude both plugin metadata keys.
        $this->assertContains(Config::META_KEY, $excluded_meta);
        $this->assertContains(Config::ATTEMPTS_META, $excluded_meta);

        // Do not introduce duplicate exclusion entries.
        $this->assertSame(
            count($excluded_meta),
            count(array_unique($excluded_meta))
        );

        // The original product remains untouched.
        $this->assertSame(
            $schedule_value,
            get_post_meta($original_id, Config::META_KEY, true)
        );

        $this->assertSame(
            $attempts_value,
            (int) get_post_meta($original_id, Config::ATTEMPTS_META, true)
        );
    }
}