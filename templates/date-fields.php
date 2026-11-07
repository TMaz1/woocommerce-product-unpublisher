<?php
/**
 * Month / day / year at hour : minute fields (core Publish box layout).
 *
 * @var string                $prefix Field name prefix, e.g. "wpu_" or "wpu_bulk_".
 * @var array<string,string>  $parts  Keys mm, jj, aa, hh, mn.
 * @var array<string,string>  $months Month names keyed by two-digit number.
 *
 * @package ProductUnpublisher
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="timestamp-wrap">
	<label>
		<span class="screen-reader-text"><?php esc_html_e( 'Month', 'woocommerce-product-unpublisher' ); ?></span>
		<select id="<?php echo esc_attr( $prefix . 'mm' ); ?>" name="<?php echo esc_attr( $prefix . 'mm' ); ?>" class="form-required">
			<?php foreach ( $months as $number => $month ) : ?>
				<option value="<?php echo esc_attr( $number ); ?>" <?php selected( $parts['mm'], $number ); ?>>
					<?php echo esc_html( $number . '-' . mb_substr( $month, 0, 3 ) ); ?>
				</option>
			<?php endforeach; ?>
		</select>
	</label>

	<label>
		<span class="screen-reader-text"><?php esc_html_e( 'Day', 'woocommerce-product-unpublisher' ); ?></span>
		<input type="text" id="<?php echo esc_attr( $prefix . 'jj' ); ?>" name="<?php echo esc_attr( $prefix . 'jj' ); ?>" value="<?php echo esc_attr( $parts['jj'] ); ?>" size="2" maxlength="2" autocomplete="off" class="form-required" inputmode="numeric" />
	</label>,

	<label>
		<span class="screen-reader-text"><?php esc_html_e( 'Year', 'woocommerce-product-unpublisher' ); ?></span>
		<input type="text" id="<?php echo esc_attr( $prefix . 'aa' ); ?>" name="<?php echo esc_attr( $prefix . 'aa' ); ?>" value="<?php echo esc_attr( $parts['aa'] ); ?>" size="4" maxlength="4" autocomplete="off" class="form-required" inputmode="numeric" />
	</label>

	<?php esc_html_e( 'at', 'woocommerce-product-unpublisher' ); ?>

	<label>
		<span class="screen-reader-text"><?php esc_html_e( 'Hour', 'woocommerce-product-unpublisher' ); ?></span>
		<input type="text" id="<?php echo esc_attr( $prefix . 'hh' ); ?>" name="<?php echo esc_attr( $prefix . 'hh' ); ?>" value="<?php echo esc_attr( $parts['hh'] ); ?>" size="2" maxlength="2" autocomplete="off" class="form-required" inputmode="numeric" />
	</label>:<label>
		<span class="screen-reader-text"><?php esc_html_e( 'Minute', 'woocommerce-product-unpublisher' ); ?></span>
		<input type="text" id="<?php echo esc_attr( $prefix . 'mn' ); ?>" name="<?php echo esc_attr( $prefix . 'mn' ); ?>" value="<?php echo esc_attr( $parts['mn'] ); ?>" size="2" maxlength="2" autocomplete="off" class="form-required" inputmode="numeric" />
	</label>
</div>
