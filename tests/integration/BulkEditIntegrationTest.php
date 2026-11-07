<?php

use ProductUnpublisher\Admin\BulkAction;
use ProductUnpublisher\Admin\BulkEditHandler;
use ProductUnpublisher\Admin\BulkEntry;
use ProductUnpublisher\Admin\BulkNotice;
use ProductUnpublisher\Admin\BulkResultStore;
use ProductUnpublisher\Config;
use ProductUnpublisher\Tests\Integration\ProductTestCase;
use ProductUnpublisher\Tests\Integration\Services;

/**
 * Drives the bulk handler the way edit.php does: GET request fields, then one product update per selected product.
 */
final class BulkEditIntegrationTest extends ProductTestCase {

	private BulkEditHandler $handler;

	public function set_up(): void {
		parent::set_up();

		$this->login_as( 'administrator' );

		$this->services->status_listener()->register();
		$this->handler = $this->services->bulk_handler();
		$this->handler->register();
	}

	/**
	 * @param array<string,string> $extra Extra request fields (date fields).
	 */
	private function submit_bulk( string $action, array $extra = array() ): void {
		$fields = array_merge(
			array(
				'bulk_edit'                       => 'Update',
				BulkEditHandler::NONCE_FIELD      => wp_create_nonce( BulkEditHandler::NONCE_ACTION ),
				BulkEditHandler::ACTION_FIELD     => $action,
			),
			$extra
		);

		// Bulk Edit is a GET form on edit.php.
		$_GET     = $fields;
		$_REQUEST = $fields;
	}

	/** Mimic bulk_edit_posts(): update each selected product. */
	private function apply_to( int ...$ids ): void {
		foreach ( $ids as $id ) {
			wp_update_post( array( 'ID' => $id, 'post_title' => get_the_title( $id ) ) );
		}
	}

	public function test_no_change_touches_nothing_and_reports_nothing(): void {
		$id = $this->create_product();
		$this->services->schedules->replace( $id, $this->future_minute() );
		$this->submit_bulk( BulkAction::NONE );

		$this->apply_to( $id );

		$this->assertSame( 1, $this->event_count( $id ) );
		$this->assertTrue( $this->handler->result()->is_empty() );
	}

	public function test_schedule_applies_the_same_time_to_every_published_product_with_its_own_event(): void {
		$a  = $this->create_product();
		$b  = $this->create_product();
		$at = $this->future_minute( 3 );

		$this->submit_bulk( BulkAction::SCHEDULE, $this->date_fields( $at, 'wpu_bulk_' ) );
		$this->apply_to( $a, $b );

		foreach ( array( $a, $b ) as $id ) {
			$this->assertSame( $at, $this->services->schedules->get_timestamp( $id ) );
			$this->assertSame( $at, wp_next_scheduled( Config::CRON_HOOK, array( $id ) ) );
			$this->assertSame( 1, $this->event_count( $id ) );
		}

		$this->assertSame( 2, $this->handler->result()->count( BulkEntry::SUCCESS ) );
		$this->assertSame( BulkAction::SCHEDULE, $this->handler->result()->action() );
	}

	public function test_schedule_replaces_existing_schedules_without_duplicates(): void {
		$id = $this->create_product();
		$this->services->schedules->replace( $id, $this->future_minute( 2 ) );
		$new = $this->future_minute( 5 );

		$this->submit_bulk( BulkAction::SCHEDULE, $this->date_fields( $new, 'wpu_bulk_' ) );
		$this->apply_to( $id );

		$this->assertSame( 1, $this->event_count( $id ) );
		$this->assertSame( $new, $this->services->schedules->get_timestamp( $id ) );
	}

	public function test_schedule_skips_products_that_are_not_published(): void {
		$published = $this->create_product();
		$draft     = $this->create_product( 'draft' );
		$private   = $this->create_product( 'private' );

		$this->submit_bulk( BulkAction::SCHEDULE, $this->date_fields( $this->future_minute(), 'wpu_bulk_' ) );
		$this->apply_to( $published, $draft, $private );

		$this->assertSame( 1, $this->event_count( $published ) );
		$this->assertSame( 0, $this->event_count( $draft ) );
		$this->assertSame( 0, $this->event_count( $private ) );
		$this->assertFalse( metadata_exists( 'post', $draft, Config::META_KEY ) );
		$this->assertSame( 1, $this->handler->result()->count( BulkEntry::SUCCESS ) );
		$this->assertSame( 2, $this->handler->result()->count( BulkEntry::SKIPPED ) );
	}

