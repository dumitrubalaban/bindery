<?php
/**
 * Bindery uninstall routine.
 *
 * Destructive cleanup is OPT-IN. By default, uninstalling Bindery leaves all
 * stored content, schema and options intact (so a reinstall loses nothing).
 *
 * To wipe Bindery data on uninstall, enable ONE of:
 *   - define( 'BINDERY_DELETE_DATA', true ); in wp-config.php, or
 *   - update_option( 'bindery_delete_data_on_uninstall', true );
 *
 * The plugin is not bootstrapped here, so this file uses no plugin classes.
 *
 * @package Bindery
 */

declare(strict_types=1);

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$bindery_should_delete = ( defined( 'BINDERY_DELETE_DATA' ) && BINDERY_DELETE_DATA )
	|| (bool) get_option( 'bindery_delete_data_on_uninstall', false );

if ( ! $bindery_should_delete ) {
	return;
}

global $wpdb;

$bindery_table = $wpdb->prefix . 'bindery_values';
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching -- DDL statement; there is nothing to cache about dropping a table.
$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $bindery_table ) );

// Individually-permitted users (Permissions tab) hold the editing capability
// directly on their own account, not via a role — read the list before the
// settings option is deleted so those direct grants can be revoked too.
$bindery_settings           = get_option( 'bindery_settings', array() );
$bindery_permitted_user_ids = is_array( $bindery_settings ) && isset( $bindery_settings['permissions']['users'] ) && is_array( $bindery_settings['permissions']['users'] )
	? $bindery_settings['permissions']['users']
	: array();

foreach ( $bindery_permitted_user_ids as $bindery_user_id ) {
	$bindery_user = get_userdata( (int) $bindery_user_id );
	if ( $bindery_user instanceof WP_User && $bindery_user->has_cap( 'bindery_edit_content' ) ) {
		$bindery_user->remove_cap( 'bindery_edit_content' );
	}
}

delete_option( 'bindery_schema_version' );
delete_option( 'bindery_version' );
delete_option( 'bindery_global_values' );
delete_option( 'bindery_delete_data_on_uninstall' );
delete_option( 'bindery_settings' );

// Remove the custom capability from every role.
if ( function_exists( 'wp_roles' ) ) {
	$bindery_roles = wp_roles();
	foreach ( array_keys( $bindery_roles->get_names() ) as $bindery_role_name ) {
		$bindery_role = $bindery_roles->get_role( $bindery_role_name );
		if ( null !== $bindery_role && $bindery_role->has_cap( 'bindery_edit_content' ) ) {
			$bindery_role->remove_cap( 'bindery_edit_content' );
		}
	}
}
