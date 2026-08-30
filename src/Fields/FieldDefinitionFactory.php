<?php
/**
 * Field definition factory.
 *
 * @package Bindery
 */

declare(strict_types=1);

namespace Bindery\Fields;

use Bindery\Support\Capabilities;

/**
 * Normalises a loose `bindery_field()` args array into an immutable
 * {@see FieldDefinition}. All shorthand resolution lives here so the rest of the
 * system only ever sees canonical definitions.
 *
 * Shorthands:
 *  - `['type' => 'h1']` (any known HTML tag) → text field rendered in that tag.
 *  - `['type' => 'link']` → url field rendered as an anchor.
 */
final class FieldDefinitionFactory {

	/**
	 * HTML tags accepted as a `type` shorthand for a tagged text field.
	 *
	 * @var list<string>
	 */
	private const TEXT_TAGS = array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'span', 'div', 'strong', 'em', 'small', 'label', 'figcaption', 'blockquote' );

	public function __construct(
		private readonly FieldTypeRegistry $types
	) {
	}

	/**
	 * Resolve a developer-facing type-or-tag to its canonical data type.
	 *
	 * `h1`–`blockquote` → `text`; `link` → `url`; anything else is returned
	 * unchanged. Shared with the sanitiser so sub-field types normalise the same
	 * way as top-level ones.
	 */
	public static function canonicalType( string $type ): string {
		if ( in_array( $type, self::TEXT_TAGS, true ) ) {
			return 'text';
		}

		if ( 'link' === $type ) {
			return 'url';
		}

		return $type;
	}

	/**
	 * @param array<string, mixed> $args
	 */
	public function create( string $key, array $args ): FieldDefinition {
		$type = (string) ( $args['type'] ?? 'text' );
		$tag  = isset( $args['tag'] ) ? (string) $args['tag'] : null;

		if ( in_array( $type, self::TEXT_TAGS, true ) ) {
			$tag  = $tag ?? $type;
			$type = 'text';
		} elseif ( 'link' === $type ) {
			$type = 'url';
			$tag  = $tag ?? 'a';
		}

		if ( ! $this->types->has( $type ) ) {
			$type = 'text';
		}

		$type_object = $this->types->get( $type );
		$default     = $type_object?->normalizeDefault( $args['default'] ?? null );

		$scope        = Scope::fromLoose( $args['scope'] ?? null );
		$source       = (string) ( $args['source'] ?? 'stored' );
		$locale_aware = (bool) ( $args['localized'] ?? $args['locale_aware'] ?? true );
		$capability   = (string) ( $args['capability'] ?? Capabilities::EDIT_CONTENT );
		$label        = isset( $args['label'] ) ? (string) $args['label'] : null;

		$extra = $args;
		unset(
			$extra['type'],
			$extra['tag'],
			$extra['default'],
			$extra['scope'],
			$extra['source'],
			$extra['localized'],
			$extra['locale_aware'],
			$extra['capability'],
			$extra['label']
		);

		return new FieldDefinition(
			$key,
			$type,
			$tag,
			$default,
			$scope,
			$source,
			$locale_aware,
			$capability,
			$label,
			$extra
		);
	}
}