	public function test_invalid_and_past_dates_are_rejected_on_the_server_and_reported_as_failures(): void {
		$id  = $this->create_product();
		$old = $this->future_minute( 2 );
		$this->services->schedules->replace( $id, $old );

		$bad = $this->date_fields( $this->future_minute(), 'wpu_bulk_' );

		// Impossible date.
		$this->submit_bulk( BulkAction::SCHEDULE, array_merge( $bad, array( 'wpu_bulk_mm' => '02', 'wpu_bulk_jj' => '30' ) ) );
		$this->apply_to( $id );
		$this->assertSame( 1, $this->handler->result()->count( BulkEntry::FAILED ) );
		$this->assertSame( 'invalid_date', $this->handler->result()->details()[0]->reason );
		$this->assertSame( $old, $this->services->schedules->get_timestamp( $id ), 'the previous schedule is untouched' );

		// Past date, using a fresh handler (each request handles a product once).
		remove_action( 'save_post_product', array( $this->handler, 'handle' ), 31 );
		$this->handler = $this->services->bulk_handler();
		$this->handler->register();
		$this->submit_bulk( BulkAction::SCHEDULE, $this->date_fields( time() - DAY_IN_SECONDS, 'wpu_bulk_' ) );
		$this->apply_to( $id );
		$this->assertSame( 'past_date', $this->handler->result()->details()[0]->reason );
		$this->assertSame( $old, $this->services->schedules->get_timestamp( $id ) );
	}

	public function test_incomplete_date_fields_are_rejected(): void {
		$id = $this->create_product();

		$this->submit_bulk( BulkAction::SCHEDULE, array( 'wpu_bulk_mm' => '12' ) );
		$this->apply_to( $id );

		$this->assertSame( 1, $this->handler->result()->count( BulkEntry::FAILED ) );
		$this->assertSame( 0, $this->event_count( $id ) );
	}

	public function test_remove_clears_metadata_event_and_retry_information_and_skips_unscheduled(): void {
		$scheduled   = $this->create_product();
		$unscheduled = $this->create_product();
		$this->services->schedules->replace( $scheduled, $this->future_minute() );
		update_post_meta( $scheduled, Config::ATTEMPTS_META, 2 );

		$this->submit_bulk( BulkAction::REMOVE );
		$this->apply_to( $scheduled, $unscheduled );

		$this->assertSame( 0, $this->event_count( $scheduled ) );
		$this->assertFalse( metadata_exists( 'post', $scheduled, Config::META_KEY ) );
		$this->assertFalse( metadata_exists( 'post', $scheduled, Config::ATTEMPTS_META ) );
		$this->assertSame( 'publish', get_post_status( $scheduled ), 'removing a schedule never changes the status' );
		$this->assertSame( 1, $this->handler->result()->count( BulkEntry::SUCCESS ) );
		$this->assertSame( 1, $this->handler->result()->count( BulkEntry::SKIPPED ) );
	}

	public function test_unpublish_now_moves_published_products_to_draft_and_reports_no_false_failures(): void {
		$scheduled = $this->create_product();
		$plain     = $this->create_product();
		$draft     = $this->create_product( 'draft' );
		$this->services->schedules->replace( $scheduled, $this->future_minute() );

		$this->submit_bulk( BulkAction::UNPUBLISH_NOW );
		$this->apply_to( $scheduled, $plain, $draft );

		$this->assertSame( 'draft', get_post_status( $scheduled ) );
		$this->assertSame( 'draft', get_post_status( $plain ) );
		$this->assertSame( 'draft', get_post_status( $draft ) );
		$this->assertSame( 0, $this->event_count( $scheduled ) );
		$this->assertFalse( metadata_exists( 'post', $scheduled, Config::META_KEY ) );

		// The nested status update re-enters save_post_product: it must not be counted again.
		$this->assertSame( 2, $this->handler->result()->count( BulkEntry::SUCCESS ) );
		$this->assertSame( 1, $this->handler->result()->count( BulkEntry::SKIPPED ) );
		$this->assertSame( 0, $this->handler->result()->count( BulkEntry::FAILED ) );
		$this->assertSame( 3, $this->handler->result()->total() );
	}

