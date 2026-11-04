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
     *
     * @param int $product_id Product ID.
     * @param int $timestamp  Scheduled timestamp.
     * @return bool Whether scheduling succeeded.
     */
    public function schedule(int $product_id, int $timestamp): bool
    {

        $args = array($product_id);

        $existing = wp_next_scheduled(
            WPU_CRON_HOOK,
            $args
        );

        if (false !== $existing) {

            if ($existing === $timestamp) {
                return true;
            }

            if (! $this->clear($product_id)) {
                return false;
            }
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

    /**
     * Clear scheduled cron events.
     *
     * @param int $product_id Product ID.
     * @return bool Whether all matching events were cleared.
     */
    public function clear(int $product_id): bool
    {

        $args = array($product_id);

        $result = wp_clear_scheduled_hook(
            WPU_CRON_HOOK,
            $args,
            true
        );

        if (
            is_wp_error($result) ||
            false === $result
        ) {
            return false;
        }

        return false === wp_next_scheduled(
            WPU_CRON_HOOK,
            $args
        );
    }

    /**
     * Replace an existing schedule.
     *
     * @param int $product_id Product ID.
     * @param int $timestamp  New timestamp.
     * @return bool Whether replacement succeeded.
     */
    public function replace_schedule(
        int $product_id,
        int $timestamp
    ): bool {

        $had_old_meta = metadata_exists(
            'post',
            $product_id,
            WPU_META_KEY
        );

        $old_value = get_post_meta(
            $product_id,
            WPU_META_KEY,
            true
        );

        $old_timestamp = $this->timestamp($old_value);

        // Remove the previous cron event.
        if (! $this->clear($product_id)) {
            return false;
        }

        // Register the replacement event.
        if (! $this->schedule($product_id, $timestamp)) {

            // Ensure any partially registered replacement is removed.
            $this->clear($product_id);

            // Restore the previous event.
            if (
                $old_timestamp &&
                $old_timestamp > time() &&
                ! $this->restore_schedule(
                    $old_timestamp,
                    $product_id
                )
            ) {
                error_log(
                    sprintf(
                        'WPU: Failed to restore previous schedule for product %d.',
                        $product_id
                    )
                );
            }

            return false;
        }

        // Store the new scheduled date.
        update_post_meta(
            $product_id,
            WPU_META_KEY,
            wp_date(
                'Y-m-d H:i:s',
                $timestamp,
                wp_timezone()
            )
        );

        // Verify that metadata and cron agree.
        $stored = get_post_meta(
            $product_id,
            WPU_META_KEY,
            true
        );

        if ($this->timestamp($stored) !== $timestamp) {

            // Remove the new cron event.
            $this->clear($product_id);

            // Restore original metadata.
            if ($had_old_meta) {

                update_post_meta(
                    $product_id,
                    WPU_META_KEY,
                    $old_value
                );
            } else {

                delete_post_meta(
                    $product_id,
                    WPU_META_KEY
                );
            }

            // Verify that the original metadata was restored.
            $restored_value = get_post_meta(
                $product_id,
                WPU_META_KEY,
                true
            );

            if (
                $had_old_meta &&
                $restored_value !== $old_value
            ) {
                error_log(
                    sprintf(
                        'WPU: Failed to restore previous metadata for product %d.',
                        $product_id
                    )
                );
            }

            // Restore the previous cron event.
            if (
                $old_timestamp &&
                $old_timestamp > time() &&
                ! $this->restore_schedule(
                    $old_timestamp,
                    $product_id
                )
            ) {
                error_log(
                    sprintf(
                        'WPU: Failed to restore previous schedule for product %d.',
                        $product_id
                    )
                );
            }

            return false;
        }

        delete_post_meta(
            $product_id,
            WPU_ATTEMPTS_META
        );

        return true;
    }

    /**
     * Remove schedule and retry information.
     *
     * @param int $product_id Product ID.
     * @return bool Whether the schedule was completely removed.
     */
    public function remove_schedule(int $product_id): bool
    {

        // Attempt to clear all matching cron events.
        $this->clear($product_id);

        // Remove scheduling metadata regardless of cron cleanup result.
        delete_post_meta(
            $product_id,
            WPU_META_KEY
        );

        delete_post_meta(
            $product_id,
            WPU_ATTEMPTS_META
        );

        // Verify that metadata has been removed.
        $metadata_removed = (
            ! metadata_exists(
                'post',
                $product_id,
                WPU_META_KEY
            ) &&
            ! metadata_exists(
                'post',
                $product_id,
                WPU_ATTEMPTS_META
            )
        );

        // Verify that no matching cron event remains.
        $cron_removed = false === wp_next_scheduled(
            WPU_CRON_HOOK,
            array($product_id)
        );

        return $metadata_removed && $cron_removed;
    }

    /**
     * React to manual status changes.
     *
     * @param string $new_status New post status.
     * @param string $old_status Previous post status.
     * @param WP_Post $post Post object.
     * @return void
     */
    public function status_changed(
        string $new_status,
        string $old_status,
        WP_Post $post
    ): void {

        if ('product' !== $post->post_type) {
            return;
        }

        $inactive_statuses = array(
            'draft',
            'private',
            'trash',
        );

        $became_inactive = in_array(
            $new_status,
            $inactive_statuses,
            true
        );

        $was_inactive = in_array(
            $old_status,
            $inactive_statuses,
            true
        );

        $became_published = in_array(
            $new_status,
            array('publish', 'future'),
            true
        );

        if ($became_inactive || ($was_inactive && $became_published)) {

            if (! $this->remove_schedule($post->ID)) {

                error_log(
                    sprintf(
                        'WPU: Failed to completely remove schedule for product %d after status transition.',
                        $post->ID
                    )
                );
            }
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

        if (! $this->schedule(
            $product_id,
            time() + $delay
        )) {

            error_log(
                sprintf(
                    'WPU: Failed to register retry event for product %d.',
                    $product_id
                )
            );
        }
    }

    /**
     * Restore a previous cron schedule.
     *
     * @param int|false $timestamp Previous timestamp.
     * @param int       $product_id Product ID.
     * @return bool Whether restoration succeeded.
     */
    private function restore_schedule(
        int|false $timestamp,
        int $product_id
    ): bool {

        if (! $timestamp || $timestamp <= time()) {
            return false;
        }

        return $this->schedule(
            $product_id,
            $timestamp
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

            if (! $this->remove_schedule($product_id)) {

                error_log(
                    sprintf(
                        'WPU: Failed to clean up invalid schedule for product %d.',
                        $product_id
                    )
                );
            }

            return;
        }

        if ($timestamp > time()) {

            if (! $this->schedule($product_id, $timestamp)) {

                $this->failure(
                    $product_id,
                    'reschedule_failed'
                );
            }

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

            if (! $this->schedule($product_id, $retry_at)) {

                $this->failure(
                    $product_id,
                    'publication_retry_failed'
                );
            }

            return;
        }

        if ('publish' !== $post->post_status) {

            if (! $this->remove_schedule($product_id)) {

                error_log(
                    sprintf(
                        'WPU: Failed to remove inactive product schedule for product %d.',
                        $product_id
                    )
                );
            }

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

        if (! $this->remove_schedule($product_id)) {

            error_log(
                sprintf(
                    'WPU: Product %d was moved to Draft, but schedule cleanup failed.',
                    $product_id
                )
            );
        }
    }
}
