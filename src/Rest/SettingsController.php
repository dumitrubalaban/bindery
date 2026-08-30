<?php
/**
 * REST controller for the settings page.
 *
 * @package Bindery
 */

declare(strict_types=1);

namespace Bindery\Rest;

use Bindery\Data\ValuePorter;
use Bindery\Locale\LocaleManager;
use Bindery\Settings\Settings;
use Bindery\Support\Capabilities;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Roles;
use WP_User;
use WP_User_Query;

/**
 * Backs the React settings app under `bindery/v1`.
 *
 *  - GET  /settings        — current settings + the choices the UI renders.
 *  - POST /settings        — save settings.
 *  - GET  /values/export   — full value export (for the Data tab download).
 *  - POST /values/import   — import a value payload.
 *
 * Every route requires `manage_options`: settings are a site-admin concern,
 * distinct from the per-post editing capability.
 */
final class SettingsController {

	private const NAMESPACE = 'bindery/v1';

	public function __construct(
		private readonly Settings $settings,
		private readonly LocaleManager $locales,
		private readonly ValuePorter $porter
	) {
	}

	public function registerRoutes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/settings',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get' ),
					'permission_callback' => array( $this, 'canManage' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'save' ),
					'permission_callback' => array( $this, 'canManage' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/values/export',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'exportValues' ),
				'permission_callback' => array( $this, 'canManage' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/values/import',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'importValues' ),
				'permission_callback' => array( $this, 'canManage' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/users/search',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'searchUsers' ),
				'permission_callback' => array( $this, 'canManage' ),
				'args'                => array(
					'q' => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	public function canManage(): bool {
		return current_user_can( 'manage_options' );
	}

	public function get(): WP_REST_Response {
		return rest_ensure_response(
			array(
				'settings' => $this->settings->all(),
				'choices'  => $this->choices(),
			)
		);
	}

	public function save( WP_REST_Request $request ): WP_REST_Response {
		$input = $request->get_json_params();
		$input = is_array( $input ) ? $input : array();

		// Capture the previously-permitted users BEFORE overwriting the option, so
		// Capabilities::syncUsers() can revoke exactly the ones being dropped.
		$previous_users = (array) $this->settings->get( 'permissions.users', array() );

		$saved = $this->settings->save( $input );

		$roles      = (array) ( $saved['permissions']['roles'] ?? array() );
		$user_ids   = (array) ( $saved['permissions']['users'] ?? array() );
		$self_added = false;

		// Never let a settings save lock the saving user out of editing: if their
		// role isn't checked and they aren't individually permitted, keep them on
		// the individual allow-list (and persist that correction) instead of
		// silently forcing a role back on for everyone, as before.
		$current = wp_get_current_user();
		if ( $current instanceof WP_User && $current->exists() ) {
			$covered_by_role = array() !== array_intersect( array_values( (array) $current->roles ), $roles );
			$covered_by_user = in_array( $current->ID, $user_ids, true );

			if ( ! $covered_by_role && ! $covered_by_user ) {
				$user_ids[] = $current->ID;
				$self_added = true;
				$input      = array_replace_recursive(
					$input,
					array(
						'permissions' => array(
							'roles' => $roles,
							'users' => $user_ids,
						),
					)
				);
				$saved      = $this->settings->save( $input );
				$user_ids   = (array) ( $saved['permissions']['users'] ?? array() );
			}
		}

		// Make the "who can edit" choice real: sync the editing capability to the
		// selected roles and individually-permitted users so the controls aren't
		// merely cosmetic.
		Capabilities::syncRoles( $roles );
		Capabilities::syncUsers( $previous_users, $user_ids );

		return rest_ensure_response(
			array(
				'settings'   => $saved,
				'saved'      => true,
				'self_added' => $self_added,
			)
		);
	}

	public function exportValues(): WP_REST_Response {
		return rest_ensure_response( $this->porter->export() );
	}

	public function importValues( WP_REST_Request $request ): WP_REST_Response {
		$payload = $request->get_json_params();
		$count   = $this->porter->import( is_array( $payload ) ? $payload : array() );

		return rest_ensure_response(
			array(
				'imported' => $count,
			)
		);
	}

	/**
	 * Backs the Permissions tab's user picker: a bounded, search-as-you-type
	 * lookup rather than shipping the whole user table to the browser (which
	 * would not scale on a site with many accounts).
	 */
	public function searchUsers( WP_REST_Request $request ): WP_REST_Response {
		$term = trim( (string) $request->get_param( 'q' ) );

		$args = array(
			'number'  => 20,
			'orderby' => 'display_name',
			'order'   => 'ASC',
			'fields'  => array( 'ID', 'display_name', 'user_email', 'user_login' ),
		);

		if ( '' !== $term ) {
			$args['search']         = '*' . $term . '*';
			$args['search_columns'] = array( 'user_login', 'user_email', 'display_name', 'user_nicename' );
		}

		$results = array();
		foreach ( ( new WP_User_Query( $args ) )->get_results() as $user ) {
			$results[] = $this->userChoice( $user );
		}

		return rest_ensure_response( $results );
	}

	/**
	 * The selectable options the settings UI needs to render its controls.
	 *
	 * @return array<string, mixed>
	 */
	private function choices(): array {
		$post_types = array();
		foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $type ) {
			if ( in_array( $type->name, array( 'attachment' ), true ) ) {
				continue;
			}
			$post_types[] = array(
				'value' => $type->name,
				'label' => $type->labels->name ?? $type->name,
			);
		}

		$roles      = array();
		$wp_roles   = wp_roles();
		$role_names = $wp_roles instanceof WP_Roles ? $wp_roles->get_names() : array();
		foreach ( $role_names as $slug => $label ) {
			$roles[] = array(
				'value' => $slug,
				'label' => $label,
			);
		}

		$tags = array();
		foreach ( Settings::AVAILABLE_TAGS as $tag => $label ) {
			$tags[] = array(
				'value' => $tag,
				'label' => $label,
			);
		}

		// Resolve the currently-permitted user ids to display-ready choices up
		// front, so the picker can render its existing selections without first
		// having to search for them.
		$selected_users = array();
		foreach ( (array) $this->settings->get( 'permissions.users', array() ) as $user_id ) {
			$user = get_userdata( (int) $user_id );
			if ( $user instanceof WP_User ) {
				$selected_users[] = $this->userChoice( $user );
			}
		}

		return array(
			'tags'           => $tags,
			'post_types'     => $post_types,
			'roles'          => $roles,
			'locales'        => $this->locales->available(),
			'selected_users' => $selected_users,
		);
	}

	/**
	 * A user, shaped for the settings UI's picker (id + a human label it can
	 * render/search on).
	 *
	 * @param WP_User|object $user A WP_User or a partial WP_User_Query result
	 *                             carrying at least ID/display_name/user_email.
	 * @return array{value:int,label:string,email:string}
	 */
	private function userChoice( object $user ): array {
		$display = (string) ( $user->display_name ?? '' );

		return array(
			'value' => (int) $user->ID,
			'label' => '' !== $display ? $display : (string) $user->user_login,
			'email' => (string) ( $user->user_email ?? '' ),
		);
	}
}
