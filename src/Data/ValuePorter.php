<?php
/**
 * Value export/import.
 *
 * @package Bindery
 */

declare(strict_types=1);

namespace Bindery\Data;

use Bindery\Storage\HistoryStorageDecorator;
use Bindery\Storage\Schema\Migrator;
use Bindery\Storage\StorageManager;

/**
 * Moves Bindery field values in and out of the site as a portable payload.
 *
 * Shared by the WP-CLI commands and the settings-page Data tab so both speak the
 * same JSON shape. Import goes through {@see StorageManager} (so it honours the
 * active adapter chain) with history recording suppressed, so a migration does
 * not flood each field's revision log.
 */
final class ValuePorter {

	/**
	 * Hard caps so a crafted import file cannot exhaust memory/storage.
	 */
	private const MAX_ROWS        = 20000;
	private const MAX_VALUE_BYTES = 200000;

	public function __construct(
		private readonly StorageManager $storage,
		private readonly HistoryStorageDecorator $recorder
	) {
	}

	/**
	 * A portable export of every stored value.
	 *
	 * @return array<string, mixed>
	 */
	public function export(): array {
		global $wpdb;

		$table = Migrator::valuesTable();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare( 'SELECT object_id, scope, field_key, locale, value FROM %i ORDER BY object_id, field_key, locale', $table ),
			ARRAY_A
		);

		$values = array();
		foreach ( (array) $rows as $row ) {
			$values[] = array(
				'object_id' => (int) $row['object_id'],
				'scope'     => (string) $row['scope'],
				'field_key' => (string) $row['field_key'],
				'locale'    => (string) $row['locale'],
				'value'     => json_decode( (string) $row['value'], true ),
			);
		}

		return array(
			'schema'  => 'bindery/values',
			'version' => Migrator::SCHEMA_VERSION,
			'values'  => $values,
		);
	}

	/**
	 * Import a payload produced by {@see export()}. Returns the number written.
	 *
	 * The file is untrusted (it can be hand-edited or come from another site), so
	 * every value is sanitised with {@see wp_kses_post()} — even though the field
	 * renderers escape on output, this strips scripts/event handlers at rest as
	 * defence in depth — and the payload is bounded in row count and value size.
	 *
	 * @param array<string, mixed> $payload
	 */
	public function import( array $payload ): int {
		if ( ! isset( $payload['values'] ) || ! is_array( $payload['values'] ) ) {
			return 0;
		}

		$rows = array_slice( $payload['values'], 0, self::MAX_ROWS );

		$this->recorder->suppress( true );

		$count = 0;
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) || ! isset( $row['field_key'] ) ) {
				continue;
			}
			$this->storage->set(
				(int) ( $row['object_id'] ?? 0 ),
				sanitize_key( (string) $row['field_key'] ),
				sanitize_text_field( (string) ( $row['locale'] ?? '' ) ),
				$this->sanitizeValue( $row['value'] ?? null )
			);
			++$count;
		}

		$this->recorder->suppress( false );

		return $count;
	}

	/**
	 * Recursively neutralise an imported value: strings are run through
	 * wp_kses_post and length-capped; arrays (repeaters) are sanitised leaf-wise;
	 * scalars pass through.
	 */
	private function sanitizeValue( mixed $value ): mixed {
		if ( is_string( $value ) ) {
			if ( strlen( $value ) > self::MAX_VALUE_BYTES ) {
				$value = substr( $value, 0, self::MAX_VALUE_BYTES );
			}

			return wp_kses_post( $value );
		}

		if ( is_array( $value ) ) {
			return array_map( array( $this, 'sanitizeValue' ), $value );
		}

		return $value;
	}
}
