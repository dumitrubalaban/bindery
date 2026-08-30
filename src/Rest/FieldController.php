<?php
/**
 * REST controller for editing field values.
 *
 * @package Bindery
 */

declare(strict_types=1);

namespace Bindery\Rest;

use Bindery\Editor\PostFieldScanner;
use Bindery\Editor\TemplateFieldCollector;
use Bindery\Fields\FieldDefinition;
use Bindery\Fields\Scope;
use Bindery\Locale\LocaleManager;
use Bindery\Storage\StorageManager;
use Bindery\Support\Capabilities;
use Bindery\Support\Sanitizer;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Exposes the editor's read/write API under `bindery/v1`.
 *
 *  - GET  /values?post=ID&locale=xx     — fields + stored values for a post.
 *  - POST /values  { post, locale, values } — save sanitised overrides.
 *  - GET  /locales                      — available locales.
 *
 * Security: every route checks {@see Capabilities::EDIT_CONTENT}. Writes are
 * whitelisted and typed by {@see PostFieldScanner} — the page's own blocks
 * (plus anything declared via `bindery_attrs()`) define what may be written,
 * never the client — and each field's own capability is checked again in
 * {@see saveValues()}. Deliberately does NOT also require the generic WP
 * `edit_post` capability: EDIT_CONTENT is meant to be grantable to accounts
 * that cannot otherwise touch wp-admin post editing at all (e.g. a client
 * account with no Editor/Author role), and every write it allows is confined
 * to Bindery's own value store — it can never alter post_content, post_title,
 * or any other core field.
 */
final class FieldController {

	private const NAMESPACE = 'bindery/v1';

	public function __construct(
		private readonly PostFieldScanner $scanner,
		private readonly StorageManager $storage,
		private readonly Sanitizer $sanitizer,
		private readonly LocaleManager $locales,
		private readonly TemplateFieldCollector $templateFields
	) {
	}

	/**
	 * The writable field whitelist for a post: fields present in its block markup
	 * plus any declared by the theme template via bindery_attrs(). Block
	 * definitions win on key collisions (the post's own markup is authoritative).
	 *
	 * @return array<string, FieldDefinition>
	 */
	private function whitelist( int $post_id ): array {
		return $this->scanner->scan( $post_id ) + $this->templateFields->declared( $post_id );
	}

	public function registerRoutes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/values',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'getValues' ),
					'permission_callback' => array( $this, 'canEditPost' ),
					'args'                => array(
						'post'   => array(
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						),
						'locale' => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'saveValues' ),
					'permission_callback' => array( $this, 'canEditPost' ),
					'args'                => array(
						'post'   => array(
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						),
						'locale' => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'values' => array(
							'required' => true,
							'type'     => 'object',
						),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/locales',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'getLocales' ),
				'permission_callback' => array( $this, 'canEdit' ),
			)
		);
	}

	/**
	 * Capability gate for the current user (no specific post).
	 */
	public function canEdit(): bool {
		return current_user_can( Capabilities::EDIT_CONTENT );
	}

	/**
	 * Capability gate for a specific post's fields.
	 *
	 * Only requires {@see Capabilities::EDIT_CONTENT} (see the class docblock
	 * for why the generic `edit_post` capability is intentionally not also
	 * required here).
	 */
	public function canEditPost(): bool|WP_Error {
		if ( ! current_user_can( Capabilities::EDIT_CONTENT ) ) {
			return new WP_Error( 'bindery_forbidden', __( 'You cannot edit Bindery content.', 'bindery' ), array( 'status' => 403 ) );
		}

		return true;
	}

	public function getValues( WP_REST_Request $request ): WP_REST_Response {
		$post_id = absint( $request['post'] );
		$locale  = (string) ( $request['locale'] ?? '' );
		$locale  = '' !== $locale ? $locale : $this->locales->current();

		$fields = array();
		foreach ( $this->whitelist( $post_id ) as $key => $definition ) {
			$storage_locale = $definition->localeAware ? $locale : '';
			$object_id      = $this->objectId( $definition, $post_id );

			$fields[ $key ] = array(
				'value'     => $this->storage->get( $object_id, $key, $storage_locale ),
				'default'   => $definition->default,
				'type'      => $definition->type,
				'localized' => $definition->localeAware,
				'scope'     => $definition->scope->value,
				'label'     => $definition->label ?? $key,
			);
		}

		return rest_ensure_response(
			array(
				'post'   => $post_id,
				'locale' => $locale,
				'fields' => $fields,
			)
		);
	}

	public function saveValues( WP_REST_Request $request ): WP_REST_Response {
		$post_id  = absint( $request['post'] );
		$locale   = (string) ( $request['locale'] ?? '' );
		$locale   = '' !== $locale ? $locale : $this->locales->current();
		$incoming = (array) $request['values'];

		$definitions = $this->whitelist( $post_id );
		$saved       = array();
		$rejected    = array();

		foreach ( $incoming as $key => $raw ) {
			$key        = (string) $key;
			$definition = $definitions[ $key ] ?? null;

			// Whitelist: only fields actually present on the page are writable.
			if ( null === $definition ) {
				$rejected[] = $key;
				continue;
			}

			// Locked fields are visible but never client-writable.
			if ( $definition->arg( 'locked' ) ) {
				$rejected[] = $key;
				continue;
			}

			// Per-field capability override.
			if ( ! current_user_can( $definition->capability ) ) {
				$rejected[] = $key;
				continue;
			}

			$storage_locale = $definition->localeAware ? $locale : '';
			$object_id      = $this->objectId( $definition, $post_id );
			$clean          = $this->sanitizer->sanitize( $definition, $raw );

			$this->storage->set( $object_id, $key, $storage_locale, $clean );
			$saved[ $key ] = $clean;
		}

		return rest_ensure_response(
			array(
				'post'     => $post_id,
				'locale'   => $locale,
				'saved'    => $saved,
				'rejected' => $rejected,
			)
		);
	}

	public function getLocales(): WP_REST_Response {
		return rest_ensure_response(
			array(
				'current'   => $this->locales->current(),
				'default'   => $this->locales->default(),
				'available' => $this->locales->available(),
			)
		);
	}

	/**
	 * Object id a definition's value is stored under.
	 */
	private function objectId( FieldDefinition $definition, int $post_id ): int {
		return Scope::Global === $definition->scope ? 0 : $post_id;
	}
}
