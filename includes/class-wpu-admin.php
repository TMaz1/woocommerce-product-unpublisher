<?php

/**
 * WooCommerce Product Unpublisher
 *
 * Admin interface and product management functionality.
 *
 * @package WooCommerce_Product_Unpublisher
 */

defined('ABSPATH') || exit;

final class WPU_Admin
{

    /**
     * Scheduler instance.
     *
     * @var WPU_Scheduler
     */
    private WPU_Scheduler $scheduler;

    /**
     * Constructor.
     *
     * @param WPU_Scheduler $scheduler Scheduler instance.
     */
    public function __construct(WPU_Scheduler $scheduler)
    {

        $this->scheduler = $scheduler;

        add_action(
            'post_submitbox_misc_actions',
            array($this, 'render_row'),
            4
        );

        add_action(
            'save_post_product',
            array($this, 'save'),
            30,
            3
        );

        add_action(
            'admin_notices',
            array($this, 'notices')
        );

        add_action(
            'admin_enqueue_scripts',
            array($this, 'assets')
        );

        add_action(
            'manage_product_posts_custom_column',
            array($this, 'column_value'),
            10,
            2
        );

        add_filter(
            'bulk_actions-edit-product',
            array($this, 'bulk_actions')
        );

        add_filter(
            'handle_bulk_actions-edit-product',
            array($this, 'handle_bulk_action'),
            10,
            3
        );
    }

    /**
     * Check whether the current screen is the classic product editor.
     *
     * @return bool
     */
    private function editor_screen(): bool
    {

        $screen = get_current_screen();

        return (
            $screen instanceof WP_Screen &&
            'product' === $screen->post_type &&
            in_array(
                $screen->base,
                array('post', 'post-new'),
                true
            )
        );
    }

    /**
     * Load admin styles and scripts.
     *
     * @param string $hook Current admin page.
     * @return void
     */
    public function assets($hook): void
    {

        if (
            ! in_array(
                $hook,
                array('post.php', 'post-new.php'),
                true
            ) ||
            ! $this->editor_screen()
        ) {
            return;
        }

        wp_enqueue_style(
            'wpu-admin',
            WPU_URL . 'assets/css/unpublisher.css',
            array(),
            WPU_VERSION
        );

        wp_enqueue_script(
            'wpu-admin',
            WPU_URL . 'assets/js/unpublisher.js',
            array('jquery'),
            WPU_VERSION,
            true
        );

        wp_localize_script(
            'wpu-admin',
            'wpuAdmin',
            array(
                'invalid' => __(
                    'Please enter a valid date and time.',
                    'woocommerce-product-unpublisher'
                ),

                'past' => __(
                    'Choose a date and time in the future.',
                    'woocommerce-product-unpublisher'
                ),

                'before' => __(
                    'Unpublishing must be later than scheduled publication.',
                    'woocommerce-product-unpublisher'
                ),

                'confirmRemove' => __(
                    'Remove the unpublishing schedule?',
                    'woocommerce-product-unpublisher'
                ),

                'monthNames' => array(
                    __('January', 'woocommerce-product-unpublisher'),
                    __('February', 'woocommerce-product-unpublisher'),
                    __('March', 'woocommerce-product-unpublisher'),
                    __('April', 'woocommerce-product-unpublisher'),
                    __('May', 'woocommerce-product-unpublisher'),
                    __('June', 'woocommerce-product-unpublisher'),
                    __('July', 'woocommerce-product-unpublisher'),
                    __('August', 'woocommerce-product-unpublisher'),
                    __('September', 'woocommerce-product-unpublisher'),
                    __('October', 'woocommerce-product-unpublisher'),
                    __('November', 'woocommerce-product-unpublisher'),
                    __('December', 'woocommerce-product-unpublisher'),
                ),

                'dateFormat' => get_option('date_format'),
                'timeFormat' => get_option('time_format'),

                'now' => array_map(
                    'intval',
                    explode(
                        ',',
                        current_datetime()->format('Y,n,j,G,i')
                    )
                ),

                'unpublishLabel' => __(
                    'Unpublish on:',
                    'woocommerce-product-unpublisher'
                ),

                'removalPending' => __(
                    'Schedule will be removed when you update.',
                    'woocommerce-product-unpublisher'
                ),

                'notScheduled' => __(
                    'Not scheduled',
                    'woocommerce-product-unpublisher'
                ),
            )
        );
    }