	public function test_unpublish_now_reports_a_partial_result_when_schedule_cleanup_fails(): void {
		$id = $this->create_product();
		$this->services->schedules->replace( $id, $this->future_minute() );

		// A gateway that cannot clear events once the product has been moved to Draft.
		$real     = new \ProductUnpublisher\Cron\WpCronGateway();
		$failing  = new class( $real ) implements \ProductUnpublisher\Cron\CronGateway {
			public bool $broken = false;

			public function __construct( private \ProductUnpublisher\Cron\WpCronGateway $real ) {}

			public function next_run( int $product_id ) {
				return $this->real->next_run( $product_id );
			}

			public function schedule( int $product_id, int $timestamp ): bool {
				return $this->real->schedule( $product_id, $timestamp );
			}

			public function clear( int $product_id ): bool {
				return $this->broken ? false : $this->real->clear( $product_id );
			}
		};
		$services = new Services( $failing );
		$failing->broken = true;

		remove_action( 'save_post_product', array( $this->handler, 'handle' ), 31 );
		$handler = $services->bulk_handler();
		$handler->register();
		$this->submit_bulk( BulkAction::UNPUBLISH_NOW );
		$this->apply_to( $id );

		$this->assertSame( 'draft', get_post_status( $id ) );
		$this->assertSame( 1, $handler->result()->count( BulkEntry::PARTIAL ) );
		$this->assertSame( 0, $handler->result()->count( BulkEntry::SUCCESS ) );
		$this->assertSame( 'cleanup_failed', $handler->result()->details()[0]->reason );

		$actionable = $services->failures->actionable( time() - HOUR_IN_SECONDS );
		$this->assertSame( $id, $actionable[0]['product_id'] );
		$this->assertSame( 'cleanup_failed', $actionable[0]['reason'] );
	}

	public function test_requests_without_a_valid_nonce_are_ignored(): void {
		$id = $this->create_product();

		$this->submit_bulk( BulkAction::UNPUBLISH_NOW );
		$_GET[ BulkEditHandler::NONCE_FIELD ]     = 'invalid';
		$_REQUEST[ BulkEditHandler::NONCE_FIELD ] = 'invalid';
		$this->apply_to( $id );

		$this->assertSame( 'publish', get_post_status( $id ) );
		$this->assertTrue( $this->handler->result()->is_empty() );
	}

	public function test_requests_that_are_not_bulk_edits_are_ignored(): void {
		$id = $this->create_product();

		$this->submit_bulk( BulkAction::UNPUBLISH_NOW );
		unset( $_REQUEST['bulk_edit'], $_GET['bulk_edit'] );
		$this->apply_to( $id );

		$this->assertSame( 'publish', get_post_status( $id ) );
	}

	public function test_unknown_actions_are_ignored(): void {
		$id = $this->create_product();

		$this->submit_bulk( 'delete_everything' );
		$this->apply_to( $id );

		$this->assertSame( 'publish', get_post_status( $id ) );
		$this->assertTrue( $this->handler->result()->is_empty() );
	}

	public function test_users_who_cannot_edit_the_product_are_refused(): void {
		$id = $this->create_product();

		$this->login_as( 'subscriber' );
		$this->submit_bulk( BulkAction::UNPUBLISH_NOW );
		$this->apply_to( $id );

		$this->assertSame( 'publish', get_post_status( $id ) );
		$this->assertSame( 1, $this->handler->result()->count( BulkEntry::FAILED ) );
		$this->assertSame( 'no_permission', $this->handler->result()->details()[0]->reason );
	}

	public function test_the_redirect_after_a_bulk_edit_carries_a_results_token_once(): void {
		$a = $this->create_product();
		$b = $this->create_product( 'draft' );

		$this->submit_bulk( BulkAction::UNPUBLISH_NOW );
		$this->apply_to( $a, $b );

		$target   = 'http://example.org/wp-admin/edit.php?post_type=product&updated=2';
		$redirect = apply_filters( 'wp_redirect', $target, 302 );

		$this->assertStringContainsString( BulkResultStore::QUERY_ARG . '=', $redirect );
		$this->assertStringContainsString( 'post_type=product', $redirect );

		parse_str( (string) wp_parse_url( $redirect, PHP_URL_QUERY ), $query );
		$result = $this->services->bulk_store->pull( get_current_user_id(), $query[ BulkResultStore::QUERY_ARG ] );

		$this->assertNotNull( $result );
		$this->assertSame( 1, $result->count( BulkEntry::SUCCESS ) );
		$this->assertSame( 1, $result->count( BulkEntry::SKIPPED ) );
		$this->assertNull( $this->services->bulk_store->pull( get_current_user_id(), $query[ BulkResultStore::QUERY_ARG ] ), 'a token can be read only once' );

		// A second redirect in the same request is left alone.
		$this->assertSame( $target, apply_filters( 'wp_redirect', $target, 302 ) );
	}

