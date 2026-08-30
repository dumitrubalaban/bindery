<?php
/**
 * Capability management.
 *
 * @package Bindery
 */

declare(strict_types=1);

namespace Bindery\Support;

use WP_Roles;

/**
 * Owns Bindery's custom capabilities and their role and per-user assignments.
 *
 * Editing client content is gated by {@see Capabilities::EDIT_CONTENT}, granted
 * to administrators and editors on activation. It can also be granted directly
 * to specific users, independent of role — e.g. so only some Administrators
 * (not all of them), or a low-privilege client account, can edit. Field
 * definitions may override the required capability per field.
 */
final class Capabilities {

	/**
	 * Capability required to edit Bindery content.
	 */
	public const EDIT_CONTENT = 'bindery_edit_content';

	/**
	 * Roles that receive {@see Capabilities::EDIT_CONTENT} by default.
	 *
	 * @var list<string>
	 */
	private const DEFAULT_ROLES = array( 'administrator', 'editor' );

	/**
	 * Grant default capabilities to the default roles. Called on activation.
	 */
	public static function grantDefaults(): void {
		$roles = self::roles();
		if ( null === $roles ) {
			return;
		}

		foreach ( self::DEFAULT_ROLES as $role_name ) {
			$role = $roles->get_role( $role_name );
			if ( null !== $role && ! $role->has_cap( self::EDIT_CONTENT ) ) {
				$role->add_cap( self::EDIT_CONTENT );
			}
		}
	}

	/**
	 * Grant the editing capability to exactly the given roles, removing it from
	 * all others. Used when the roles are chosen on the settings page.
	 *
	 * Deliberately does NOT force "administrator" to always be included — the
	 * whole point of per-user permissions is that not every Administrator
	 * account should necessarily be able to edit content. Losing yourself out
	 * is instead prevented one level up, in {@see \Bindery\Rest\SettingsController::save()},
	 * which keeps the saving user on the individual allow-list if this call
	 * would otherwise leave them without the capability.
	 *
	 * @param list<string> $selected Role slugs that should be able to edit.
	 */
	public static function syncRoles( array $selected ): void {
		$roles = self::roles();
		if ( null === $roles ) {
			return;
		}

		foreach ( $roles->get_names() as $role_name => $label ) {
			$role = $roles->get_role( $role_name );
			if ( null === $role ) {
				continue;
			}
			$should = in_array( $role_name, $selected, true );
			if ( $should && ! $role->has_cap( self::EDIT_CONTENT ) ) {
				$role->add_cap( self::EDIT_CONTENT );
			} elseif ( ! $should && $role->has_cap( self::EDIT_CONTENT ) ) {
				$role->remove_cap( self::EDIT_CONTENT );
			}
		}
	}

	/**
	 * Grant/revoke the editing capability directly on specific users, independent
	 * of role. This is what lets a single Administrator (or a low-privilege
	 * client account) be individually permitted without changing anyone's role.
	 *
	 * A direct grant/revoke on a user only ever touches that user's own capability
	 * record — it never affects what their role grants everyone else, and
	 * revoking it is a safe no-op for a user who only ever had the capability
	 * via their role in the first place.
	 *
	 * @param list<int> $previous User ids that were individually permitted before this save.
	 * @param list<int> $selected User ids that should be individually permitted now.
	 */
	public static function syncUsers( array $previous, array $selected ): void {
		foreach ( array_diff( $previous, $selected ) as $user_id ) {
			$user = get_userdata( (int) $user_id );
			if ( $user instanceof \WP_User && $user->has_cap( self::EDIT_CONTENT ) ) {
				$user->remove_cap( self::EDIT_CONTENT );
			}
		}

		foreach ( $selected as $user_id ) {
			$user = get_userdata( (int) $user_id );
			if ( $user instanceof \WP_User && ! $user->has_cap( self::EDIT_CONTENT ) ) {
				$user->add_cap( self::EDIT_CONTENT );
			}
		}
	}

	/**
	 * Remove all capability grants: every role, plus every individually
	 * permitted user recorded in settings. Intended for uninstall, not
	 * deactivation.
	 */
	public static function removeDefaults(): void {
		$roles = self::roles();
		if ( null !== $roles ) {
			foreach ( $roles->get_names() as $role_name => $label ) {
				$role = $roles->get_role( $role_name );
				if ( null !== $role && $role->has_cap( self::EDIT_CONTENT ) ) {
					$role->remove_cap( self::EDIT_CONTENT );
				}
			}
		}

		self::syncUsers( self::permittedUserIds(), array() );
	}

	/**
	 * The individually-permitted user ids currently on record, read directly
	 * from the option so this works even where the Settings service isn't
	 * wired up (e.g. during uninstall).
	 *
	 * @return list<int>
	 */
	private static function permittedUserIds(): array {
		$stored = get_option( 'bindery_settings', array() );
		$users  = is_array( $stored ) && isset( $stored['permissions']['users'] ) && is_array( $stored['permissions']['users'] )
			? $stored['permissions']['users']
			: array();

		return array_values( array_map( 'absint', $users ) );
	}

	/**
	 * Resolve the global roles object if available.
	 */
	private static function roles(): ?WP_Roles {
		if ( ! function_exists( 'wp_roles' ) ) {
			return null;
		}

		return wp_roles();
	}
}