    /**
     * Get date and time components.
     *
     * @param int|false $timestamp Timestamp.
     * @return array
     */
    private function date_parts($timestamp): array
    {
        $timestamp = $timestamp
            ? (int) $timestamp
            : current_datetime()->getTimestamp();

        return array(
            wp_date('m', $timestamp, wp_timezone()),
            wp_date('d', $timestamp, wp_timezone()),
            wp_date('Y', $timestamp, wp_timezone()),
            wp_date('H', $timestamp, wp_timezone()),
            wp_date('i', $timestamp, wp_timezone()),
        );
    }

    /**
     * Render the unpublishing control in the Publish box.
     *
     * @param WP_Post $post Current post.
     * @return void
     */
    public function render_row($post): void
    {

        if (
            ! $post instanceof WP_Post ||
            'product' !== $post->post_type ||
            ! current_user_can('edit_post', $post->ID)
        ) {
            return;
        }

        wp_nonce_field(
            'wpu_save_schedule',
            'wpu_nonce'
        );

        $stored = get_post_meta(
            $post->ID,
            WPU_META_KEY,
            true
        );

        $timestamp = $this->scheduler->timestamp($stored);

        $parts = $this->date_parts($timestamp);

        $display = $timestamp
            ? $this->scheduler->format_timestamp($timestamp)
            : __('Not scheduled', 'woocommerce-product-unpublisher');

        $label = $timestamp && $timestamp <= time()
            ? __('Unpublishing overdue:', 'woocommerce-product-unpublisher')
            : __('Unpublish on:', 'woocommerce-product-unpublisher');

        $months = array(
            '01' => __('January', 'woocommerce-product-unpublisher'),
            '02' => __('February', 'woocommerce-product-unpublisher'),
            '03' => __('March', 'woocommerce-product-unpublisher'),
            '04' => __('April', 'woocommerce-product-unpublisher'),
            '05' => __('May', 'woocommerce-product-unpublisher'),
            '06' => __('June', 'woocommerce-product-unpublisher'),
            '07' => __('July', 'woocommerce-product-unpublisher'),
            '08' => __('August', 'woocommerce-product-unpublisher'),
            '09' => __('September', 'woocommerce-product-unpublisher'),
            '10' => __('October', 'woocommerce-product-unpublisher'),
            '11' => __('November', 'woocommerce-product-unpublisher'),
            '12' => __('December', 'woocommerce-product-unpublisher'),
        );

?>


        <div
            class="misc-pub-section curtime misc-pub-curtime"
            id="wpu-publish-row">

            <span
                class="dashicons dashicons-calendar-alt"
                aria-hidden="true"></span>

            <span id="wpu-timestamp">

                <span id="wpu-label">
                    <?php echo esc_html($label); ?>
                </span>

                <strong id="wpu-current-value">
                    <?php
                    echo esc_html(
                        $timestamp
                            ? wp_date(
                                get_option('date_format') . ' \a\t ' . get_option('time_format'),
                                $timestamp,
                                wp_timezone()
                            )
                            : __('Not scheduled', 'woocommerce-product-unpublisher')
                    );
                    ?>
                </strong>

            </span>

            <a
                href="#wpu-edit"
                class="edit-timestamp hide-if-no-js"
                id="wpu-edit-toggle"
                role="button"
                aria-expanded="false"
                aria-controls="wpu-edit-panel">

                <span aria-hidden="true">
                    <?php esc_html_e('Edit', 'woocommerce-product-unpublisher'); ?>
                </span>

                <span class="screen-reader-text">
                    <?php esc_html_e('Edit unpublishing date and time', 'woocommerce-product-unpublisher'); ?>
                </span>

            </a>

            <input
                type="hidden"
                name="wpu_action"
                id="wpu_action"
                value="" />

            <fieldset
                id="wpu-edit-panel"
                hidden>

                <legend class="screen-reader-text">
                    <?php esc_html_e('Unpublishing date and time', 'woocommerce-product-unpublisher'); ?>
                </legend>

                <div class="timestamp-wrap">

                    <label>
                        <span class="screen-reader-text">
                            <?php esc_html_e('Month', 'woocommerce-product-unpublisher'); ?>
                        </span>

                        <select
                            id="wpu_mm"
                            name="wpu_mm"
                            class="form-required">

                            <?php foreach ($months as $number => $month) : ?>

                                <option
                                    value="<?php echo esc_attr($number); ?>"
                                    <?php selected($parts[0], $number); ?>>

                                    <?php echo esc_html($number . '-' . substr($month, 0, 3)); ?>

                                </option>

                            <?php endforeach; ?>

                        </select>
                    </label>

                    <label>
                        <span class="screen-reader-text">
                            <?php esc_html_e('Day', 'woocommerce-product-unpublisher'); ?>
                        </span>

                        <input
                            type="text"
                            id="wpu_jj"
                            name="wpu_jj"
                            value="<?php echo esc_attr($parts[1]); ?>"
                            size="2"
                            maxlength="2"
                            autocomplete="off"
                            class="form-required"
                            inputmode="numeric" />
                    </label>,

                    <label>
                        <span class="screen-reader-text">
                            <?php esc_html_e('Year', 'woocommerce-product-unpublisher'); ?>
                        </span>

                        <input
                            type="text"
                            id="wpu_aa"
                            name="wpu_aa"
                            value="<?php echo esc_attr($parts[2]); ?>"
                            size="4"
                            maxlength="4"
                            autocomplete="off"
                            class="form-required"
                            inputmode="numeric" />
                    </label>

                    <?php esc_html_e('at', 'woocommerce-product-unpublisher'); ?>

                    <label>
                        <span class="screen-reader-text">
                            <?php esc_html_e('Hour', 'woocommerce-product-unpublisher'); ?>
                        </span>

                        <input
                            type="text"
                            id="wpu_hh"
                            name="wpu_hh"
                            value="<?php echo esc_attr($parts[3]); ?>"
                            size="2"
                            maxlength="2"
                            autocomplete="off"
                            class="form-required"
                            inputmode="numeric" />
                    </label>:<label>
                        <span class="screen-reader-text">
                            <?php esc_html_e('Minute', 'woocommerce-product-unpublisher'); ?>
                        </span>

                        <input
                            type="text"
                            id="wpu_mn"
                            name="wpu_mn"
                            value="<?php echo esc_attr($parts[4]); ?>"
                            size="2"
                            maxlength="2"
                            autocomplete="off"
                            class="form-required"
                            inputmode="numeric" />
                    </label>

                </div>

                <p
                    id="wpu-error"
                    class="wpu-error"
                    role="alert"
                    hidden></p>

                <p>

                    <a
                        href="#wpu-save"
                        class="save-timestamp button"
                        id="wpu-ok">

                        <?php esc_html_e('OK', 'woocommerce-product-unpublisher'); ?>

                    </a>

                    <a
                        href="#wpu-cancel"
                        class="cancel-timestamp button-cancel"
                        id="wpu-cancel">

                        <?php esc_html_e('Cancel', 'woocommerce-product-unpublisher'); ?>

                    </a>

                    <a
                        href="#wpu-remove"
                        class="wpu-remove"
                        id="wpu-remove"
                        <?php echo $timestamp ? '' : 'hidden'; ?>>

                        <?php esc_html_e('Remove schedule', 'woocommerce-product-unpublisher'); ?>

                    </a>

                </p>

            </fieldset>

        </div>

<?php
    }

