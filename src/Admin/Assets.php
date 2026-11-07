<?php
/**
 * Loads admin scripts and styles on the product editor and product list screens.
 *
 * @package ProductUnpublisher
 */

namespace ProductUnpublisher\Admin;

use ProductUnpublisher\Config;
use ProductUnpublisher\Time\Clock;

defined( 'ABSPATH' ) || exit;

final class Assets {

	public function __construct( private Clock $clock ) {}

	public function register(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * @param string $hook Current admin page hook suffix.
	 */
	public function enqueue( $hook ): void {
		$screen = get_current_screen();

		if ( ! $screen instanceof \WP_Screen || Config::POST_TYPE !== $screen->post_type ) {
			return;
		}

		$is_editor = in_array( $hook, array( 'post.php', 'post-new.php' ), true ) && 'post' === $screen->base;
		$is_list   = 'edit.php' === $hook && 'edit' === $screen->base;

		if ( ! $is_editor && ! $is_list ) {
			return;
		}

		wp_register_script(
			'wpu-date-utils',
			WPU_URL . 'assets/js/date-utils.js',
			array(),
			WPU_VERSION,
			true
		);

		if ( $is_editor ) {
			wp_enqueue_style( 'wpu-editor', WPU_URL . 'assets/css/unpublisher.css', array(), WPU_VERSION );
			wp_enqueue_script(
				'wpu-editor',
				WPU_URL . 'assets/js/unpublisher.js',
				array( 'jquery', 'wpu-date-utils' ),
				WPU_VERSION,
				true
			);
			wp_localize_script( 'wpu-editor', 'wpuAdmin', $this->editor_data() );
			return;
		}

		wp_enqueue_style( 'wpu-bulk-edit', WPU_URL . 'assets/css/bulk-edit.css', array(), WPU_VERSION );
		wp_enqueue_script(
			'wpu-bulk-edit',
			WPU_URL . 'assets/js/bulk-edit.js',
			array( 'jquery', 'wpu-date-utils', 'inline-edit-post' ),
			WPU_VERSION,
			true
		);
		wp_localize_script( 'wpu-bulk-edit', 'wpuBulk', $this->bulk_data() );
	}

	/**
	 * Data shared by both scripts for date validation and formatting.
	 *
	 * @return array<string,mixed>
	 */
	private function common_data(): array {
		$now = ( new \DateTimeImmutable( '@' . $this->clock->now() ) )->setTimezone( $this->clock->timezone() );

		return array(
			'monthNames' => array_values( Months::names() ),
			'weekdays'   => Months::weekdays(),
			'dateFormat' => get_option( 'date_format' ),
			'timeFormat' => get_option( 'time_format' ),
			'atLabel'    => __( 'at', 'woocommerce-product-unpublisher' ),
			'now'        => array_map( 'intval', explode( ',', $now->format( 'Y,n,j,G,i' ) ) ),
			'invalid'    => __( 'Please enter a valid date and time.', 'woocommerce-product-unpublisher' ),
			'past'       => __( 'Choose a date and time in the future.', 'woocommerce-product-unpublisher' ),
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	private function editor_data(): array {
		return array_merge(
			$this->common_data(),
			array(
				'before'         => __( 'Unpublishing must be later than scheduled publication.', 'woocommerce-product-unpublisher' ),
				'confirmRemove'  => __( 'Remove the unpublishing schedule? The product will stay published. The change is applied when you click Update.', 'woocommerce-product-unpublisher' ),
				'unpublishLabel' => __( 'Unpublish on:', 'woocommerce-product-unpublisher' ),
				'removalPending' => __( 'Schedule will be removed when you update.', 'woocommerce-product-unpublisher' ),
				'notScheduled'   => __( 'Not scheduled', 'woocommerce-product-unpublisher' ),
			)
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	private function bulk_data(): array {
		return array_merge(
			$this->common_data(),
			array(
				/* translators: %d: number of products. */
				'confirmNowOne'      => __( 'Unpublish %d selected published product now? It will immediately become a Draft, will no longer be visible in your store, and any scheduled unpublishing for it will be removed.', 'woocommerce-product-unpublisher' ),
				/* translators: %d: number of products. */
				'confirmNowMany'     => __( 'Unpublish %d selected published products now? They will immediately become Drafts, will no longer be visible in your store, and any scheduled unpublishing for them will be removed.', 'woocommerce-product-unpublisher' ),
				/* translators: %d: number of products that are not published. */
				'confirmNowSkipped'  => __( '%d selected product is not published and will be left unchanged.', 'woocommerce-product-unpublisher' ),
				/* translators: %d: number of products that are not published. */
				'confirmNowSkippedMany' => __( '%d selected products are not published and will be left unchanged.', 'woocommerce-product-unpublisher' ),
				'nothingToUnpublish' => __( 'None of the selected products are published, so there is nothing to unpublish.', 'woocommerce-product-unpublisher' ),
				/* translators: %d: number of products. */
				'confirmRemoveOne'   => __( 'Remove the scheduled unpublishing from %d selected product? This deletes its scheduled date, its cron event and any retry information. The product stays published.', 'woocommerce-product-unpublisher' ),
				/* translators: %d: number of products. */
				'confirmRemoveMany'  => __( 'Remove the scheduled unpublishing from %d selected products? This deletes their scheduled dates, their cron events and any retry information. The products stay published.', 'woocommerce-product-unpublisher' ),
			)
		);
	}
}
