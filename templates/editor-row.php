<?php
/**
 * Unpublishing row in the classic product editor Publish box.
 *
 * @var int|null              $timestamp Scheduled timestamp, if any.
 * @var string                $label     Row label.
 * @var string                $current   Current value text.
 * @var array<string,string>  $parts     Date field values.
 * @var array<string,string>  $months    Month names.
 *
 * @package ProductUnpublisher
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="misc-pub-section curtime misc-pub-curtime" id="wpu-publish-row">
	<span class="dashicons dashicons-calendar-alt" aria-hidden="true"></span>

	<span id="wpu-timestamp">
		<span id="wpu-label"><?php echo esc_html( $label ); ?></span>
		<strong id="wpu-current-value"><?php echo esc_html( $current ); ?></strong>
	</span>

	<a href="#wpu-edit" class="edit-timestamp hide-if-no-js" id="wpu-edit-toggle" role="button" aria-expanded="false" aria-controls="wpu-edit-panel">
		<span aria-hidden="true"><?php esc_html_e( 'Edit', 'woocommerce-product-unpublisher' ); ?></span>
		<span class="screen-reader-text"><?php esc_html_e( 'Edit unpublishing date and time', 'woocommerce-product-unpublisher' ); ?></span>
	</a>

	<input type="hidden" name="wpu_action" id="wpu_action" value="" />

	<fieldset id="wpu-edit-panel" hidden>
		<legend class="screen-reader-text"><?php esc_html_e( 'Unpublishing date and time', 'woocommerce-product-unpublisher' ); ?></legend>

		<?php
		\ProductUnpublisher\Admin\View::render(
			'date-fields',
			array(
				'prefix' => 'wpu_',
				'parts'  => $parts,
				'months' => $months,
			)
		);
		?>

		<p id="wpu-error" class="wpu-error" role="alert" hidden></p>

		<p>
			<a href="#wpu-save" class="save-timestamp button" id="wpu-ok"><?php esc_html_e( 'OK', 'woocommerce-product-unpublisher' ); ?></a>
			<a href="#wpu-cancel" class="cancel-timestamp button-cancel" id="wpu-cancel"><?php esc_html_e( 'Cancel', 'woocommerce-product-unpublisher' ); ?></a>
			<a href="#wpu-remove" class="wpu-remove" id="wpu-remove" <?php echo $timestamp ? '' : 'hidden'; ?>><?php esc_html_e( 'Remove schedule', 'woocommerce-product-unpublisher' ); ?></a>
		</p>
	</fieldset>
</div>
