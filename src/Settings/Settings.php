<?php
/**
 * Settings store.
 *
 * @package Bindery
 */

declare(strict_types=1);

namespace Bindery\Settings;

/**
 * Single source of truth for Bindery's user-facing configuration.
 *
 * Stored as one option ({@see OPTION}) and resolved in layers:
 *   built-in defaults  →  saved option (the admin UI)  →  `bindery/settings` filter
 * so non-technical users configure via the settings page while developers can
 * still override anything in code. All reads go through {@see all()} / {@see get()}
 * so callers never see a partial or unsanitised shape.
 */
final class Settings {

	public const OPTION = 'bindery_settings';

	/**
	 * Hard cap on how many individually-permitted user ids one site may store,
	 * so a malformed or abusive payload can't grow the option unbounded.
	 */
	private const MAX_PERMITTED_USERS = 200;

	/**
	 * Text tags the auto content-editor can safely make editable. Exposed to the
	 * UI as the list of choices; image/link/button editing is handled by blocks,
	 * not here, so they are intentionally absent (no dead controls).
	 *
	 * @var array<string, string>
	 */
	public const AVAILABLE_TAGS = array(
		'h1'         => 'Heading 1',
		'h2'         => 'Heading 2',
		'h3'         => 'Heading 3',
		'h4'         => 'Heading 4',
		'h5'         => 'Heading 5',
		'h6'         => 'Heading 6',
		'p'          => 'Paragraphs',
		'li'         => 'List items',
		'blockquote' => 'Quotes',
		'span'       => 'Inline text (span)',
		'figcaption' => 'Captions',
	);

	/**
	 * Resolved settings cache for this request.
	 *
	 * @var array<string, mixed>|null
	 */
	private ?array $resolved = null;

	/**
	 * Built-in defaults — the shape every consumer can rely on.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return array(
			'enabled'     => true,
			'auto'        => array(
				'enabled'     => true,
				'tags'        => array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'li', 'blockquote' ),
				'post_types'  => array( 'page' ),
				'exclude_ids' => array(),
			),
			'overlay'     => array(
				'show_toggle' => true,
				'strict'      => false,
				'auto_enter'  => false,
				'accent'      => '#d8b75d',
			),
			'permissions' => array(
				'roles' => array( 'administrator', 'editor' ),
				'users' => array(),
			),
			'history'     => array(
				'enabled' => true,
				'cap'     => 30,
			),
			'data'        => array(
				'delete_on_uninstall' => false,
			),
		);
	}

	/**
	 * The fully-resolved, sanitised settings array.
	 *
	 * @return array<string, mixed>
	 */
	public function all(): array {
		if ( null !== $this->resolved ) {
			return $this->resolved;
		}

		$stored = get_option( self::OPTION, array() );
		$merged = $this->sanitize( is_array( $stored ) ? $stored : array() );

		/**
		 * Filters the resolved Bindery settings (developer override, wins over UI).
		 *
		 * @param array<string, mixed> $merged The resolved settings.
		 */
		$this->resolved = (array) apply_filters( 'bindery/settings', $merged );

		return $this->resolved;
	}

	/**
	 * Dot-path getter, e.g. get('auto.tags') or get('overlay.accent').
	 */
	public function get( string $path, mixed $fallback = null ): mixed {
		$value = $this->all();
		foreach ( explode( '.', $path ) as $segment ) {
			if ( ! is_array( $value ) || ! array_key_exists( $segment, $value ) ) {
				return $fallback;
			}
			$value = $value[ $segment ];
		}

		return $value;
	}

	/**
	 * Persist a new settings payload (already-sanitised on read-back).
	 *
	 * @param array<string, mixed> $input Raw input from the REST endpoint.
	 * @return array<string, mixed> The sanitised, stored settings.
	 */
	public function save( array $input ): array {
		$clean = $this->sanitize( $input );
		update_option( self::OPTION, $clean );
		$this->resolved = null;

		return $this->all();
	}

