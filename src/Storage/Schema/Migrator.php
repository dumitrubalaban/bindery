<?php
/**
 * Versioned database schema migrator.
 *
 * @package Bindery
 */

declare(strict_types=1);

namespace Bindery\Storage\Schema;

/**
 * Creates and upgrades Bindery's custom tables.
 *
 * Migrations are forward-only and idempotent: {@see Migrator::migrate()} runs on
 * activation and whenever the stored schema version is behind the code's
 * {@see Migrator::SCHEMA_VERSION}. dbDelta() handles additive column/key changes.
 */
final class Migrator {

	/**
	 * Current schema version shipped in code. Bump on every schema change.
	 *
	 * v2 adds the value-history table for per-field revisions / restore.
	 */
	public const SCHEMA_VERSION = 2;

	/**
	 * Option storing the installed schema version.
	 */
	private const VERSION_OPTION = 'bindery_schema_version';

	/**
	 * Unprefixed base name of the values table.
	 */
	public const VALUES_TABLE = 'bindery_values';

	/**
	 * Unprefixed base name of the value-history table.
	 */
	public const HISTORY_TABLE = 'bindery_value_history';

	/**
	 * Fully-qualified, prefixed name of the values table.
	 */
	public static function valuesTable(): string {
		global $wpdb;

		return $wpdb->prefix . self::VALUES_TABLE;
	}

	/**
	 * Fully-qualified, prefixed name of the value-history table.
	 */
	public static function historyTable(): string {
		global $wpdb;

		return $wpdb->prefix . self::HISTORY_TABLE;
	}

	/**
	 * Run any pending migrations.
	 */
	public function migrate(): void {
		$installed = (int) get_option( self::VERSION_OPTION, 0 );

		if ( $installed >= self::SCHEMA_VERSION ) {
			return;
		}

		$this->createValuesTable();
		$this->createHistoryTable();

		update_option( self::VERSION_OPTION, self::SCHEMA_VERSION, true );
	}

	/**
	 * Create or upgrade the values table.
	 *
	 * One row per (object_id, field_key, locale). object_id 0 means a global
	 * (site-wide) value; otherwise it is the post/object the value belongs to.
	 * `value` holds JSON so scalar fields and structured fields (repeaters)
	 * share one schema.
	 */
	private function createValuesTable(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table           = self::valuesTable();
		$charset_collate = $wpdb->get_charset_collate();

		// Note: dbDelta is whitespace- and format-sensitive (two spaces after
		// PRIMARY KEY, lowercase types, each field on its own line).
		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			object_id bigint(20) unsigned NOT NULL DEFAULT 0,
			scope varchar(20) NOT NULL DEFAULT 'page',
			field_key varchar(191) NOT NULL DEFAULT '',
			locale varchar(20) NOT NULL DEFAULT '',
			value longtext NULL,
			updated_at datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
			PRIMARY KEY  (id),
			UNIQUE KEY object_field_locale (object_id, field_key, locale),
			KEY field_key (field_key),
			KEY scope (scope)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	/**
	 * Create or upgrade the value-history table.
	 *
	 * One row per write (append-only): each {@see \Bindery\Storage\StorageManager}
	 * set() records the value, who changed it and when, so a field can be reviewed
	 * and restored. Pruned to a per-field cap by {@see \Bindery\History\ValueHistory}.
	 */
	private function createHistoryTable(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table           = self::historyTable();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			object_id bigint(20) unsigned NOT NULL DEFAULT 0,
			scope varchar(20) NOT NULL DEFAULT 'page',
			field_key varchar(191) NOT NULL DEFAULT '',
			locale varchar(20) NOT NULL DEFAULT '',
			value longtext NULL,
			edited_by bigint(20) unsigned NOT NULL DEFAULT 0,
			edited_at datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
			PRIMARY KEY  (id),
			KEY object_field_locale (object_id, field_key, locale),
			KEY edited_at (edited_at)
		) {$charset_collate};";

		dbDelta( $sql );
	}
}
