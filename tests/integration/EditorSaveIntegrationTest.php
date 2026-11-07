<?php

use ProductUnpublisher\Admin\EditorRow;
use ProductUnpublisher\Config;
use ProductUnpublisher\Tests\Integration\ProductTestCase;

final class EditorSaveIntegrationTest extends ProductTestCase {

	public function set_up(): void {
		parent::set_up();

		$this->login_as( 'administrator' );
		$this->services->status_listener()->register();
		$this->services->editor_handler()->register();
	}

	/** @param array<string,string> $fields */
	private function post( string $action, array $fields = array(), bool $with_nonce = true ): void {
		$_POST = array_merge(
			array( 'wpu_action' => $action ),
			$with_nonce ? array( EditorRow::NONCE_FIELD => wp_create_nonce( EditorRow::NONCE_ACTION ) ) : array(),
			$fields
		);
	}

	private function save( int $id ): void {
		wp_update_post( array( 'ID' => $id, 'post_title' => get_the_title( $id ) ) );
	}

	private function notice( int $id ): ?array {
		return $this->services->notices->pull( $id );
	}

	public function test_saving_a_valid_date_schedules_the_product(): void {
		$id = $this->create_product();
		$at = $this->future_minute( 3 );

		$this->post( 'save', $this->date_fields( $at, 'wpu_' ) );
		$this->save( $id );

		$this->assertSame( $at, $this->services->schedules->get_timestamp( $id ) );
		$this->assertSame( 1, $this->event_count( $id ) );
		$this->assertSame( 'success', $this->notice( $id )['type'] );
	}

	public function test_an_invalid_date_keeps_the_previous_schedule_and_reports_an_error(): void {
		$id  = $this->create_product();
		$old = $this->future_minute( 2 );
		$this->services->schedules->replace( $id, $old );

		$this->post( 'save', array_merge( $this->date_fields( $this->future_minute(), 'wpu_' ), array( 'wpu_jj' => '31', 'wpu_mm' => '02' ) ) );
		$this->save( $id );

		$this->assertSame( $old, $this->services->schedules->get_timestamp( $id ) );
		$this->assertSame( 1, $this->event_count( $id ) );
		$this->assertSame( 'error', $this->notice( $id )['type'] );
	}

	public function test_a_past_date_is_rejected(): void {
		$id = $this->create_product();

		$this->post( 'save', $this->date_fields( time() - DAY_IN_SECONDS, 'wpu_' ) );
		$this->save( $id );

		$this->assertSame( 0, $this->event_count( $id ) );
		$this->assertSame( 'error', $this->notice( $id )['type'] );
	}

	public function test_remove_deletes_the_schedule(): void {
		$id = $this->create_product();
		$this->services->schedules->replace( $id, $this->future_minute() );

		$this->post( 'remove' );
		$this->save( $id );

		$this->assertSame( 0, $this->event_count( $id ) );
		$this->assertFalse( metadata_exists( 'post', $id, Config::META_KEY ) );
		$this->assertSame( 'success', $this->notice( $id )['type'] );
	}

	public function test_an_empty_action_leaves_the_schedule_alone(): void {
		$id = $this->create_product();
		$at = $this->future_minute();
		$this->services->schedules->replace( $id, $at );

		$this->post( '' );
		$this->save( $id );

		$this->assertSame( $at, $this->services->schedules->get_timestamp( $id ) );
		$this->assertNull( $this->notice( $id ) );
	}

	public function test_requests_without_a_valid_nonce_are_ignored(): void {
		$id = $this->create_product();

		$this->post( 'save', $this->date_fields( $this->future_minute(), 'wpu_' ), false );
		$this->save( $id );
		$this->assertSame( 0, $this->event_count( $id ) );

		$this->post( 'save', array_merge( $this->date_fields( $this->future_minute(), 'wpu_' ), array( EditorRow::NONCE_FIELD => 'bad' ) ) );
		$this->save( $id );
		$this->assertSame( 0, $this->event_count( $id ) );
	}

	public function test_draft_products_cannot_receive_a_schedule(): void {
		$id = $this->create_product( 'draft' );

		$this->post( 'save', $this->date_fields( $this->future_minute(), 'wpu_' ) );
		$this->save( $id );

		$this->assertSame( 0, $this->event_count( $id ) );
		$this->assertSame( 'error', $this->notice( $id )['type'] );
	}

	public function test_unpublishing_must_come_after_a_future_products_publication(): void {
		$publish_at = time() + 5 * DAY_IN_SECONDS;
		$id         = $this->create_product( 'future', array( 'post_date' => gmdate( 'Y-m-d H:i:s', $publish_at ), 'post_date_gmt' => gmdate( 'Y-m-d H:i:s', $publish_at ) ) );

		$this->post( 'save', $this->date_fields( $this->future_minute( 2 ), 'wpu_' ) );
		$this->save( $id );
		$this->assertSame( 0, $this->event_count( $id ) );

		$this->post( 'save', $this->date_fields( $this->future_minute( 8 ), 'wpu_' ) );
		$this->save( $id );
		$this->assertSame( 1, $this->event_count( $id ) );
	}

	public function test_users_who_cannot_edit_the_product_cannot_schedule_it(): void {
		$id = $this->create_product();

		$this->login_as( 'subscriber' );
		$this->post( 'save', $this->date_fields( $this->future_minute(), 'wpu_' ) );
		$this->save( $id );

		$this->assertSame( 0, $this->event_count( $id ) );
	}
}
