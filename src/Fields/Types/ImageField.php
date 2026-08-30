<?php
/**
 * Image field type.
 *
 * @package Bindery
 */

declare(strict_types=1);

namespace Bindery\Fields\Types;

use Bindery\Contracts\FieldType;
use Bindery\Fields\FieldDefinition;

/**
 * An image referenced by attachment id (preferred) or raw URL. Rendered with
 * the size from `args['size']` (default `full`) and optional `args['alt']`.
 */
final class ImageField implements FieldType {

	public function id(): string {
		return 'image';
	}

	public function sanitize( mixed $value ): int|string {
		if ( is_numeric( $value ) ) {
			return absint( $value );
		}

		return esc_url_raw( (string) ( is_scalar( $value ) ? $value : '' ) );
	}

	public function normalizeDefault( mixed $default ): int|string {
		if ( is_numeric( $default ) ) {
			return absint( $default );
		}

		return is_scalar( $default ) ? (string) $default : '';
	}

	public function render( mixed $value, FieldDefinition $definition ): string {
		$size = (string) $definition->arg( 'size', 'full' );
		$alt  = (string) $definition->arg( 'alt', '' );

		if ( is_numeric( $value ) && absint( $value ) > 0 ) {
			$attrs = array();
			if ( '' !== $alt ) {
				$attrs['alt'] = $alt;
			}

			return (string) wp_get_attachment_image( absint( $value ), $size, false, $attrs );
		}

		$url = (string) ( is_scalar( $value ) ? $value : '' );
		if ( '' === $url ) {
			return '';
		}

		return sprintf( '<img src="%s" alt="%s"%s />', esc_url( $url ), esc_attr( $alt ), $definition->renderAttributes() );
	}
}
