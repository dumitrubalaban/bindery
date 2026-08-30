<?php
/**
 * Value-history repository.
 *
 * @package Bindery
 */

declare(strict_types=1);

namespace Bindery\History;

use Bindery\Storage\Schema\Migrator;

/**
 * Append-only log of every field-value write, with restore.
 *
 * Each entry captures the value, the user who set it and when, so a field's
 * history can be reviewed and any past value put back. Works uniformly for
 * page-scoped and global values (unlike post revisions, which are post-bound).
 * History is capped per (object, key, locale) so the table cannot grow without
 * bound; {@see PER_FIELD_CAP} is filterable via `bindery/history_cap`.
 */
final class ValueHistory {

	private const PER_FIELD_CAP = 30;

	/**
	 * Append a version. The value is JSON-encoded so scalars and structured
	 * values share one column, mirroring the values table.
	 */
	public function record( int $object_id, string $scope, string $key, string $locale, mixed $value ): void {
		global $wpdb;

		$table = Migrator::historyTable();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->insert(
			$table,
			array(
				'object_id' => $object_id,
				'scope'     => $scope,
				'field_key' => $key,
				'locale'    => $locale,
				'value'     => (string) wp_json_encode( $value ),
				'edited_by' => get_current_user_id(),
				'edited_at' => current_time( 'mysql', true ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%d', '%s' )
		);

		$this->prune( $object_id, $key, $locale );
	}

	/**
	 * Recent versions for a field, newest first.
	 *
	 * @return list<array<string, mixed>>
	 */
	public function versions( int $object_id, string $key, string $locale, int $limit = 30 ): array {
		global $wpdb;

		$table = Migrator::historyTable();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT id, value, edited_by, edited_at FROM %i
				 WHERE object_id = %d AND field_key = %s AND locale = %s
				 ORDER BY id DESC LIMIT %d',
				$table,
				$object_id,
				$key,
				$locale,
				$limit
			),
			ARRAY_A
		);

		$out = array();
		foreach ( (array) $rows as $row ) {
			$out[] = array(
				'id'        => (int) $row['id'],
				'value'     => json_decode( (string) $row['value'], true ),
				'edited_by' => (int) $row['edited_by'],
				'edited_at' => (string) $row['edited_at'],
			);
		}

		return $out;
	}

	/**
	 * Fetch a single version row by id, or null when absent.
	 *
	 * @return array<string, mixed>|null
	 */
	public function version( int $id ): ?array {
		global $wpdb;

		$table = Migrator::historyTable();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT id, object_id, scope, field_key, locale, value, edited_by, edited_at FROM %i WHERE id = %d',
				$table,
				$id
			),
			ARRAY_A
		);

		if ( null === $row ) {
			return null;
		}

		$row['value'] = json_decode( (string) $row['value'], true );

		return $row;
	}

	/**
	 * Cap stored versions for a field to the (filterable) limit, dropping oldest.
	 */
	private function prune( int $object_id, string $key, string $locale ): void {
		global $wpdb;

		/**
		 * Filters how many history versions to keep per (object, key, locale).
		 *
		 * @param int $cap Maximum versions retained. Default 30.
		 */
		$cap = (int) apply_filters( 'bindery/history_cap', self::PER_FIELD_CAP );
		if ( $cap < 1 ) {
			return;
		}

		$table = Migrator::historyTable();

		// Find the id of the newest row beyond the cap; delete everything older.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$cutoff = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT id FROM %i
				 WHERE object_id = %d AND field_key = %s AND locale = %s
				 ORDER BY id DESC LIMIT 1 OFFSET %d',
				$table,
				$object_id,
				$key,
				$locale,
				$cap
			)
		);

		if ( null === $cutoff ) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				'DELETE FROM %i
				 WHERE object_id = %d AND field_key = %s AND locale = %s AND id <= %d',
				$table,
				$object_id,
				$key,
				$locale,
				(int) $cutoff
			)
		);
	}
}
