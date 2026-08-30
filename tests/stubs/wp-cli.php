<?php
/**
 * Minimal WP-CLI stubs for static analysis only (scanned, never executed).
 *
 * @package Bindery
 */

// phpcs:disable

namespace {
	class WP_CLI {
		/** @param object|callable|string $callable */
		public static function add_command( string $name, $callable, array $args = array() ): bool {
		}
		public static function log( string $message ): void {
		}
		public static function success( string $message ): void {
		}
		/** @return never */
		public static function error( string $message, $exit = true ) {
		}
	}
}

namespace WP_CLI\Utils {
	/**
	 * @param array<int, array<string, mixed>> $items
	 * @param array<int, string>               $fields
	 */
	function format_items( string $format, array $items, $fields ): void {
	}
}
