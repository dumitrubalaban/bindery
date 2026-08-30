<?php
/**
 * WordPress option value source.
 *
 * @package Bindery
 */

declare(strict_types=1);

namespace Bindery\Sources;

use Bindery\Contracts\ValueSource;
use Bindery\Fields\FieldContext;
use Bindery\Fields\FieldDefinition;

/**
 * Reads a value from a WordPress option, named by `args['option']` (defaulting
 * to `bindery_{key}`). Useful for binding to settings owned elsewhere. Returns
 * null when the option is unset so the code default still applies.
 */
final class OptionSource implements ValueSource {

	public function id(): string {
		return 'option';
	}

	public function resolve( FieldDefinition $definition, FieldContext $context ): mixed {
		$option = (string) $definition->arg( 'option', 'bindery_' . $definition->key );

		// The option name can originate from field args / block-binding metadata,
		// so never read an arbitrary option. Only the plugin's own namespace is
		// allowed by default; developers may allow specific others via the filter.
		if ( ! $this->is_allowed( $option ) ) {
			return null;
		}

		$sentinel = '__bindery_unset__';
		$value    = get_option( $option, $sentinel );

		return $sentinel === $value ? null : $value;
	}

	/**
	 * Whether this source may read the given option name.
	 *
	 * @param string $option Option name requested.
	 */
	private function is_allowed( string $option ): bool {
		// Block obviously-sensitive options outright, even within the allowlist.
		if ( preg_match( '/(secret|password|passwd|api[_-]?key|access[_-]?token|_token|token_|_salt|nonce|private[_-]?key)/i', $option ) ) {
			return false;
		}

		/**
		 * Extra option names this source may read, beyond the plugin's own
		 * `bindery_` namespace. Lets a developer bind to specific external settings.
		 *
		 * @param string[] $allowed Allowed option names.
		 * @param string   $option  The option name being requested.
		 */
		$allowed = (array) apply_filters( 'bindery/option_source_allowed', array(), $option );
		if ( in_array( $option, $allowed, true ) ) {
			return true;
		}

		return str_starts_with( $option, 'bindery_' );
	}
}
