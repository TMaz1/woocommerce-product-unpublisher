<?php

namespace ProductUnpublisher\Tests;

use ProductUnpublisher\Product\ProductGateway;

final class FakeProductGateway implements ProductGateway {

	/** @var array<int,string> id => status */
	public array $statuses = array();

	/** @var array<int,int> id => publication timestamp */
	public array $publish_at = array();

	/** Error code set_draft() should return for specific ids (null = success). */
	public array $draft_errors = array();

	public array $drafted = array();

	/** Callback run after a successful set_draft(), e.g. to mimic the status listener. */
	public $after_draft = null;

	public function exists( int $product_id ): bool {
		return isset( $this->statuses[ $product_id ] );
	}

	public function status( int $product_id ): ?string {
		return $this->statuses[ $product_id ] ?? null;
	}

	public function publication_timestamp( int $product_id ): ?int {
		return $this->publish_at[ $product_id ] ?? null;
	}

	public function set_draft( int $product_id ): ?string {
		if ( ! empty( $this->draft_errors[ $product_id ] ) ) {
			return $this->draft_errors[ $product_id ];
		}

		$this->statuses[ $product_id ] = 'draft';
		$this->drafted[]               = $product_id;

		if ( is_callable( $this->after_draft ) ) {
			( $this->after_draft )( $product_id );
		}

		return null;
	}
}
