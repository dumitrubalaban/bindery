<?php
/**
 * Public template API (facade).
 *
 * Global functions theme authors call. These are intentionally in the global
 * namespace and loaded as a functions file (not autoloaded). Each is a thin
 * facade over container services.
 *
 * @package Bindery
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}


use Bindery\Container;
use Bindery\Editor\TemplateFieldCollector;
use Bindery\Fields\FieldDefinition;
use Bindery\Fields\FieldDefinitionFactory;
use Bindery\Fields\FieldRegistry;
use Bindery\Fields\FieldRenderer;
use Bindery\Fields\Scope;
use Bindery\Fields\ValueResolver;
use Bindery\Plugin;

if ( ! function_exists( 'bindery_container' ) ) {
	/**
	 * The plugin DI container.
	 */
	function bindery_container(): Container {
		return Plugin::instance()->container();
	}
}

if ( ! function_exists( 'bindery_register_field' ) ) {
	/**
	 * Declare an editable field and return its normalised definition.
	 *
	 * @param array<string, mixed> $args Field options (type, default, scope, …).
	 */
	function bindery_register_field( string $key, array $args = array() ): FieldDefinition {
		$container  = bindery_container();
		$definition = $container->get( FieldDefinitionFactory::class )->create( $key, $args );

		return $container->get( FieldRegistry::class )->register( $definition );
	}
}

if ( ! function_exists( 'bindery_definition' ) ) {
	/**
	 * Get the declared definition for a key, declaring it on first use.
	 *
	 * @param array<string, mixed> $args Field options used if not yet declared.
	 */
	function bindery_definition( string $key, array $args = array() ): FieldDefinition {
		$registry = bindery_container()->get( FieldRegistry::class );
		$existing = $registry->get( $key );

		return $existing ?? bindery_register_field( $key, $args );
	}
}

if ( ! function_exists( 'bindery_value' ) ) {
	/**
	 * Resolve a field's effective value (override ?? default).
	 *
	 * @param array<string, mixed> $args      Field options used if not declared.
	 * @param int|null             $object_id Override the object to resolve for.
	 */
	function bindery_value( string $key, array $args = array(), ?int $object_id = null ): mixed {
		$definition = bindery_definition( $key, $args );

		return bindery_container()->get( ValueResolver::class )->resolve( $definition, $object_id );
	}
}

if ( ! function_exists( 'bindery_rows' ) ) {
	/**
	 * Resolve a repeater field's rows for looping in a theme template.
	 *
	 * Each row is an associative array of sub-field values. The theme is
	 * responsible for escaping when it outputs them.
	 *
	 * @param array<string, mixed> $args      Field options used if not declared.
	 * @param int|null             $object_id Override the object to resolve for.
	 * @return list<array<string, mixed>>
	 */
	function bindery_rows( string $key, array $args = array(), ?int $object_id = null ): array {
		// bindery_rows() is the repeater accessor — imply the type so the
		// array-of-rows default normalises correctly without the caller saying so.
		if ( ! isset( $args['type'] ) ) {
			$args['type'] = 'repeater';
		}
		$definition = bindery_definition( $key, $args );
		$value      = bindery_container()->get( ValueResolver::class )->resolve( $definition, $object_id );

		if ( ! is_array( $value ) ) {
			return array();
		}

		return array_values( array_filter( $value, 'is_array' ) );
	}
}

if ( ! function_exists( 'bindery_get_field' ) ) {
	/**
	 * Render a field to an output-ready (escaped) HTML string.
	 *
	 * @param array<string, mixed> $args      Field options used if not declared.
	 * @param int|null             $object_id Override the object to resolve for.
	 */
	function bindery_get_field( string $key, array $args = array(), ?int $object_id = null ): string {
		$definition = bindery_definition( $key, $args );

		return bindery_container()->get( FieldRenderer::class )->render( $definition, $object_id );
	}
}

if ( ! function_exists( 'bindery_field' ) ) {
	/**
	 * Echo a rendered field. Output is already escaped by the field type.
	 *
	 * @param array<string, mixed> $args      Field options used if not declared.
	 * @param int|null             $object_id Override the object to resolve for.
	 */
	function bindery_field( string $key, array $args = array(), ?int $object_id = null ): void {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- field type renders escaped HTML.
		echo bindery_get_field( $key, $args, $object_id );
	}
}

