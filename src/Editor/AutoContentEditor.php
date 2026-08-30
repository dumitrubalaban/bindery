<?php
/**
 * Automatic content-editable layer.
 *
 * @package Bindery
 */

declare(strict_types=1);

namespace Bindery\Editor;

use Bindery\Locale\LocaleManager;
use Bindery\Settings\Settings;
use Bindery\Storage\StorageManager;
use Bindery\Support\Capabilities;
use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Makes the existing body content of a page client-editable without the developer
 * declaring a single field.
 *
 * On `the_content`, each simple text element (headings, paragraphs, list items …)
 * is given a stable key derived from its tag + ordinal position. A stored override
 * for that key replaces the element's text for every visitor; for editors the
 * element also gets the `data-bindery-*` markers the overlay needs, so any page's
 * copy can be edited inline and persists per page.
 *
 * This is the opt-in "auto" counterpart to declared regions (blocks /
 * bindery_attrs). Disable globally or per post with the `bindery/auto_editable`
 * filter. Only pure-text elements are touched, so inline markup (links) is never
 * flattened; elements with child markup are left as-is.
 */
final class AutoContentEditor {

	private const KEY_PREFIX = 'auto-';

	/**
	 * Tags whose direct text is treated as an editable region.
	 *
	 * @var array<string, true>
	 */
	private const EDITABLE_TAGS = array(
		'h1'         => true,
		'h2'         => true,
		'h3'         => true,
		'h4'         => true,
		'h5'         => true,
		'h6'         => true,
		'p'          => true,
		'li'         => true,
		'blockquote' => true,
		'span'       => true,
		'figcaption' => true,
		'dt'         => true,
		'dd'         => true,
	);

	public function __construct(
		private readonly StorageManager $storage,
		private readonly LocaleManager $locales,
		private readonly TemplateFieldCollector $collector,
		private readonly Settings $settings
	) {
	}

	/**
	 * Filter callback for `the_content`.
	 */
	public function process( string $html ): string {
		if ( ! is_singular() || ! is_main_query() || ! in_the_loop() ) {
			return $html;
		}
		if ( '' === trim( $html ) ) {
			return $html;
		}

		$post_id = (int) get_the_ID();
		if ( $post_id <= 0 ) {
			return $html;
		}

		// Settings gate: master switch, auto-mode, post-type scope and exclusions.
		if ( ! $this->settings->get( 'enabled' ) || ! $this->settings->get( 'auto.enabled' ) ) {
			return $html;
		}
		$post_types = (array) $this->settings->get( 'auto.post_types', array() );
		if ( array() !== $post_types && ! in_array( (string) get_post_type( $post_id ), $post_types, true ) ) {
			return $html;
		}
		if ( in_array( $post_id, array_map( 'intval', (array) $this->settings->get( 'auto.exclude_ids', array() ) ), true ) ) {
			return $html;
		}

		/**
		 * Filters whether to auto-mark a post's body content editable (after the
		 * settings gate; lets developers override per request).
		 *
		 * @param bool $enabled Whether to process this post. Default true.
		 * @param int  $post_id The post being rendered.
		 */
		if ( ! apply_filters( 'bindery/auto_editable', true, $post_id ) ) {
			return $html;
		}

		$can_edit = current_user_can( Capabilities::EDIT_CONTENT );

		// Fast path: a visitor on a page with no stored overrides needs no parsing.
		if ( ! $can_edit && ! $this->hasOverrides( $post_id ) ) {
			return $html;
		}

		return $this->rewrite( $html, $post_id, $can_edit );
	}

	/**
	 * Whether the object has any auto-* override stored (any locale).
	 */
	private function hasOverrides( int $post_id ): bool {
		foreach ( array_keys( $this->storage->all( $post_id ) ) as $key ) {
			if ( str_starts_with( (string) $key, self::KEY_PREFIX ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Walk the content, apply overrides and (for editors) editable markers.
	 */
	private function rewrite( string $html, int $post_id, bool $can_edit ): string {
		$locale = $this->locales->current();

		// Effective tag set = user selection (settings) ∩ the supported superset.
		$selected     = (array) $this->settings->get( 'auto.tags', array() );
		$enabled_tags = array_intersect_key( self::EDITABLE_TAGS, array_flip( array_map( 'strval', $selected ) ) );
		if ( array() === $enabled_tags ) {
			return $html;
		}

		$previous = libxml_use_internal_errors( true );
		$doc      = new DOMDocument( '1.0', 'UTF-8' );
		// The XML encoding hint keeps multibyte text (e.g. Greek) intact; NOIMPLIED
		// keeps our wrapper as the top node without an added <html>/<body>.
		$doc->loadHTML(
			'<?xml encoding="utf-8"?><div id="bindery-auto-root">' . $html . '</div>',
			LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
		);
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		$root = $doc->getElementById( 'bindery-auto-root' );
		if ( ! $root instanceof DOMElement ) {
			return $html;
		}

		$xpath = new DOMXPath( $doc );
		$nodes = $xpath->query( './/*', $root );
		if ( false === $nodes ) {
			return $html;
		}

		$counters = array();
		$changed  = false;

		foreach ( $nodes as $node ) {
			if ( ! $node instanceof DOMElement ) {
				continue;
			}
			$tag = strtolower( $node->nodeName );
			if ( ! isset( $enabled_tags[ $tag ] ) || $this->hasChildElements( $node ) ) {
				continue;
			}
			// Never touch a region a block or bindery_attrs() already declared —
			// declared fields win, and re-marking would clobber their key.
			if ( $node->hasAttribute( 'data-bindery-field' ) ) {
				continue;
			}
			if ( '' === trim( $node->textContent ) ) {
				continue;
			}

			$counters[ $tag ] = ( $counters[ $tag ] ?? 0 ) + 1;
			$key              = self::KEY_PREFIX . $tag . '-' . $counters[ $tag ];

			$override = $this->storage->get( $post_id, $key, $locale );
			if ( is_string( $override ) ) {
				$node->textContent = $override;
				$changed           = true;
			}

			if ( $can_edit ) {
				$node->setAttribute( 'data-bindery-field', $key );
				$node->setAttribute( 'data-bindery-object', (string) $post_id );
				$node->setAttribute( 'data-bindery-type', 'text' );
				$this->collector->record(
					$post_id,
					$key,
					array(
						'type'  => $tag,
						'scope' => 'page',
					)
				);
				$changed = true;
			}
		}

		if ( ! $changed ) {
			return $html;
		}

		$out = '';
		foreach ( $root->childNodes as $child ) {
			$out .= (string) $doc->saveHTML( $child );
		}

		return $out;
	}

	/**
	 * Whether an element contains any child *element* nodes (vs pure text).
	 */
	private function hasChildElements( DOMElement $node ): bool {
		foreach ( $node->childNodes as $child ) {
			if ( XML_ELEMENT_NODE === $child->nodeType ) {
				return true;
			}
		}

		return false;
	}
}
