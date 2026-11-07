<?php

use PHPUnit\Framework\TestCase;
use ProductUnpublisher\Admin\BulkAction;
use ProductUnpublisher\Admin\BulkEntry;
use ProductUnpublisher\Admin\BulkResult;

final class BulkResultTest extends TestCase {

	public function test_counts_every_outcome_exactly(): void {
		$result = new BulkResult( BulkAction::SCHEDULE );

		$result->add( BulkEntry::success( 1 ) );
		$result->add( BulkEntry::success( 2 ) );
		$result->add( BulkEntry::skipped( 3, 'not_published' ) );
		$result->add( BulkEntry::failed( 4, 'schedule_failed' ) );
		$result->add( BulkEntry::partial( 5, 'cleanup_failed' ) );

		$this->assertSame( 2, $result->count( BulkEntry::SUCCESS ) );
		$this->assertSame( 1, $result->count( BulkEntry::SKIPPED ) );
		$this->assertSame( 1, $result->count( BulkEntry::FAILED ) );
		$this->assertSame( 1, $result->count( BulkEntry::PARTIAL ) );
		$this->assertSame( 5, $result->total() );
		$this->assertFalse( $result->is_empty() );
	}

	public function test_only_non_successes_keep_details(): void {
		$result = new BulkResult();
		$result->add( BulkEntry::success( 1 ) );
		$result->add( BulkEntry::failed( 2, 'remove_failed' ) );

		$this->assertCount( 1, $result->details() );
		$this->assertSame( 2, $result->details()[0]->product_id );
	}

	public function test_details_are_capped_but_totals_are_not(): void {
		$result = new BulkResult();

		for ( $i = 1; $i <= BulkResult::MAX_DETAILS + 25; $i++ ) {
			$result->add( BulkEntry::failed( $i, 'schedule_failed' ) );
		}

		$this->assertSame( BulkResult::MAX_DETAILS + 25, $result->count( BulkEntry::FAILED ) );
		$this->assertCount( BulkResult::MAX_DETAILS, $result->details() );
	}

	public function test_round_trips_through_an_array(): void {
		$result = new BulkResult( BulkAction::UNPUBLISH_NOW );
		$result->add( BulkEntry::success( 1 ) );
		$result->add( BulkEntry::partial( 2, 'cleanup_failed' ) );

		$copy = BulkResult::from_array( unserialize( serialize( $result->to_array() ) ) );

		$this->assertNotNull( $copy );
		$this->assertSame( BulkAction::UNPUBLISH_NOW, $copy->action() );
		$this->assertSame( $result->to_array(), $copy->to_array() );
	}

	/**
	 * @dataProvider corrupt_data
	 */
	public function test_corrupt_stored_data_is_rejected( mixed $data ): void {
		$this->assertNull( BulkResult::from_array( $data ) );
	}

	public static function corrupt_data(): array {
		return array(
			'false'        => array( false ),
			'string'       => array( 'x' ),
			'missing keys' => array( array( 'action' => 'schedule' ) ),
			'bad types'    => array( array( 'action' => 1, 'counts' => array(), 'details' => array() ) ),
		);
	}

	public function test_invalid_detail_entries_are_dropped(): void {
		$copy = BulkResult::from_array(
			array(
				'action'  => 'remove',
				'counts'  => array( 'failed' => 2 ),
				'details' => array( array( 1, 'bogus', 'x' ), array( 'a', 'failed', 'x' ), array( 7, 'failed', 'remove_failed' ) ),
			)
		);

		$this->assertCount( 1, $copy->details() );
		$this->assertSame( 7, $copy->details()[0]->product_id );
		$this->assertSame( 2, $copy->count( BulkEntry::FAILED ) );
	}

	public function test_empty_result(): void {
		$this->assertTrue( ( new BulkResult() )->is_empty() );
	}

	public function test_action_validation(): void {
		$this->assertTrue( BulkAction::is_valid( 'schedule' ) );
		$this->assertTrue( BulkAction::is_valid( 'none' ) );
		$this->assertFalse( BulkAction::is_valid( 'delete_everything' ) );
		$this->assertFalse( BulkAction::is_valid( '' ) );
	}
}