    /**
     * Save the unpublishing schedule.
     *
     * @param int     $post_id Post ID.
     * @param WP_Post $post    Post object.
     * @param bool    $update  Whether this is an existing post.
     * @return void
     */
    public function save($post_id, $post, $update): void
    {

        if (
            ! $post instanceof WP_Post ||
            'product' !== $post->post_type ||
            (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) ||
            wp_is_post_revision($post_id) ||
            wp_is_post_autosave($post_id) ||
            ! current_user_can('edit_post', $post_id)
        ) {
            return;
        }

        if (
            ! isset($_POST['wpu_nonce']) ||
            ! is_string($_POST['wpu_nonce'])
        ) {
            return;
        }

        $nonce = sanitize_text_field(
            wp_unslash($_POST['wpu_nonce'])
        );

        if (! wp_verify_nonce($nonce, 'wpu_save_schedule')) {
            return;
        }

        $action = isset($_POST['wpu_action']) &&
            is_string($_POST['wpu_action'])
            ? sanitize_key(wp_unslash($_POST['wpu_action']))
            : '';


        if ('remove' === $action) {

            if ($this->scheduler->remove_schedule($post_id)) {

                $this->notice(
                    $post_id,
                    __(
                        'The product unpublishing schedule has been removed.',
                        'woocommerce-product-unpublisher'
                    ),
                    'success'
                );
            } else {

                $this->notice(
                    $post_id,
                    __(
                        'Could not completely remove the unpublishing schedule. Please try again.',
                        'woocommerce-product-unpublisher'
                    ),
                    'error'
                );
            }

            return;
        }

        // No action means the user is updating the product without changing its unpublishing schedule.
        if ('save' !== $action) {
            return;
        }

        if (
            ! in_array(
                $post->post_status,
                array('publish', 'future'),
                true
            )
        ) {
            $this->notice(
                $post_id,
                __(
                    'Only published or scheduled products can have an unpublishing schedule.',
                    'woocommerce-product-unpublisher'
                ),
                'error'
            );

            return;
        }

        $keys = array(
            'wpu_mm' => 'month',
            'wpu_jj' => 'day',
            'wpu_aa' => 'year',
            'wpu_hh' => 'hour',
            'wpu_mn' => 'minute',
        );

        $values = array();

        foreach ($keys as $key => $name) {

            if (
                ! isset($_POST[$key]) ||
                ! is_string($_POST[$key])
            ) {
                $this->notice(
                    $post_id,
                    __(
                        'The unpublishing date is incomplete. The previous schedule was retained.',
                        'woocommerce-product-unpublisher'
                    ),
                    'error'
                );

                return;
            }

            $raw = trim(
                wp_unslash($_POST[$key])
            );

            if (! preg_match('/^\d+$/', $raw)) {
                $this->notice(
                    $post_id,
                    __(
                        'The unpublishing date contains invalid values. The previous schedule was retained.',
                        'woocommerce-product-unpublisher'
                    ),
                    'error'
                );

                return;
            }

            $values[$name] = absint($raw);
        }

        $timestamp = $this->scheduler->validate_date($values);

        if (! $timestamp) {
            $this->notice(
                $post_id,
                __(
                    'Choose a valid unpublishing date and time in the future. The previous schedule was retained.',
                    'woocommerce-product-unpublisher'
                ),
                'error'
            );

            return;
        }

        if ('future' === $post->post_status) {

            $publish_timestamp = get_post_timestamp(
                $post_id,
                'date'
            );

            if (
                $publish_timestamp &&
                $timestamp <= $publish_timestamp
            ) {
                $this->notice(
                    $post_id,
                    __(
                        'Unpublishing must be later than scheduled publication. The previous schedule was retained.',
                        'woocommerce-product-unpublisher'
                    ),
                    'error'
                );

                return;
            }
        }

        if (
            ! $this->scheduler->replace_schedule(
                $post_id,
                $timestamp
            )
        ) {

            $this->notice(
                $post_id,
                __(
                    'Could not schedule unpublishing. Check WordPress cron configuration. Your previous schedule may have been retained.',
                    'woocommerce-product-unpublisher'
                ),
                'error'
            );

            return;
        }

        // Format using the WordPress site timezone.
        $formatted_date = wp_date(
            'M j, Y \@ H:i',
            $timestamp,
            wp_timezone()
        );

        // Get the product preview URL.
        $preview_url = get_preview_post_link($post_id);

        // Build the success message.
        $message = sprintf(
            /* translators: 1: date, 2: preview link */
            __(
                'Product scheduled unpublishing for: %1$s. %2$s',
                'woocommerce-product-unpublisher'
            ),
            esc_html($formatted_date),
            $preview_url
                ? sprintf(
                    '<a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>',
                    esc_url($preview_url),
                    esc_html__(
                        'Preview product',
                        'woocommerce-product-unpublisher'
                    )
                )
                : ''
        );

        $this->notice(
            $post_id,
            $message,
            'success'
        );
    }

