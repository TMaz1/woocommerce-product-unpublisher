<?php

use ProductUnpublisher\Admin\BulkEditFields;
use ProductUnpublisher\Admin\DateDisplay;
use ProductUnpublisher\Admin\DateParts;
use ProductUnpublisher\Admin\EditorRow;
use ProductUnpublisher\Admin\ListColumn;
use ProductUnpublisher\Config;
use ProductUnpublisher\Diagnostics\OptionFailureLog;
use ProductUnpublisher\Schedule\RetryPolicy;
use ProductUnpublisher\Tests\Integration\ProductTestCase;

final class AdminUiIntegrationTest extends ProductTestCase {

	public function set_up(): void {
		parent::set_up();

		$this->login_as( 'administrator' );
	}

	private function column(): ListColumn {
		return new ListColumn(
			$this->services->schedules,
			$this->services->products,
			new RetryPolicy(),
			new DateDisplay( $this->services->clock ),
			$this->services->clock
		);
	}

	public function test_bulk_edit_controls_contain_all_four_choices_and_a_nonce_and_render_once(): void {
		$fields = new BulkEditFields( new DateParts( $this->services->clock ), $this->services->clock );

		ob_start();
		$fields->render( ListColumn::COLUMN, 'product' );
		$fields->render( 'name', 'product' );
		$html = ob_get_clean();

		foreach ( array( 'none', 'schedule', 'remove', 'unpublish_now' ) as $value ) {
			$this->assertSame( 1, substr_count( $html, 'name="wpu_bulk_action" value="' . $value . '"' ), $value );
		}

		$this->assertSame( 1, substr_count( $html, 'name="wpu_bulk_nonce"' ) );
		$this->assertSame( 1, substr_count( $html, 'id="wpu-bulk-date-fields"' ) );
		$this->assertStringContainsString( 'name="wpu_bulk_mm"', $html );
		$this->assertStringNotContainsString( '_wp_http_referer', $html );
		$this->assertMatchesRegularExpression( '/value="none"\s+checked/', $html );
	}

	public function test_bulk_edit_controls_default_to_a_future_date(): void {
		$fields = new BulkEditFields( new DateParts( $this->services->clock ), $this->services->clock );

		ob_start();
		$fields->render( ListColumn::COLUMN, 'product' );
		$html = ob_get_clean();

		preg_match( '/name="wpu_bulk_aa" value="(\d{4})"/', $html, $year );
		$this->assertGreaterThanOrEqual( (int) wp_date( 'Y' ), (int) $year[1] );
	}

	public function test_bulk_edit_controls_are_not_rendered_for_other_post_types_columns_or_users(): void {
		$fields = new BulkEditFields( new DateParts( $this->services->clock ), $this->services->clock );

		ob_start();
		$fields->render( 'name', 'post' );
		$fields->render( 'sku', 'product' );
		$this->assertSame( '', ob_get_clean() );

		$this->login_as( 'subscriber' );
		ob_start();
		$fields->render( 'name', 'product' );
		$this->assertSame( '', ob_get_clean() );
	}

	public function test_editor_row_renders_the_current_schedule_and_a_nonce(): void {
		$id = $this->create_product();
		$at = $this->future_minute( 3 );
		$this->services->schedules->replace( $id, $at );

		$row = new EditorRow( $this->services->schedules, new DateParts( $this->services->clock ), new DateDisplay( $this->services->clock ), $this->services->clock );

		ob_start();
		$row->render( get_post( $id ) );
		$html = ob_get_clean();

		$this->assertStringContainsString( 'id="wpu-publish-row"', $html );
		$this->assertStringContainsString( 'name="wpu_nonce"', $html );
		$this->assertStringContainsString( 'name="wpu_aa" value="' . wp_date( 'Y', $at, wp_timezone() ) . '"', $html );
		$this->assertStringContainsString( 'Unpublish on:', $html );
		$this->assertStringNotContainsString( 'id="wpu-remove" hidden', $html );
	}

