<?php
/**
 * WP-CLI commands for Bindery values.
 *
 * @package Bindery
 */

declare(strict_types=1);

namespace Bindery\Cli;

use Bindery\History\ValueHistory;
use Bindery\Storage\HistoryStorageDecorator;
use Bindery\Storage\Schema\Migrator;
use Bindery\Storage\StorageManager;
use WP_CLI;

/**
 * `wp bindery` — move, inspect and restore Bindery field values.
 *
 * Because values live in a custom table (outside post_content), these commands
 * give them a first-class migration and history story:
 *
 *   wp bindery export --file=values.json
 *   wp bindery import --file=values.json
 *   wp bindery history hero_title --object=9 --locale=en_US
 *   wp bindery restore 42
 */
final class BinderyCommand {

	public function __construct(
		private readonly StorageManager $storage,
		private readonly ValueHistory $history,
		private readonly HistoryStorageDecorator $recorder
	) {
	}

	/**
	 * Export all field values to JSON.
	 *
	 * ## OPTIONS
	 *
	 * [--file=<name>]
	 * : Write to this file name inside the uploads/bindery directory. Defaults to STDOUT.
	 *
	 * @param array<int, string>    $args       Positional args (unused).
	 * @param array<string, string> $assoc_args Flags.
	 */
	public function export( array $args, array $assoc_args ): void {
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

		$payload = wp_json_encode(
			array(
				'schema'      => 'bindery/values',
				'version'     => Migrator::SCHEMA_VERSION,
				'exported_at' => current_time( 'mysql', true ),
				'values'      => $values,
			),
			JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
		);

		$file = $assoc_args['file'] ?? '';
		if ( '' !== $file ) {
			// Restrict exports to an allowed location (uploads/bindery); never an
			// arbitrary user-supplied path. basename() + sanitize_file_name() strip
			// any directory components or traversal, so the file can only land here.
			$uploads = wp_upload_dir();
			if ( ! empty( $uploads['error'] ) ) {
				WP_CLI::error( 'Uploads directory is not available.' );
			}
			$dir = trailingslashit( $uploads['basedir'] ) . 'bindery';
			if ( ! wp_mkdir_p( $dir ) ) {
				WP_CLI::error( 'Could not create the export directory.' );
			}

			$name = sanitize_file_name( basename( $file ) );
			if ( '' === $name ) {
				$name = 'export.json';
			} elseif ( ! str_ends_with( strtolower( $name ), '.json' ) ) {
				$name .= '.json';
			}
			$path = trailingslashit( $dir ) . $name;

			file_put_contents( $path, (string) $payload ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			WP_CLI::success( sprintf( 'Exported %d value(s) to %s', count( $values ), $path ) );
			return;
		}

		WP_CLI::log( (string) $payload );
	}

	/**
	 * Import field values from a JSON file produced by `wp bindery export`.
	 *
	 * History recording is suppressed during import so a migration does not flood
	 * each field's history with one version per imported row.
	 *
	 * ## OPTIONS
	 *
	 * --file=<name>
	 * : The JSON file to import, read from the uploads/bindery directory.
	 *
	 * [--dry-run]
	 * : Parse and report without writing.
	 *
	 * @param array<int, string>    $args       Positional args (unused).
	 * @param array<string, string> $assoc_args Flags.
	 */
	public function import( array $args, array $assoc_args ): void {
		$file = (string) ( $assoc_args['file'] ?? '' );
		if ( '' === $file ) {
			WP_CLI::error( 'Provide --file=<name> (a file inside the uploads/bindery directory).' );
		}

		// Read only from the plugin's own export location (uploads/bindery); never an
		// arbitrary path. basename() + sanitize_file_name() strip any directory
		// components or traversal, so the read is confined to that directory.
		$uploads = wp_upload_dir();
		$path    = trailingslashit( $uploads['basedir'] ) . 'bindery/' . sanitize_file_name( basename( $file ) );
		if ( ! is_readable( $path ) ) {
			WP_CLI::error( sprintf( 'No readable export file at %s.', $path ) );
		}

		$decoded = json_decode( (string) file_get_contents( $path ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading the plugin's own export file in a CLI command.
		if ( ! is_array( $decoded ) || ! isset( $decoded['values'] ) || ! is_array( $decoded['values'] ) ) {
			WP_CLI::error( 'Unrecognised file: expected a bindery/values export with a "values" array.' );
		}

		$dry = isset( $assoc_args['dry-run'] );
		$this->recorder->suppress( true );

		$count = 0;
		foreach ( $decoded['values'] as $row ) {
			if ( ! is_array( $row ) || ! isset( $row['field_key'] ) ) {
				continue;
			}
			$object_id = (int) ( $row['object_id'] ?? 0 );
			$key       = (string) $row['field_key'];
			$locale    = (string) ( $row['locale'] ?? '' );

			if ( ! $dry ) {
				$this->storage->set( $object_id, $key, $locale, $row['value'] ?? null );
			}
			++$count;
		}

		$this->recorder->suppress( false );

		if ( $dry ) {
			WP_CLI::success( sprintf( '%d value(s) would be imported (dry run).', $count ) );
			return;
		}

		WP_CLI::success( sprintf( 'Imported %d value(s).', $count ) );
	}

	/**
	 * List the recorded history for one field.
	 *
	 * ## OPTIONS
	 *
	 * <field_key>
	 * : The field key.
	 *
	 * [--object=<id>]
	 * : Object id (0 for global). Default 0.
	 *
	 * [--locale=<locale>]
	 * : Locale. Default empty.
	 *
	 * @param array<int, string>    $args       Positional args: field_key.
	 * @param array<string, string> $assoc_args Flags.
	 */
	public function history( array $args, array $assoc_args ): void {
		$key = (string) ( $args[0] ?? '' );
		if ( '' === $key ) {
			WP_CLI::error( 'Provide a <field_key>.' );
		}

		$object_id = (int) ( $assoc_args['object'] ?? 0 );
		$locale    = (string) ( $assoc_args['locale'] ?? '' );

		$versions = $this->history->versions( $object_id, $key, $locale );
		if ( array() === $versions ) {
			WP_CLI::log( 'No history for this field.' );
			return;
		}

		$items = array();
		foreach ( $versions as $v ) {
			$value   = $v['value'];
			$preview = is_scalar( $value ) ? (string) $value : wp_json_encode( $value );
			$items[] = array(
				'version'   => $v['id'],
				'edited_at' => $v['edited_at'],
				'edited_by' => $v['edited_by'],
				'value'     => mb_substr( (string) $preview, 0, 60 ),
			);
		}

		WP_CLI\Utils\format_items( 'table', $items, array( 'version', 'edited_at', 'edited_by', 'value' ) );
	}

	/**
	 * Restore a field to a recorded history version.
	 *
	 * ## OPTIONS
	 *
	 * <version_id>
	 * : The version id from `wp bindery history`.
	 *
	 * @param array<int, string>    $args       Positional args: version_id.
	 * @param array<string, string> $assoc_args Flags (unused; part of the WP-CLI callback signature).
	 */
	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- fixed WP-CLI callback signature.
	public function restore( array $args, array $assoc_args ): void {
		$id  = (int) ( $args[0] ?? 0 );
		$row = $id > 0 ? $this->history->version( $id ) : null;

		if ( null === $row ) {
			WP_CLI::error( sprintf( 'No history version #%d.', $id ) );
		}

		$this->storage->set(
			(int) $row['object_id'],
			(string) $row['field_key'],
			(string) $row['locale'],
			$row['value']
		);

		WP_CLI::success(
			sprintf( 'Restored %s (object %d) to version #%d.', $row['field_key'], (int) $row['object_id'], $id )
		);
	}
}
