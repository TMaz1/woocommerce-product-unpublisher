<?php

use PHPUnit\Framework\TestCase;
use ProductUnpublisher\Schedule\RetryPolicy;

final class RetryPolicyTest extends TestCase {

	public function test_delay_doubles_and_is_capped(): void {
		$policy = new RetryPolicy();

		$this->assertSame( 30, $policy->delay( 1 ) );
		$this->assertSame( 60, $policy->delay( 2 ) );
		$this->assertSame( 120, $policy->delay( 3 ) );
		$this->assertSame( 240, $policy->delay( 4 ) );
		$this->assertSame( 900, $policy->delay( 10 ) );
		$this->assertSame( 900, $policy->delay( 500 ) );
	}

	public function test_zero_attempts_use_the_base_delay(): void {
		$this->assertSame( 30, ( new RetryPolicy() )->delay( 0 ) );
	}

	public function test_exhaustion_at_the_limit(): void {
		$policy = new RetryPolicy( 5 );

		$this->assertFalse( $policy->exhausted( 4 ) );
		$this->assertTrue( $policy->exhausted( 5 ) );
		$this->assertTrue( $policy->exhausted( 6 ) );
	}
}