    /**
     * Store an admin notice for a specific product.
     *
     * @param int    $post_id Post ID.
     * @param string $message Notice message.
     * @param string $type    Notice type.
     * @return void
     */
    private function notice(
        int $post_id,
        string $message,
        string $type = 'error'
    ): void {

        $allowed_types = array(
            'success',
            'error',
            'warning',
            'info',
        );

        if (! in_array($type, $allowed_types, true)) {
            $type = 'error';
        }

        $key = sprintf(
            'wpu_notice_%d_%d',
            get_current_user_id(),
            $post_id
        );

        set_transient(
            $key,
            array(
                'message' => $message,
                'type'    => $type,
            ),
            5 * MINUTE_IN_SECONDS
        );
    }

    /**
     * Display admin notices.
     *
     * @return void
     */
    public function notices(): void
    {

        if (! is_admin()) {
            return;
        }

        $screen = get_current_screen();

        if (
            ! $screen instanceof WP_Screen ||
            'product' !== $screen->post_type
        ) {
            return;
        }

        // Individual product notices.
        if (
            isset($_GET['post']) &&
            current_user_can('edit_products')
        ) {

            $post_id = absint(
                wp_unslash($_GET['post'])
            );

            if ($post_id && current_user_can('edit_post', $post_id)) {

                $key = sprintf(
                    'wpu_notice_%d_%d',
                    get_current_user_id(),
                    $post_id
                );

                $notice = get_transient($key);

                if (is_array($notice)) {

                    delete_transient($key);

                    $type = isset($notice['type'])
                        ? sanitize_html_class($notice['type'])
                        : 'error';

                    $message = isset($notice['message'])
                        ? $notice['message']
                        : '';

                    printf(
                        '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
                        esc_attr($type),
                        wp_kses_post($message)
                    );
                }
            }
        }

        // Bulk action notices.
        if (
            'edit' === $screen->base &&
            isset($_GET['wpu_bulk_done']) &&
            current_user_can('edit_products')
        ) {

            $count = absint(
                wp_unslash($_GET['wpu_bulk_done'])
            );

            printf(
                '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
                esc_html(
                    sprintf(
                        _n(
                            '%d product moved to Draft.',
                            '%d products moved to Draft.',
                            $count,
                            'woocommerce-product-unpublisher'
                        ),
                        $count
                    )
                )
            );
        }
    }

