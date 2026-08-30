<?php
/**
 * Locale provider contract.
 *
 * @package Bindery
 */

declare(strict_types=1);

namespace Bindery\Contracts;

/**
 * Resolves the active content locale and the set of available locales.
 *
 * The shipped {@see \Bindery\Locale\Providers\DefaultLocaleProvider} is filter
 * driven so custom routing (e.g. RO/RU/EN path prefixes) works out of the box;
 * WPML/Polylang adapters implement this interface and register via the
 * `bindery/locale_provider` filter.
 */
interface LocaleProvider {

	/**
	 * The locale to read/write content for on the current request.
	 */
	public function current(): string;

	/**
	 * The site's default/fallback locale.
	 */
	public function default(): string;

	/**
	 * Available locales as [ code => label ].
	 *
	 * @return array<string, string>
	 */
	public function available(): array;
}
