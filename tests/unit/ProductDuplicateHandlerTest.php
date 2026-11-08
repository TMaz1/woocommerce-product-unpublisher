<?php

use PHPUnit\Framework\TestCase;
use ProductUnpublisher\Config;
use ProductUnpublisher\Product\ProductDuplicateHandler;

final class ProductDuplicateHandlerTest extends TestCase
{

    private ProductDuplicateHandler $handler;

    protected function setUp(): void
    {
        $this->handler = new ProductDuplicateHandler();
    }

    public function test_excludes_schedule_and_attempt_metadata(): void
    {
        $result = $this->handler->exclude_schedule_meta(
            array(),
            array(
                Config::META_KEY,
                Config::ATTEMPTS_META,
                '_regular_price',
                '_sku',
            )
        );

        $this->assertContains(Config::META_KEY, $result);
        $this->assertContains(Config::ATTEMPTS_META, $result);
    }

    public function test_preserves_existing_excluded_metadata(): void
    {
        $existing_exclusions = array(
            '_some_other_plugin_meta',
        );

        $result = $this->handler->exclude_schedule_meta(
            $existing_exclusions,
            array()
        );

        $this->assertContains('_some_other_plugin_meta', $result);
        $this->assertContains(Config::META_KEY, $result);
        $this->assertContains(Config::ATTEMPTS_META, $result);
    }

    public function test_does_not_add_duplicate_exclusion_keys(): void
    {
        $result = $this->handler->exclude_schedule_meta(
            array(
                Config::META_KEY,
                Config::ATTEMPTS_META,
            ),
            array()
        );

        $this->assertCount(
            1,
            array_keys($result, Config::META_KEY, true)
        );

        $this->assertCount(
            1,
            array_keys($result, Config::ATTEMPTS_META, true)
        );
    }
}
