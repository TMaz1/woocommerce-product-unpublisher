<?php
/**
 * Localised month names.
 *
 * @package ProductUnpublisher
 */

namespace ProductUnpublisher\Admin;

defined( 'ABSPATH' ) || exit;

final class Months {

	/**
	 * Month names keyed by two-digit number ("01" => "January").
	 *
	 * @return array<string,string>
	 */
	public static function names(): array {
		return array(
			'01' => __( 'January', 'woocommerce-product-unpublisher' ),
			'02' => __( 'February', 'woocommerce-product-unpublisher' ),
			'03' => __( 'March', 'woocommerce-product-unpublisher' ),
			'04' => __( 'April', 'woocommerce-product-unpublisher' ),
			'05' => __( 'May', 'woocommerce-product-unpublisher' ),
			'06' => __( 'June', 'woocommerce-product-unpublisher' ),
			'07' => __( 'July', 'woocommerce-product-unpublisher' ),
			'08' => __( 'August', 'woocommerce-product-unpublisher' ),
			'09' => __( 'September', 'woocommerce-product-unpublisher' ),
			'10' => __( 'October', 'woocommerce-product-unpublisher' ),
			'11' => __( 'November', 'woocommerce-product-unpublisher' ),
			'12' => __( 'December', 'woocommerce-product-unpublisher' ),
		);
	}

	/**
	 * Weekday names, Sunday first.
	 *
	 * @return string[]
	 */
	public static function weekdays(): array {
		return array(
			__( 'Sunday', 'woocommerce-product-unpublisher' ),
			__( 'Monday', 'woocommerce-product-unpublisher' ),
			__( 'Tuesday', 'woocommerce-product-unpublisher' ),
			__( 'Wednesday', 'woocommerce-product-unpublisher' ),
			__( 'Thursday', 'woocommerce-product-unpublisher' ),
			__( 'Friday', 'woocommerce-product-unpublisher' ),
			__( 'Saturday', 'woocommerce-product-unpublisher' ),
		);
	}
}