	public function test_redirects_elsewhere_and_empty_results_are_left_alone(): void {
		$this->assertSame( 'http://example.org/wp-login.php', apply_filters( 'wp_redirect', 'http://example.org/wp-login.php', 302 ) );

		$id = $this->create_product();
		$this->submit_bulk( BulkAction::UNPUBLISH_NOW );
		$this->apply_to( $id );

		$this->assertSame( 'http://example.org/wp-login.php', apply_filters( 'wp_redirect', 'http://example.org/wp-login.php', 302 ) );
	}

	public function test_result_tokens_are_validated_and_per_user(): void {
		$this->assertNull( $this->services->bulk_store->pull( 1, '../../etc' ) );
		$this->assertNull( $this->services->bulk_store->pull( 1, 'abcdefghijkl' ) );

		$result = new \ProductUnpublisher\Admin\BulkResult( BulkAction::REMOVE );
		$result->add( BulkEntry::success( 1 ) );
		$token = $this->services->bulk_store->save( 5, $result );

		$this->assertNull( $this->services->bulk_store->pull( 6, $token ), 'another user cannot read it' );
		$this->assertNotNull( $this->services->bulk_store->pull( 5, $token ) );
	}

	public function test_the_notice_describes_successes_skips_failures_and_partial_results(): void {
		$notice = new BulkNotice( $this->services->bulk_store );
		$id     = $this->create_product( 'publish', array( 'post_title' => 'Blue Mug' ) );

		$result = new \ProductUnpublisher\Admin\BulkResult( BulkAction::UNPUBLISH_NOW );
		$result->add( BulkEntry::success( $id ) );
		$result->add( BulkEntry::success( $id ) );
		$result->add( BulkEntry::skipped( $id, 'not_published' ) );
		$result->add( BulkEntry::partial( $id, 'cleanup_failed' ) );
		$result->add( BulkEntry::failed( $id, 'update_error' ) );

		$html = $notice->build_html( $result );

		$this->assertStringContainsString( '2 products unpublished and moved to Draft.', $html );
		$this->assertStringContainsString( '1 product was skipped and left unchanged.', $html );
		$this->assertStringContainsString( '1 product could not be processed.', $html );
		$this->assertStringContainsString( 'moved to Draft, but its unpublishing schedule data could not be fully removed', $html );
		$this->assertStringContainsString( 'Blue Mug: moved to Draft, but schedule cleanup failed', $html );
		$this->assertStringContainsString( 'notice-warning', $html );
	}

	public function test_notice_types(): void {
		$notice = new BulkNotice( $this->services->bulk_store );

		$ok = new \ProductUnpublisher\Admin\BulkResult();
		$ok->add( BulkEntry::success( 1 ) );
		$this->assertSame( 'success', $notice->notice_type( $ok ) );

		$bad = new \ProductUnpublisher\Admin\BulkResult();
		$bad->add( BulkEntry::failed( 1, 'x' ) );
		$this->assertSame( 'error', $notice->notice_type( $bad ) );

		$skipped = new \ProductUnpublisher\Admin\BulkResult();
		$skipped->add( BulkEntry::skipped( 1, 'not_published' ) );
		$this->assertSame( 'info', $notice->notice_type( $skipped ) );

		$mixed = new \ProductUnpublisher\Admin\BulkResult();
		$mixed->add( BulkEntry::success( 1 ) );
		$mixed->add( BulkEntry::failed( 2, 'x' ) );
		$this->assertSame( 'warning', $notice->notice_type( $mixed ) );
	}

	public function test_notice_output_escapes_product_titles(): void {
		$notice = new BulkNotice( $this->services->bulk_store );
		$id     = $this->create_product( 'publish', array( 'post_title' => '<script>alert(1)</script>' ) );

		$result = new \ProductUnpublisher\Admin\BulkResult( BulkAction::SCHEDULE );
		$result->add( BulkEntry::failed( $id, 'schedule_failed' ) );

		$this->assertStringNotContainsString( '<script>', $notice->build_html( $result ) );
	}
}
