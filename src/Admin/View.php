<?php
/**
 * Minimal template loader.
 *
 * @package ProductUnpublisher
 */

namespace ProductUnpublisher\Admin;

defined( 'ABSPATH' ) || exit;

final class View {

	/**
	 * Include templates/<name>.php with the given variables in scope.
	 *
	 * @param string              $name Template name (lowercase letters, digits, hyphens).
	 * @param array<string,mixed> $vars Variables exposed to the template.
	 */
	public static function render( string $name, array $vars = array() ): void {
		if ( 1 !== preg_match( '/^[a-z0-9-]+$/', $name ) ) {
			return;
		}

		$file = WPU_PATH . 'templates/' . $name . '.php';

		if ( ! is_readable( $file ) ) {
			return;
		}

		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- Scoped template variables.
		extract( $vars, EXTR_SKIP );

		include $file;
	}

	/**
	 * Render a template and return the output.
	 *
	 * @param string              $name Template name.
	 * @param array<string,mixed> $vars Variables.
	 */
	public static function capture( string $name, array $vars = array() ): string {
		ob_start();
		self::render( $name, $vars );

		return (string) ob_get_clean();
	}
}
