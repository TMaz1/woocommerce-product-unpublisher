<?php
/**
 * Applies the Bulk Edit "Unpublishing schedule" choice to every selected product.
 *
 * The Products list submits Bulk Edit with GET, so request data is read from $_REQUEST. WordPress fires
 * save_post_product once per selected product, so the request is parsed once per call (cheap) and each
 * product is processed at most once per request. Outcomes are collected and reported after the redirect.
 *
 * @package ProductUnpublisher
 */

namespace ProductUnpublisher\Admin;

use ProductUnpublisher\Config;
use ProductUnpublisher\Product\ProductGateway;
use ProductUnpublisher\Product\ProductUnpublisher;
use ProductUnpublisher\Product\UnpublishOutcome;
use ProductUnpublisher\Schedule\ScheduleManager;
use ProductUnpublisher\Time\InvalidScheduleDateException;
use ProductUnpublisher\Time\ScheduleDateParser;

defined( 'ABSPATH' ) || exit;

final class BulkEditHandler {

	public const NONCE_ACTION = 'wpu_bulk_edit_schedule';
	public const NONCE_FIELD  = 'wpu_bulk_nonce';
	public const ACTION_FIELD = 'wpu_bulk_action';

	private BulkResult $result;

	/** @var array<int,true> Products already handled in this request. */
	private array $handled = array();

	public function __construct(
		private ScheduleManager $schedules,
		private ProductGateway $products,
		private ProductUnpublisher $unpublisher,
		private ScheduleDateParser $parser,
		private BulkResultStore $store
	) {
		$this->result = new BulkResult();
	}

	public function register(): void {
		add_action( 'save_post_product', array( $this, 'handle' ), 31, 3 );
		add_filter( 'wp_redirect', array( $this, 'append_results_to_redirect' ), 10, 2 );
	}

	/**
	 * @param int|string $post_id Post ID.
	 * @param mixed      $post    Post object.
	 * @param mixed      $update  Whether this is an update.
	 */
	public function handle( $post_id, $post = null, $update = false ): void {
		$post_id = (int) $post_id;

		if (
			! $post instanceof \WP_Post ||
			Config::POST_TYPE !== $post->post_type ||
			isset( $this->handled[ $post_id ] ) ||
			wp_is_post_revision( $post_id ) ||
			wp_is_post_autosave( $post_id )
		) {
			return;
		}

		$request = $this->read_request();

		if ( null === $request || BulkAction::NONE === $request->action ) {
			return;
		}

		// Mark first: unpublishing re-enters save_post_product for the same product.
		$this->handled[ $post_id ] = true;

		$this->result->set_action( $request->action );
		$this->result->add( $this->process( $post_id, $request ) );
	}

	/**
	 * Add the results token to the redirect WordPress performs after a bulk edit.
	 *
	 * @param mixed $location Redirect target.
	 * @param mixed $status   HTTP status.
	 * @return mixed
	 */
	public function append_results_to_redirect( $location, $status = 302 ) {
		if ( $this->result->is_empty() || ! is_string( $location ) || false === strpos( $location, 'edit.php' ) ) {
			return $location;
		}

		$token        = $this->store->save( get_current_user_id(), $this->result );
		$this->result = new BulkResult();

		return add_query_arg( BulkResultStore::QUERY_ARG, $token, $location );
	}

	/**
	 * Results collected so far in this request (exposed for tests and extensions).
	 */
	public function result(): BulkResult {
		return $this->result;
	}

	/**
	 * Parse and authenticate the bulk request, or return null when this is not one of ours.
	 */
	private function read_request(): ?BulkRequest {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Nonce verified below.
		if (
			! isset( $_REQUEST['bulk_edit'], $_REQUEST[ self::NONCE_FIELD ], $_REQUEST[ self::ACTION_FIELD ] ) ||
			! is_string( $_REQUEST[ self::NONCE_FIELD ] ) ||
			! is_string( $_REQUEST[ self::ACTION_FIELD ] )
		) {
			return null;
		}

		$request = wp_unslash( $_REQUEST );
		// phpcs:enable

		if ( ! wp_verify_nonce( sanitize_text_field( $request[ self::NONCE_FIELD ] ), self::NONCE_ACTION ) ) {
			return null;
		}

		$action = sanitize_key( $request[ self::ACTION_FIELD ] );

		if ( ! BulkAction::is_valid( $action ) ) {
			return null;
		}

		if ( BulkAction::SCHEDULE !== $action ) {
			return new BulkRequest( $action );
		}

		try {
			return new BulkRequest( $action, $this->parser->from_request( $request, 'wpu_bulk_' ) );
		} catch ( InvalidScheduleDateException $e ) {
			$reason = InvalidScheduleDateException::PAST === $e->reason() ? 'past_date' : 'invalid_date';

			return new BulkRequest( $action, null, $reason );
		}
	}

	private function process( int $product_id, BulkRequest $request ): BulkEntry {
		if ( ! current_user_can( 'edit_post', $product_id ) ) {
			return BulkEntry::failed( $product_id, 'no_permission' );
		}

		switch ( $request->action ) {
			case BulkAction::SCHEDULE:
				return $this->schedule( $product_id, $request );

			case BulkAction::REMOVE:
				return $this->remove( $product_id );

			case BulkAction::UNPUBLISH_NOW:
				return $this->unpublish_now( $product_id );
		}

		return BulkEntry::failed( $product_id, 'unknown_action' );
	}

	private function schedule( int $product_id, BulkRequest $request ): BulkEntry {
		// Only published products receive an active schedule.
		if ( 'publish' !== $this->products->status( $product_id ) ) {
			return BulkEntry::skipped( $product_id, 'not_published' );
		}

		if ( null !== $request->date_error || null === $request->timestamp ) {
			return BulkEntry::failed( $product_id, $request->date_error ?? 'invalid_date' );
		}

		return $this->schedules->replace( $product_id, $request->timestamp )
			? BulkEntry::success( $product_id )
			: BulkEntry::failed( $product_id, 'schedule_failed' );
	}

	private function remove( int $product_id ): BulkEntry {
		if ( ! $this->schedules->has_schedule( $product_id ) ) {
			return BulkEntry::skipped( $product_id, 'no_schedule' );
		}

		return $this->schedules->remove( $product_id )
			? BulkEntry::success( $product_id )
			: BulkEntry::failed( $product_id, 'remove_failed' );
	}

	private function unpublish_now( int $product_id ): BulkEntry {
		$outcome = $this->unpublisher->unpublish_now( $product_id );

		switch ( $outcome->status ) {
			case UnpublishOutcome::UNPUBLISHED:
				return BulkEntry::success( $product_id );

			case UnpublishOutcome::CLEANUP_FAILED:
				return BulkEntry::partial( $product_id, 'cleanup_failed' );

			case UnpublishOutcome::NOT_PUBLISHED:
				return BulkEntry::skipped( $product_id, 'not_published' );
		}

		return BulkEntry::failed( $product_id, '' !== $outcome->reason ? $outcome->reason : 'update_error' );
	}
}