    /**
     * Display the scheduled date in the product list.
     *
     * @param string $column     Column name.
     * @param int    $product_id Product ID.
     * @return void
     */
    public function column_value($column, $product_id): void
    {

        if ('wpu_unpublish' !== $column) {
            return;
        }

        $timestamp = $this->scheduler->timestamp(
            get_post_meta(
                $product_id,
                WPU_META_KEY,
                true
            )
        );

        if ($timestamp) {

            echo esc_html(
                $this->scheduler->format_timestamp($timestamp)
            );
        } else {

            echo '&mdash;';
        }
    }

    /**
     * Register the product bulk action.
     *
     * @param array $actions Existing actions.
     * @return array
     */
    public function bulk_actions($actions): array
    {

        $actions['wpu_draft_now'] = __(
            'Move Published to Draft now',
            'woocommerce-product-unpublisher'
        );

        return $actions;
    }

    /**
     * Handle the bulk action.
     *
     * @param string $redirect_url Redirect URL.
     * @param string $action       Selected action.
     * @param array  $product_ids  Selected product IDs.
     * @return string
     */
    public function handle_bulk_action(
        $redirect_url,
        $action,
        $product_ids
    ): string {

        if ('wpu_draft_now' !== $action) {
            return $redirect_url;
        }

        $count = 0;

        foreach (array_map('absint', (array) $product_ids) as $product_id) {

            if (
                ! $product_id ||
                ! current_user_can('edit_post', $product_id)
            ) {
                continue;
            }

            $post = get_post($product_id);

            if (
                ! $post ||
                'product' !== $post->post_type ||
                'publish' !== $post->post_status
            ) {
                continue;
            }

            $result = wp_update_post(
                array(
                    'ID'          => $product_id,
                    'post_status' => 'draft',
                ),
                true
            );

            if (
                ! is_wp_error($result) &&
                'draft' === get_post_status($product_id)
            ) {

                $this->scheduler->remove_schedule($product_id);

                $count++;
            }
        }

        return add_query_arg(
            'wpu_bulk_done',
            $count,
            $redirect_url
        );
    }
}
