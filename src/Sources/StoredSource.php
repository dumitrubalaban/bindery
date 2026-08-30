<?php
/**
 * Stored value source (Bindery's own storage).
 *
 * @package Bindery
 */

declare(strict_types=1);

namespace Bindery\Sources;

use Bindery\Contracts\ValueSource;
use Bindery\Fields\FieldContext;
use Bindery\Fields\FieldDefinition;
use Bindery\Storage\StorageManager;

/**
 * The default source: reads the override from Bindery's storage layer for the
 * given object and locale. Returns null when nothing is stored.
 */
final class StoredSource implements ValueSource {

	public function __construct(
		private readonly StorageManager $storage
	) {
	}

	public function id(): string {
		return 'stored';
	}

	public function resolve( FieldDefinition $definition, FieldContext $context ): mixed {
		return $this->storage->get( $context->objectId, $definition->key, $context->locale );
	}
}