	/**
	 * Normalise any partial/untrusted input against the default shape.
	 *
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>
	 */
	public function sanitize( array $input ): array {
		$d = self::defaults();

		$auto_tags = isset( $input['auto']['tags'] ) && is_array( $input['auto']['tags'] )
			? array_values( array_intersect( array_map( 'strval', $input['auto']['tags'] ), array_keys( self::AVAILABLE_TAGS ) ) )
			: $d['auto']['tags'];

		$post_types = isset( $input['auto']['post_types'] ) && is_array( $input['auto']['post_types'] )
			? array_values( array_map( 'sanitize_key', $input['auto']['post_types'] ) )
			: $d['auto']['post_types'];

		$exclude_ids = isset( $input['auto']['exclude_ids'] ) && is_array( $input['auto']['exclude_ids'] )
			? array_values( array_filter( array_map( 'absint', $input['auto']['exclude_ids'] ) ) )
			: $d['auto']['exclude_ids'];

		// Note: unlike auto.tags below, an explicitly-empty roles list is honoured
		// as "no role gets blanket access" rather than reverting to the default —
		// that's what makes a users-only permission model (see below) possible.
		$roles = isset( $input['permissions']['roles'] ) && is_array( $input['permissions']['roles'] )
			? array_values( array_unique( array_map( 'sanitize_key', $input['permissions']['roles'] ) ) )
			: $d['permissions']['roles'];

		// Individually-permitted users, independent of role. Invalid/deleted user
		// ids are dropped on every resolve (self-healing if an account disappears),
		// and the list is bounded so a malformed payload can't grow it unbounded.
		$users_raw = isset( $input['permissions']['users'] ) && is_array( $input['permissions']['users'] )
			? array_map( 'absint', $input['permissions']['users'] )
			: $d['permissions']['users'];

		$users = array_values(
			array_unique(
				array_filter(
					array_slice( $users_raw, 0, self::MAX_PERMITTED_USERS ),
					static function ( int $id ): bool {
						return $id > 0 && function_exists( 'get_userdata' ) && false !== get_userdata( $id );
					}
				)
			)
		);

		$accent = isset( $input['overlay']['accent'] ) ? sanitize_hex_color( (string) $input['overlay']['accent'] ) : null;

		return array(
			'enabled'     => $this->bool( $input['enabled'] ?? $d['enabled'] ),
			'auto'        => array(
				'enabled'     => $this->bool( $input['auto']['enabled'] ?? $d['auto']['enabled'] ),
				'tags'        => array() === $auto_tags ? array() : $auto_tags,
				'post_types'  => $post_types,
				'exclude_ids' => $exclude_ids,
			),
			'overlay'     => array(
				'show_toggle' => $this->bool( $input['overlay']['show_toggle'] ?? $d['overlay']['show_toggle'] ),
				'strict'      => $this->bool( $input['overlay']['strict'] ?? $d['overlay']['strict'] ),
				'auto_enter'  => $this->bool( $input['overlay']['auto_enter'] ?? $d['overlay']['auto_enter'] ),
				'accent'      => $accent ?? $d['overlay']['accent'],
			),
			'permissions' => array(
				'roles' => $roles,
				'users' => $users,
			),
			'history'     => array(
				'enabled' => $this->bool( $input['history']['enabled'] ?? $d['history']['enabled'] ),
				'cap'     => max( 1, min( 200, (int) ( $input['history']['cap'] ?? $d['history']['cap'] ) ) ),
			),
			'data'        => array(
				'delete_on_uninstall' => $this->bool( $input['data']['delete_on_uninstall'] ?? $d['data']['delete_on_uninstall'] ),
			),
		);
	}

	private function bool( mixed $value ): bool {
		return filter_var( $value, FILTER_VALIDATE_BOOLEAN );
	}
}
