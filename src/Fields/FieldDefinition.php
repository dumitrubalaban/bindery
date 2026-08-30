<?php
/**
 * Immutable field definition value object.
 *
 * @package Bindery
 */

declare(strict_types=1);

namespace Bindery\Fields;

/**
 * A normalised, immutable description of one editable region.
 *
 * Built exclusively by {@see FieldDefinitionFactory} so normalisation lives in
 * one place. `type` is the data/render strategy id (text, richtext, image …);
 * `tag` is the optional HTML element for scalar rendering; `args` carries any
 * type- or source-specific extras (e.g. attrs, callback, repeater subfields).
 */
final readonly class FieldDefinition {

	/**
	 * @param array<string, mixed> $args Extra type/source-specific options.
	 */
	public function __construct(
		public string $key,
		public string $type,
		public ?string $tag,
		public mixed $default,
		public Scope $scope,
		public string $source,
		public bool $localeAware,
		public string $capability,
		public ?string $label,
		public array $args = array()
	) {
	}

	/**
	 * Build an HTML attribute string from `args['attrs']` (already escaped).
	 */
	public function renderAttributes(): string {
		$attrs = $this->args['attrs'] ?? array();
		if ( ! is_array( $attrs ) || array() === $attrs ) {
			return '';
		}

		$out = '';
		foreach ( $attrs as $name => $value ) {
			$name = preg_replace( '/[^a-z0-9_-]/i', '', (string) $name );
			if ( '' === (string) $name ) {
				continue;
			}
			$out .= sprintf( ' %s="%s"', $name, esc_attr( (string) $value ) );
		}

		return $out;
	}

	/**
	 * A stable args value, or a fallback when unset.
	 */
	public function arg( string $name, mixed $fallback = null ): mixed {
		return $this->args[ $name ] ?? $fallback;
	}
}
