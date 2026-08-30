<?php
/**
 * Template-declared field collector.
 *
 * @package Bindery
 */

declare(strict_types=1);

namespace Bindery\Editor;

use Bindery\Fields\FieldDefinition;
use Bindery\Fields\FieldDefinitionFactory;

/**
 * Bridges hand-coded, attribute-marked editable regions to the REST write path.
 *
 * Regions declared in a theme template with `bindery_attrs()` live in PHP, not in
 * a post's block markup, so {@see PostFieldScanner} (which reads post_content)
 * cannot see them — and a REST save, a separate request that never renders the
 * template, would reject them as un-whitelisted.
 *
 * This collector closes that gap: while an editor renders a page, each declared
 * region records its raw args here; on `wp_footer`/`shutdown` the set is persisted
 * to the object's meta. A later REST request rebuilds those definitions from meta
 * and merges them into the writable whitelist. Recording is gated to capable users
 * so anonymous page views never trigger a meta write.
 */
final class TemplateFieldCollector {

	private const META_KEY = '_bindery_template_fields';

	/**
	 * Raw args seen this request, keyed by object id then field key.
	 *
	 * @var array<int, array<string, array<string, mixed>>>
	 */
	private array $pending = array();

	private bool $persisted = false;

	public function __construct(
		private readonly FieldDefinitionFactory $factory
	) {
	}

	/**
	 * Note that a field was declared in the current template render.
	 *
	 * @param array<string, mixed> $args The raw args passed to bindery_attrs().
	 */
	public function record( int $object_id, string $key, array $args ): void {
		if ( '' === $key || $object_id <= 0 ) {
			return;
		}

		$this->pending[ $object_id ][ $key ] = $args;
	}

	/**
	 * Persist this request's declared sets to per-object meta, replacing the
	 * stored set so a template that stops declaring a field self-cleans. Writes
	 * only when the set actually changed.
	 */
	public function persist(): void {
		if ( $this->persisted ) {
			return;
		}
		$this->persisted = true;

		foreach ( $this->pending as $object_id => $fields ) {
			$encoded  = (string) wp_json_encode( $fields );
			$existing = get_post_meta( $object_id, self::META_KEY, true );

			if ( is_string( $existing ) && $existing === $encoded ) {
				continue;
			}

			update_post_meta( $object_id, self::META_KEY, wp_slash( $encoded ) );
		}
	}

	/**
	 * Rebuild the field definitions a template previously declared for an object.
	 *
	 * @return array<string, FieldDefinition>
	 */
	public function declared( int $object_id ): array {
		if ( $object_id <= 0 ) {
			return array();
		}

		$raw = get_post_meta( $object_id, self::META_KEY, true );
		if ( ! is_string( $raw ) || '' === $raw ) {
			return array();
		}

		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) ) {
			return array();
		}

		$out = array();
		foreach ( $decoded as $key => $args ) {
			$out[ (string) $key ] = $this->factory->create( (string) $key, is_array( $args ) ? $args : array() );
		}

		return $out;
	}
}
