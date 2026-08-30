<?php
/**
 * Block Bindings source: bindery/field.
 *
 * @package Bindery
 */

declare(strict_types=1);

namespace Bindery\Bindings;

use Bindery\Fields\FieldDefinitionFactory;
use Bindery\Fields\FieldRegistry;
use Bindery\Fields\Scope;
use Bindery\Fields\ValueResolver;
use WP_Block;

/**
 * Registers `bindery/field` with the WordPress Block Bindings API so any block
 * attribute can resolve from the Bindery value engine:
 *
 *   {"metadata":{"bindings":{"content":{"source":"bindery/field",
 *      "args":{"key":"hero_title","default":"Welcome"}}}}}
 *
 * The same precedence (override ?? default), per-locale, applies. Page-scoped
 * fields resolve against the block's `postId` context; global fields against 0.
 */
final class FieldBindingSource {

	public const SOURCE = 'bindery/field';

	public function __construct(
		private readonly FieldRegistry $registry,
		private readonly FieldDefinitionFactory $factory,
		private readonly ValueResolver $resolver
	) {
	}

	/**
	 * Register the source. Safe to call on `init`.
	 */
	public function register(): void {
		if ( ! function_exists( 'register_block_bindings_source' ) ) {
			return;
		}

		register_block_bindings_source(
			self::SOURCE,
			array(
				'label'              => __( 'Bindery Field', 'bindery' ),
				'get_value_callback' => array( $this, 'getValue' ),
				'uses_context'       => array( 'postId' ),
			)
		);
	}

	/**
	 * Resolve a bound attribute's value.
	 *
	 * @param array<string, mixed> $source_args    Binding args; must include `key`.
	 * @param WP_Block|null        $block          The block instance (for context).
	 * @param string               $attribute_name The attribute being bound. Part
	 *        of the core get_value_callback signature; unused here but required
	 *        positionally so $block resolves correctly.
	 */
	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- fixed core callback signature.
	public function getValue( array $source_args, ?WP_Block $block = null, string $attribute_name = '' ): mixed {
		$key = isset( $source_args['key'] ) ? (string) $source_args['key'] : '';
		if ( '' === $key ) {
			return null;
		}

		$args = $source_args;
		unset( $args['key'] );

		$definition = $this->registry->get( $key );
		if ( null === $definition ) {
			$definition = $this->registry->register( $this->factory->create( $key, $args ) );
		}

		$object_id = null;
		if ( Scope::Page === $definition->scope
			&& $block instanceof WP_Block
			&& isset( $block->context['postId'] )
		) {
			$object_id = (int) $block->context['postId'];
		}

		return $this->resolver->resolve( $definition, $object_id );
	}
}
