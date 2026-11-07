<?php

namespace ProductUnpublisher\Tests;

use ProductUnpublisher\Storage\ScheduleStore;
use ProductUnpublisher\Time\LocalTimeCodec;

final class InMemoryScheduleStore implements ScheduleStore {

	/** @var array<int,string> */
	public array $raw = array();

	/** @var array<int,int> */
	public array $attempts = array();

	/** When true, set_timestamp() silently does nothing (simulates a failed meta write). */
	public bool $fail_writes = false;

	private LocalTimeCodec $codec;

	public function __construct( FixedClock $clock ) {
		$this->codec = new LocalTimeCodec( $clock );
	}

	public function get_timestamp( int $product_id ): ?int {
		return $this->codec->parse( $this->raw[ $product_id ] ?? null );
	}

	public function get_raw( int $product_id ): ?string {
		return $this->raw[ $product_id ] ?? null;
	}

	public function set_timestamp( int $product_id, int $timestamp ): void {
		if ( $this->fail_writes ) {
			return;
		}

		$this->raw[ $product_id ] = $this->codec->format( $timestamp );
	}

	public function restore_raw( int $product_id, ?string $raw ): void {
		if ( null === $raw ) {
			unset( $this->raw[ $product_id ] );
			return;
		}

		$this->raw[ $product_id ] = $raw;
	}

	public function delete( int $product_id ): void {
		unset( $this->raw[ $product_id ] );
	}

	public function get_attempts( int $product_id ): int {
		return $this->attempts[ $product_id ] ?? 0;
	}

	public function increment_attempts( int $product_id ): int {
		$this->attempts[ $product_id ] = $this->get_attempts( $product_id ) + 1;

		return $this->attempts[ $product_id ];
	}

	public function clear_attempts( int $product_id ): void {
		unset( $this->attempts[ $product_id ] );
	}

	public function has_data( int $product_id ): bool {
		return isset( $this->raw[ $product_id ] ) || isset( $this->attempts[ $product_id ] );
	}
}
