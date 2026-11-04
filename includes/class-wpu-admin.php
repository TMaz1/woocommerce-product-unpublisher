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
            array($this, 'render_row')
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

        add_filter(
            'manage_edit-product_columns',
            array($this, 'add_column')
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
            : current_time('timestamp');

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
            class="misc-pub-section misc-pub-curtime"
            id="wpu-publish-row">

            <span
                class="dashicons dashicons-calendar-alt"
                aria-hidden="true"></span>

            <strong id="wpu-label">
                <?php echo esc_html($label); ?>
            </strong>

            <span id="wpu-current-value">
                <?php echo esc_html($display); ?>
            </span>

            <a
                href="#wpu-edit"
                class="edit-timestamp"
                id="wpu-edit-toggle">
                <?php esc_html_e('Edit', 'woocommerce-product-unpublisher'); ?>
            </a>

            <input
                type="hidden"
                name="wpu_action"
                id="wpu_action"
                value="" />

            <div id="wpu-edit-panel" hidden>

                <fieldset class="wpu-fields">

                    <legend class="screen-reader-text">
                        <?php
                        esc_html_e(
                            'Unpublishing date and time',
                            'woocommerce-product-unpublisher'
                        );
                        ?>
                    </legend>

                    <div class="wpu-date-fields">

                        <label
                            class="screen-reader-text"
                            for="wpu_mm">
                            <?php esc_html_e('Month', 'woocommerce-product-unpublisher'); ?>
                        </label>

                        <select
                            id="wpu_mm"
                            name="wpu_mm">

                            <?php foreach ($months as $number => $month) : ?>

                                <option
                                    value="<?php echo esc_attr($number); ?>"
                                    <?php selected($parts[0], $number); ?>>
                                    <?php echo esc_html($month); ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                        <label
                            class="screen-reader-text"
                            for="wpu_jj">
                            <?php esc_html_e('Day', 'woocommerce-product-unpublisher'); ?>
                        </label>

                        <input
                            type="text"
                            id="wpu_jj"
                            name="wpu_jj"
                            value="<?php echo esc_attr($parts[1]); ?>"
                            maxlength="2"
                            inputmode="numeric" />

                        <span>,</span>

                        <label
                            class="screen-reader-text"
                            for="wpu_aa">
                            <?php esc_html_e('Year', 'woocommerce-product-unpublisher'); ?>
                        </label>

                        <input
                            type="text"
                            id="wpu_aa"
                            name="wpu_aa"
                            value="<?php echo esc_attr($parts[2]); ?>"
                            maxlength="4"
                            inputmode="numeric" />

                    </div>

                    <div class="wpu-time-fields">

                        <span>
                            <?php esc_html_e('at', 'woocommerce-product-unpublisher'); ?>
                        </span>

                        <label
                            class="screen-reader-text"
                            for="wpu_hh">
                            <?php esc_html_e('Hour', 'woocommerce-product-unpublisher'); ?>
                        </label>

                        <input
                            type="text"
                            id="wpu_hh"
                            name="wpu_hh"
                            value="<?php echo esc_attr($parts[3]); ?>"
                            maxlength="2"
                            inputmode="numeric" />

                        <span>:</span>

                        <label
                            class="screen-reader-text"
                            for="wpu_mn">
                            <?php esc_html_e('Minute', 'woocommerce-product-unpublisher'); ?>
                        </label>

                        <input
                            type="text"
                            id="wpu_mn"
                            name="wpu_mn"
                            value="<?php echo esc_attr($parts[4]); ?>"
                            maxlength="2"
                            inputmode="numeric" />

                    </div>

                    <p
                        id="wpu-error"
                        class="wpu-error"
                        role="alert"
                        hidden></p>

                    <p class="wpu-actions">

                        <button
                            type="button"
                            class="button button-primary button-small"
                            id="wpu-ok">
                            <?php esc_html_e('OK', 'woocommerce-product-unpublisher'); ?>
                        </button>

                        <a
                            href="#wpu-cancel"
                            class="cancel-timestamp"
                            id="wpu-cancel">
                            <?php esc_html_e('Cancel', 'woocommerce-product-unpublisher'); ?>
                        </a>

                        <?php if ($timestamp) : ?>

                            <a
                                href="#wpu-remove"
                                class="wpu-remove"
                                id="wpu-remove">
                                <?php esc_html_e('Remove schedule', 'woocommerce-product-unpublisher'); ?>
                            </a>

                        <?php endif; ?>

                    </p>

                </fieldset>

            </div>

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

        if ('' === $action) {
            return;
        }

        if ('remove' === $action) {

            $this->scheduler->remove_schedule($post_id);

            return;
        }

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
                __(
                    'Only published or scheduled products can have an unpublishing schedule.',
                    'woocommerce-product-unpublisher'
                )
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
                    __(
                        'The unpublishing date is incomplete. The previous schedule was retained.',
                        'woocommerce-product-unpublisher'
                    )
                );

                return;
            }

            $raw = trim(
                wp_unslash($_POST[$key])
            );

            if (! preg_match('/^\d+$/', $raw)) {
                $this->notice(
                    __(
                        'The unpublishing date contains invalid values. The previous schedule was retained.',
                        'woocommerce-product-unpublisher'
                    )
                );

                return;
            }

            $values[$name] = absint($raw);
        }

        $timestamp = $this->scheduler->validate_date($values);

        if (! $timestamp) {
            $this->notice(
                __(
                    'Choose a valid unpublishing date and time in the future. The previous schedule was retained.',
                    'woocommerce-product-unpublisher'
                )
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
                    __(
                        'Unpublishing must be later than scheduled publication. The previous schedule was retained.',
                        'woocommerce-product-unpublisher'
                    )
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
                __(
                    'Could not schedule unpublishing. Check WordPress cron configuration.',
                    'woocommerce-product-unpublisher'
                )
            );
        }
    }

    /**
     * Store an admin notice for the current user.
     *
     * @param string $message Notice message.
     * @return void
     */
    private function notice(string $message): void
    {

        set_transient(
            'wpu_notice_' . get_current_user_id(),
            $message,
            MINUTE_IN_SECONDS
        );
    }

    /**
     * Display admin notices.
     *
     * @return void
     */
    public function notices(): void
    {

        if (
            ! is_admin() ||
            ! current_user_can('edit_products')
        ) {
            return;
        }

        $key = 'wpu_notice_' . get_current_user_id();

        $message = get_transient($key);

        if ($message) {

            delete_transient($key);

            printf(
                '<div class="notice notice-error is-dismissible"><p>%s</p></div>',
                esc_html($message)
            );
        }

        if (isset($_GET['wpu_bulk_done'])) {

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
     * Add the unpublishing column to the product list.
     *
     * @param array $columns Existing columns.
     * @return array
     */
    public function add_column($columns): array
    {

        $columns['wpu_unpublish'] = __(
            'Unpublish on',
            'woocommerce-product-unpublisher'
        );

        return $columns;
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
            'Move selected published products to Draft now',
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