if ( ! function_exists( 'bindery_get_attrs' ) ) {
	/**
	 * Build the HTML attribute string that marks an element as a Bindery editable
	 * region for the front-end overlay.
	 *
	 * Designed for hand-coded theme templates: the developer owns the tag and
	 * markup; this only prints `data-bindery-*` hooks. The editable markers
	 * (`data-bindery-field`/`-object`/`-type`) are emitted ONLY for users who hold
	 * the field's capability and only when the field is not locked, so anonymous
	 * visitors receive clean markup and locked regions stay display-only. Editor
	 * renders also record the declaration so a later REST save can whitelist it.
	 *
	 * You own the tag, so output the escaped value yourself (the field is declared
	 * by this call, so the inner accessor needs no args):
	 *   <h1 <?php bindery_attrs( 'hero_title', array( 'type' => 'h1' ) ); ?>><?php
	 *     echo esc_html( (string) bindery_value( 'hero_title' ) );
	 *   ?></h1>
	 *
	 * @param array<string, mixed> $args      Field options (type, default, locked …).
	 * @param int|null             $object_id Override the object to resolve for.
	 */
	function bindery_get_attrs( string $key, array $args = array(), ?int $object_id = null ): string {
		if ( '' === $key ) {
			return '';
		}

		$definition = bindery_definition( $key, $args );

		$current_post = get_the_ID();
		$current_post = false === $current_post ? 0 : (int) $current_post;

		$value = bindery_container()->get( ValueResolver::class )->resolve( $definition, $object_id );
		$empty = ( is_scalar( $value ) && '' === trim( (string) $value ) ) ? '1' : '0';

		$attrs = array( 'data-bindery-empty' => $empty );

		$locked   = (bool) $definition->arg( 'locked' );
		$can_edit = current_user_can( $definition->capability );

		if ( $can_edit && ! $locked ) {
			// The overlay edits from a page, so the marker carries that page's id
			// (for whitelist + permission); the definition's scope still routes a
			// global field's storage to object 0 on save.
			$attrs['data-bindery-field']  = $key;
			$attrs['data-bindery-object'] = (string) $current_post;
			$attrs['data-bindery-type']   = $definition->type;

			// Record the declaration so a REST save (which never renders this
			// template) can rebuild and whitelist the field.
			bindery_container()->get( TemplateFieldCollector::class )->record(
				$current_post,
				$key,
				$args
			);
		}

		$out = '';
		foreach ( $attrs as $name => $val ) {
			$out .= sprintf( ' %s="%s"', $name, esc_attr( (string) $val ) );
		}

		return ltrim( $out );
	}
}

if ( ! function_exists( 'bindery_attrs' ) ) {
	/**
	 * Echo the Bindery editable-region attributes. See {@see bindery_get_attrs()}.
	 *
	 * @param array<string, mixed> $args      Field options (type, default, locked …).
	 * @param int|null             $object_id Override the object to resolve for.
	 */
	function bindery_attrs( string $key, array $args = array(), ?int $object_id = null ): void {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- attribute values are esc_attr()'d in builder.
		echo bindery_get_attrs( $key, $args, $object_id );
	}
}

if ( ! function_exists( 'bindery_repeater_attrs' ) ) {
	/**
	 * Mark a loop's wrapping element as an editable repeater region: the
	 * front-end overlay adds add/move/edit/delete controls to it, and any
	 * change saves the field's *entire* rows array through the same
	 * `/values` endpoint every other field type already uses — the repeater
	 * type's own sanitiser (see {@see \Bindery\Support\Sanitizer}) re-checks
	 * every row's sub-fields against `args['fields']` on the way in, so nothing
	 * client-supplied reaches storage without being typed and sanitised.
	 *
	 * Loop the same rows in the template with {@see bindery_rows()} (identical
	 * $args, same "first declaration wins" rule as bindery_attrs()/bindery_value()).
	 * Each rendered row should carry two data attributes so the overlay can
	 * read/reorder rows without re-parsing the theme's row markup:
	 *   data-bindery-row-index="0"
	 *   data-bindery-row-data="<?php echo esc_attr( wp_json_encode( $row ) ); ?>"
	 *
	 * `args['fields']` doubles as the add/edit form's schema: each entry is
	 * either a type string ('text', 'image', …) or `['type' => …, 'multiline'
	 * => true]` for a textarea instead of a single-line input.
	 *
	 * @param array<string, mixed> $args      Field options — must include 'fields' (sub-field
	 *                                        schema) and 'default' (initial rows).
	 * @param int|null             $object_id Override the object to resolve for.
	 */
	function bindery_repeater_attrs( string $key, array $args = array(), ?int $object_id = null ): void {
		if ( '' === $key ) {
			return;
		}

		$args['type'] = 'repeater';
		$definition   = bindery_definition( $key, $args );

		$current_post = get_the_ID();
		$current_post = false === $current_post ? 0 : (int) $current_post;

		$locked   = (bool) $definition->arg( 'locked' );
		$can_edit = current_user_can( $definition->capability );

		$attrs = array();

		if ( $can_edit && ! $locked ) {
			$attrs['data-bindery-repeater'] = $key;
			$attrs['data-bindery-object']   = (string) ( Scope::Page === $definition->scope ? $current_post : 0 );
			$attrs['data-bindery-schema']   = wp_json_encode( (array) $definition->arg( 'fields', array() ) );

			bindery_container()->get( TemplateFieldCollector::class )->record( $current_post, $key, $args );
		}

		$out = '';
		foreach ( $attrs as $name => $val ) {
			$out .= sprintf( ' %s="%s"', $name, esc_attr( (string) $val ) );
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- attribute values are esc_attr()'d above.
		echo ltrim( $out );
	}
}