	public function test_editor_row_marks_overdue_schedules_and_hides_remove_when_unscheduled(): void {
		$id = $this->create_product();
		update_post_meta( $id, Config::META_KEY, wp_date( 'Y-m-d H:i:s', time() - 3600, wp_timezone() ) );

		$row = new EditorRow( $this->services->schedules, new DateParts( $this->services->clock ), new DateDisplay( $this->services->clock ), $this->services->clock );

		ob_start();
		$row->render( get_post( $id ) );
		$this->assertStringContainsString( 'Unpublishing overdue:', ob_get_clean() );

		$other = $this->create_product();
		ob_start();
		$row->render( get_post( $other ) );
		$html = ob_get_clean();
		$this->assertStringContainsString( 'Not scheduled', $html );
		$this->assertStringContainsString( 'id="wpu-remove" hidden', $html );
	}

	public function test_editor_row_is_not_rendered_for_other_post_types(): void {
		$row = new EditorRow( $this->services->schedules, new DateParts( $this->services->clock ), new DateDisplay( $this->services->clock ), $this->services->clock );

		ob_start();
		$row->render( get_post( self::factory()->post->create() ) );
		$this->assertSame( '', ob_get_clean() );
	}

	public function test_list_column_is_inserted_before_date(): void {
		$columns = $this->column()->add_column( array( 'cb' => '', 'name' => 'Name', 'date' => 'Date' ) );

		$this->assertSame( array( 'cb', 'name', ListColumn::COLUMN, 'date' ), array_keys( $columns ) );
		$this->assertSame( array( 'cb', 'name', ListColumn::COLUMN ), array_keys( $this->column()->add_column( array( 'cb' => '', 'name' => 'Name' ) ) ) );
	}

	public function test_list_column_output(): void {
		$none = $this->create_product();
		$id   = $this->create_product();
		$this->services->schedules->replace( $id, $this->future_minute() );

		ob_start();
		$this->column()->render( ListColumn::COLUMN, $none );
		$this->assertSame( '&mdash;', ob_get_clean() );

		ob_start();
		$this->column()->render( ListColumn::COLUMN, $id );
		$html = ob_get_clean();
		$this->assertStringNotContainsString( 'Overdue', $html );
		$this->assertNotSame( '', $html );

		ob_start();
		$this->column()->render( 'sku', $id );
		$this->assertSame( '', ob_get_clean() );
	}

	public function test_list_column_flags_overdue_and_exhausted_retries(): void {
		$id = $this->create_product();
		update_post_meta( $id, Config::META_KEY, wp_date( 'Y-m-d H:i:s', time() - 3600, wp_timezone() ) );
		update_post_meta( $id, Config::ATTEMPTS_META, Config::MAX_ATTEMPTS );

		ob_start();
		$this->column()->render( ListColumn::COLUMN, $id );
		$html = ob_get_clean();

		$this->assertStringContainsString( 'Overdue', $html );
		$this->assertStringContainsString( 'Automatic retries stopped', $html );
	}

	public function test_failure_log_is_capped_and_actionable_entries_are_filtered(): void {
		$log = new OptionFailureLog();

		for ( $i = 1; $i <= Config::FAILURE_LOG_LIMIT + 10; $i++ ) {
			$log->record( $i, 'update_error', 1 );
		}

		$this->assertCount( Config::FAILURE_LOG_LIMIT, $log->entries() );
		$this->assertSame( array(), $log->actionable( 0 ), 'plain retries are not actionable' );

		$log->record( 999, 'cleanup_failed' );
		$log->record( 998, 'retries_exhausted', 5 );

		$this->assertSame( array( 999, 998 ), array_column( $log->actionable( time() - 60 ), 'product_id' ) );
		$this->assertSame( array(), $log->actionable( time() + 60 ), 'old entries age out' );

		$log->clear();
		$this->assertSame( array(), $log->entries() );
	}
}
