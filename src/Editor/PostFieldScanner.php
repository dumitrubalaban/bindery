<?php
/**
 * Post field scanner.
 *
 * @package Bindery
 */

declare(strict_types=1);

namespace Bindery\Editor;

use Bindery\Bindings\FieldBindingSource;
use Bindery\Fields\FieldDefinition;
use Bindery\Fields\FieldDefinitionFactory;

/**
 * Derives the set of editable fields for a post by walking its blocks.
 *
 * This is the authoritative, server-trusted source of "what may be edited on
 * this page": every block carrying a `bindery/field` binding, plus every
 * `bindery/editable-text` block. The REST controller uses it both to list
 * fields and as a write whitelist, so a client can never target a key that is
 * not actually present in the page's markup.
 */
final class PostFieldScanner {

	public function __construct(
		private readonly FieldDefinitionFactory $factory
	) {
	}

	/**
	 * @return array<string, FieldDefinition> Keyed by field key.
	 */
	public function scan( int $post_id ): array {
		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post ) {
			return array();
		}

		$found = array();
		$this->walk( parse_blocks( (string) $post->post_content ), $found );

		/**
		 * Filters the editable fields discovered for a post.
		 *
		 * @param array<string, FieldDefinition> $found   Discovered definitions.
		 * @param int                            $post_id The post id.
		 */
		return apply_filters( 'bindery/post_fields', $found, $post_id );
	}

	/**
	 * @param array<int, array<string, mixed>> $blocks
	 * @param array<string, FieldDefinition>   $found
	 */
	private function walk( array $blocks, array &$found ): void {
		foreach ( $blocks as $block ) {
			$name  = (string) ( $block['blockName'] ?? '' );
			$attrs = (array) ( $block['attrs'] ?? array() );

			$bindings = $attrs['metadata']['bindings'] ?? array();
			foreach ( (array) $bindings as $binding ) {
				if ( ! is_array( $binding ) || FieldBindingSource::SOURCE !== ( $binding['source'] ?? '' ) ) {
					continue;
				}
				$args = (array) ( $binding['args'] ?? array() );
				$key  = (string) ( $args['key'] ?? '' );
				if ( '' !== $key ) {
					unset( $args['key'] );
					$found[ $key ] = $this->factory->create( $key, $args );
				}
			}

			// Any Bindery block that declares a fieldKey contributes a field.
			// Text blocks derive their type from `tag`; structured blocks set
			// `binderyType` (e.g. repeater) and a `subfields` map. Merge with the
			// registered block's defaults so values omitted from the saved markup
			// (e.g. default subfields) are still known.
			if ( str_starts_with( $name, 'bindery/' ) && '' !== (string) ( $attrs['fieldKey'] ?? '' ) ) {
				$merged = array_merge( $this->blockDefaults( $name ), $attrs );
				$key    = (string) $merged['fieldKey'];
				$args   = array(
					'type' => (string) ( $merged['binderyType'] ?? $merged['tag'] ?? 'text' ),
				);
				if ( isset( $merged['placeholder'] ) ) {
					$args['default'] = (string) $merged['placeholder'];
				}
				if ( isset( $merged['subfields'] ) && is_array( $merged['subfields'] ) ) {
					$args['fields'] = $merged['subfields'];
				}
				if ( ! empty( $merged['locked'] ) ) {
					$args['locked'] = true;
				}
				$found[ $key ] = $this->factory->create( $key, $args );

				// A button also stores its link URL under {key}__url.
				if ( 'button' === (string) ( $merged['binderyType'] ?? '' ) ) {
					$found[ $key . '__url' ] = $this->factory->create( $key . '__url', array( 'type' => 'url' ) );
				}

				// A form stores its editable labels under {key}_{slot}.
				if ( 'form' === (string) ( $merged['binderyType'] ?? '' ) ) {
					foreach ( array( '_title', '_name', '_email', '_message', '_submit', '_success' ) as $bindery_slot ) {
						$found[ $key . $bindery_slot ] = $this->factory->create( $key . $bindery_slot, array( 'type' => 'text' ) );
					}
				}
			}

			if ( ! empty( $block['innerBlocks'] ) ) {
				$this->walk( (array) $block['innerBlocks'], $found );
			}
		}
	}

	/**
	 * Default attribute values for a registered block type.
	 *
	 * @return array<string, mixed>
	 */
	private function blockDefaults( string $name ): array {
		$type = \WP_Block_Type_Registry::get_instance()->get_registered( $name );
		if ( null === $type || ! is_array( $type->attributes ) ) {
			return array();
		}

		$out = array();
		foreach ( $type->attributes as $attr => $schema ) {
			if ( is_array( $schema ) && array_key_exists( 'default', $schema ) ) {
				$out[ $attr ] = $schema['default'];
			}
		}

		return $out;
	}
}
