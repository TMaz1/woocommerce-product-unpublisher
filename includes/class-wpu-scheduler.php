<?php

defined('ABSPATH') || exit;

final class WPU_Scheduler
{

    public function __construct()
    {

        add_action(
            WPU_CRON_HOOK,
            array($this, 'run'),
            10,
            1
        );

        add_action(
            'transition_post_status',
            array($this, 'status_changed'),
            20,
            3
        );
    }

    public function timestamp($value): int|false
    {

        if (! is_string($value) || '' === $value) {
            return false;
        }

        $date = DateTimeImmutable::createFromFormat(
            '!Y-m-d H:i:s',
            $value,
            wp_timezone()
        );

        $errors = DateTimeImmutable::getLastErrors();

        if (
            ! $date ||
            (
                is_array($errors) &&
                (
                    $errors['warning_count'] ||
                    $errors['error_count']
                )
            ) ||
            $date->format('Y-m-d H:i:s') !== $value
        ) {
            return false;
        }

        return $date->getTimestamp();
    }

    public function format_timestamp(int $timestamp): string
    {

        return wp_date(
            get_option('date_format') . ' ' . get_option('time_format'),
            $timestamp,
            wp_timezone()
        );
    }

    public function validate_date(array $values): int|false
    {

        $month  = absint($values['month'] ?? 0);
        $day    = absint($values['day'] ?? 0);
        $year   = absint($values['year'] ?? 0);
        $hour   = absint($values['hour'] ?? 0);
        $minute = absint($values['minute'] ?? 0);

        if (
            ! checkdate($month, $day, $year) ||
            $year < 2000 ||
            $hour > 23 ||
            $minute > 59
        ) {
            return false;
        }

        $value = sprintf(
            '%04d-%02d-%02d %02d:%02d:00',
            $year,
            $month,
            $day,
            $hour,
            $minute
        );

        $date = DateTimeImmutable::createFromFormat(
            '!Y-m-d H:i:s',
            $value,
            wp_timezone()
        );

        $errors = DateTimeImmutable::getLastErrors();

        if (
            ! $date ||
            (
                is_array($errors) &&
                (
                    $errors['warning_count'] ||
                    $errors['error_count']
                )
            ) ||
            $date->format('Y-m-d H:i:s') !== $value ||
            $date->getTimestamp() <= time()
        ) {
            return false;
        }

        return $date->getTimestamp();
    }

    /**
     * Register a cron event and verify it exists.
     */
    public function schedule(int $product_id, int $timestamp): bool
    {

        $args = array($product_id);

        $existing = wp_next_scheduled(
            WPU_CRON_HOOK,
            $args
        );

        if ($existing) {

            if ($existing === $timestamp) {
                return true;
            }

            $this->clear($product_id);
        }

        $result = wp_schedule_single_event(
            $timestamp,
            WPU_CRON_HOOK,
            $args,
            true
        );

        if (is_wp_error($result) || false === $result) {
            return false;
        }

        return wp_next_scheduled(
            WPU_CRON_HOOK,
            $args
        ) === $timestamp;
    }

    public function clear(int $product_id): void
    {

        wp_clear_scheduled_hook(
            WPU_CRON_HOOK,
            array($product_id)
        );
    }

    /**
     * Replace an existing schedule.
     */
    public function replace_schedule(
        int $product_id,
        int $timestamp
    ): bool {

        $old_value = get_post_meta(
            $product_id,
            WPU_META_KEY,
            true
        );

        $old_timestamp = $this->timestamp($old_value);

        $this->clear($product_id);

        if (! $this->schedule($product_id, $timestamp)) {

            if ($old_timestamp && $old_timestamp > time()) {

                $this->schedule(
                    $product_id,
                    $old_timestamp
                );
            }

            return false;
        }

        update_post_meta(
            $product_id,
            WPU_META_KEY,
            wp_date(
                'Y-m-d H:i:s',
                $timestamp,
                wp_timezone()
            )
        );

        delete_post_meta(
            $product_id,
            WPU_ATTEMPTS_META
        );

        return true;
    }

    /**
     * Remove schedule and retry information.
     */
    public function remove_schedule(int $product_id): void
    {

        $this->clear($product_id);

        delete_post_meta(
            $product_id,
            WPU_META_KEY
        );

        delete_post_meta(
            $product_id,
            WPU_ATTEMPTS_META
        );
    }

    /**
     * React to manual status changes.
     */
    public function status_changed(
        string $new_status,
        string $old_status,
        WP_Post $post
    ): void {

        if ('product' !== $post->post_type) {
            return;
        }

        if (
            in_array(
                $new_status,
                array('draft', 'private', 'trash'),
                true
            )
        ) {

            $this->remove_schedule($post->ID);
        }
    }

    /**
     * Log a failure and schedule a retry.
     */
    private function failure(
        int $product_id,
        string $reason
    ): void {

        $attempts = absint(
            get_post_meta(
                $product_id,
                WPU_ATTEMPTS_META,
                true
            )
        ) + 1;

        update_post_meta(
            $product_id,
            WPU_ATTEMPTS_META,
            $attempts
        );

        $log = get_option(
            'wpu_failure_log',
            array()
        );

        if (! is_array($log)) {
            $log = array();
        }

        $log[] = array(
            'product_id' => $product_id,
            'reason'     => sanitize_key($reason),
            'time'       => time(),
            'attempt'    => $attempts,
        );

        update_option(
            'wpu_failure_log',
            array_slice($log, -100),
            false
        );

        if ($attempts >= WPU_MAX_ATTEMPTS) {
            return;
        }

        $delay = min(
            900,
            30 * (2 ** ($attempts - 1))
        );

        $this->schedule(
            $product_id,
            time() + $delay
        );
    }

    /**
     * Execute the scheduled unpublishing.
     */
    public function run($product_id): void
    {

        $product_id = absint($product_id);

        if (! $product_id) {
            return;
        }

        $post = get_post($product_id);

        if (
            ! $post ||
            'product' !== $post->post_type
        ) {
            return;
        }

        $stored = get_post_meta(
            $product_id,
            WPU_META_KEY,
            true
        );

        $timestamp = $this->timestamp($stored);

        if (! $timestamp) {

            $this->remove_schedule($product_id);

            return;
        }

        if ($timestamp > time()) {

            $this->schedule(
                $product_id,
                $timestamp
            );

            return;
        }

        if ('future' === $post->post_status) {

            $publish_timestamp = get_post_timestamp(
                $product_id,
                'date'
            );

            $retry_at = max(
                time() + 60,
                $publish_timestamp
                    ? $publish_timestamp + 60
                    : time() + 60
            );

            $this->schedule(
                $product_id,
                $retry_at
            );

            return;
        }

        if ('publish' !== $post->post_status) {

            $this->remove_schedule($product_id);

            return;
        }

        $result = wp_update_post(
            array(
                'ID'          => $product_id,
                'post_status' => 'draft',
            ),
            true
        );

        if (
            is_wp_error($result) ||
            'draft' !== get_post_status($product_id)
        ) {

            $this->failure(
                $product_id,
                is_wp_error($result)
                    ? 'update_error'
                    : 'status_not_changed'
            );

            return;
        }

        $this->remove_schedule($product_id);
    }
}
