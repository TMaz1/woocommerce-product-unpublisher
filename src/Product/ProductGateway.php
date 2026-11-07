<?php
/**
 * Port for reading and changing product status.
 *
 * @package ProductUnpublisher
 */

namespace ProductUnpublisher\Product;

defined( 'ABSPATH' ) || exit;

interface ProductGateway {

	/**
	 * Whether the ID belongs to an existing product.
	 */
	public function exists( int $product_id ): bool;

	/**
	 * Current post status, or null when missing.
	 */
	public function status( int $product_id ): ?string;

	/**
	 * Scheduled publication timestamp for future products, if known.
	 */
	public function publication_timestamp( int $product_id ): ?int;

	/**
	 * Move the product to Draft.
	 *
	 * @return string|null Null on success, otherwise a controlled error code.
	 */
	public function set_draft( int $product_id ): ?string;
}
