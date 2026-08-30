<?php
/**
 * History-recording storage decorator.
 *
 * @package Bindery
 */

declare(strict_types=1);

namespace Bindery\Storage;

use Bindery\Contracts\StorageAdapter;
use Bindery\History\ValueHistory;

/**
 * Records every value write to {@see ValueHistory} before delegating to the inner
 * adapter, so each edit becomes a restorable version. Reads pass straight through.
 *
 * Sits OUTSIDE the caching decorator in the chain so it observes every actual
 * write. Recording can be turned off globally with the `bindery/record_history`
 * filter, or suppressed transiently (e.g. during a bulk import) via {@see suppress()}.
 */
final class HistoryStorageDecorator implements StorageAdapter {

	private bool $suppressed = false;

	public function __construct(
		private readonly StorageAdapter $inner,
		private readonly ValueHistory $history
	) {
	}

	/**
	 * Turn recording off/on for the rest of the request (importers use this to
	 * avoid flooding history with one version per imported value).
	 */
	public function suppress( bool $suppressed = true ): void {
		$this->suppressed = $suppressed;
	}

	public function get( int $object_id, string $key, string $locale ): mixed {
		return $this->inner->get( $object_id, $key, $locale );
	}

	public function set( int $object_id, string $key, string $locale, mixed $value ): void {
		if ( ! $this->suppressed ) {
			/**
			 * Filters whether to record value-history versions. Default true.
			 *
			 * @param bool $enabled Whether to record. Default true.
			 */
			if ( apply_filters( 'bindery/record_history', true ) ) {
				$scope = $object_id > 0 ? 'page' : 'global';
				$this->history->record( $object_id, $scope, $key, $locale, $value );
			}
		}

		$this->inner->set( $object_id, $key, $locale, $value );
	}

	public function delete( int $object_id, string $key, string $locale ): void {
		$this->inner->delete( $object_id, $key, $locale );
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	public function all( int $object_id ): array {
		return $this->inner->all( $object_id );
	}
}
