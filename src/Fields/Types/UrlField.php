<?php
/**
 * URL / link field type.
 *
 * @package Bindery
 */

declare(strict_types=1);

namespace Bindery\Fields\Types;

use Bindery\Contracts\FieldType;
use Bindery\Fields\FieldDefinition;

/**
 * A URL. Rendered as an anchor when `tag` is `a` (the `['type' => 'link']`
 * shorthand), otherwise as the escaped URL text. Anchor text comes from
 * `args['text']`, falling back to the URL itself.
 */
final class UrlField implements FieldType {

	public function id(): string {
		return 'url';
	}

	public function sanitize( mixed $value ): string {
		return esc_url_raw( (string) ( is_scalar( $value ) ? $value : '' ) );
	}

	public function normalizeDefault( mixed $default ): string {
		return is_scalar( $default ) ? (string) $default : '';
	}

	public function render( mixed $value, FieldDefinition $definition ): string {
		$url = (string) ( is_scalar( $value ) ? $value : '' );

		if ( 'a' !== $definition->tag ) {
			return esc_url( $url );
		}

		$text = (string) $definition->arg( 'text', $url );

		return sprintf(
			'<a href="%1$s"%3$s>%2$s</a>',
			esc_url( $url ),
			esc_html( $text ),
			$definition->renderAttributes()
		);
	}
}
