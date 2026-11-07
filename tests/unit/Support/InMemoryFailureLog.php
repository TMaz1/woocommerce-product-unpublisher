<?php

namespace ProductUnpublisher\Tests;

use ProductUnpublisher\Diagnostics\FailureLog;

final class InMemoryFailureLog implements FailureLog {

	/** @var array<int,array{product_id:int,reason:string,time:int,attempt:int}> */
	public array $items = array();

	public function record( int $product_id, string $reason, int $attempt = 0 ): void {
		$this->items[] = array(
			'product_id' => $product_id,
			'reason'     => $reason,
			'time'       => 0,
			'attempt'    => $attempt,
		);
	}

	public function entries(): array {
		return $this->items;
	}

	public function actionable( int $since ): array {
		return $this->items;
	}

	public function clear(): void {
		$this->items = array();
	}

	/** @return string[] */
	public function reasons(): array {
		return array_column( $this->items, 'reason' );
	}
}
