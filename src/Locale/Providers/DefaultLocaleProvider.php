<?php
/**
 * Default, filter-driven locale provider.
 *
 * @package Bindery
 */

declare(strict_types=1);

namespace Bindery\Locale\Providers;

use Bindery\Contracts\LocaleProvider;

/**
 * Ships zero-config but is fully filterable so custom routing (e.g. RO/RU/EN
 * path prefixes) can drive the active locale without a third-party plugin:
 *
 *  - `bindery/current_locale`    — the locale for this request.
 *  - `bindery/default_locale`    — the fallback locale.
 *  - `bindery/available_locales` — [ code => label ] of editable locales.
 */
final class DefaultLocaleProvider implements LocaleProvider {

	public function current(): string {
		/** @var string $current */
		$current = apply_filters( 'bindery/current_locale', $this->default() );

		return '' !== $current ? $current : $this->default();
	}

	public function default(): string {
		$site = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();

		/** @var string $default */
		$default = apply_filters( 'bindery/default_locale', $site );

		return '' !== $default ? $default : 'en_US';
	}

	/**
	 * @return array<string, string>
	 */
	public function available(): array {
		$default = $this->default();

		/** @var array<string, string> $locales */
		$locales = apply_filters( 'bindery/available_locales', array( $default => $default ) );

		return is_array( $locales ) && array() !== $locales ? $locales : array( $default => $default );
	}
}
