<?php
/**
 * Locale manager.
 *
 * @package Bindery
 */

declare(strict_types=1);

namespace Bindery\Locale;

use Bindery\Contracts\LocaleProvider;
use Bindery\Fields\FieldDefinition;

/**
 * Wraps the active {@see LocaleProvider} and answers the one question the value
 * engine needs: which locale string to store/read a given field under. A field
 * that is not locale-aware always uses the empty-string locale.
 */
final class LocaleManager {

	public function __construct(
		private readonly LocaleProvider $provider
	) {
	}

	public function current(): string {
		return $this->provider->current();
	}

	public function default(): string {
		return $this->provider->default();
	}

	/**
	 * @return array<string, string>
	 */
	public function available(): array {
		return $this->provider->available();
	}

	/**
	 * The storage locale for a definition: current locale, or '' when the field
	 * is not localised.
	 */
	public function localeFor( FieldDefinition $definition ): string {
		return $definition->localeAware ? $this->current() : '';
	}

	public function provider(): LocaleProvider {
		return $this->provider;
	}
}
