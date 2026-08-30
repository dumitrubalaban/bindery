<?php
/**
 * Custom-table storage adapter.
 *
 * @package Bindery
 */

declare(strict_types=1);

namespace Bindery\Storage;

use Bindery\Contracts\StorageAdapter;
use Bindery\Storage\Schema\Migrator;

/**
 * Default storage: one row per (object_id, field_key, locale) in the
 * `wp_bindery_values` table. Values are JSON-encoded so scalars and structured
 * values (repeaters) share one schema and round-trip to native PHP types.
 */
final class TableStorageAdapter implements StorageAdapter {

	public function get( int $object_id, string $key, string $locale ): mixed {
		global $wpdb;

		$table = Migrator::valuesTable();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$raw = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT value FROM %i WHERE object_id = %d AND field_key = %s AND locale = %s LIMIT 1',
				$table,
				$object_id,
				$key,
				$locale
			)
		);

		if ( null === $raw ) {
			return null;
		}

		return json_decode( (string) $raw, true );
	}

	public function set( int $object_id, string $key, string $locale, mixed $value ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->replace(
			Migrator::valuesTable(),
			array(
				'object_id'  => $object_id,
				'scope'      => $object_id > 0 ? 'page' : 'global',
				'field_key'  => $key,
				'locale'     => $locale,
				'value'      => (string) wp_json_encode( $value ),
				'updated_at' => current_time( 'mysql', true ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s' )
		);
	}

	public function delete( int $object_id, string $key, string $locale ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete(
			Migrator::valuesTable(),
			array(
				'object_id' => $object_id,
				'field_key' => $key,
				'locale'    => $locale,
			),
			array( '%d', '%s', '%s' )
		);
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	public function all( int $object_id ): array {
		global $wpdb;

		$table = Migrator::valuesTable();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT field_key, locale, value FROM %i WHERE object_id = %d',
				$table,
				$object_id
			),
			ARRAY_A
		);

		$out = array();
		foreach ( (array) $rows as $row ) {
			$key                    = (string) $row['field_key'];
			$locale                 = (string) $row['locale'];
			$out[ $key ][ $locale ] = json_decode( (string) $row['value'], true );
		}

		return $out;
	}
}
