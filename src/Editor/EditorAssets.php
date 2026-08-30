<?php
/**
 * Editor asset enqueuing.
 *
 * @package Bindery
 */

declare(strict_types=1);

namespace Bindery\Editor;

use Bindery\Locale\LocaleManager;

/**
 * Enqueues the build-free editor sidebar script on the block editor and seeds
 * it with the data it needs (REST root + nonce + available locales) via an
 * inline `window.binderyEditor` payload.
 */
final class EditorAssets {

	private const HANDLE = 'bindery-editor';

	public function __construct(
		private readonly LocaleManager $locales
	) {
	}

	/**
	 * Register the runtime script + its data on init, so blocks can declare it
	 * as a dependency (the block's editorScript lists `bindery-editor`).
	 */
	public function register(): void {
		$asset = $this->asset();

		wp_register_script(
			self::HANDLE,
			BINDERY_URL . 'build/editor/index.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		$data = array(
			'restRoot' => esc_url_raw( rest_url( 'bindery/v1' ) ),
			'nonce'    => wp_create_nonce( 'wp_rest' ),
			'locales'  => $this->locales->available(),
			'current'  => $this->locales->current(),
			'default'  => $this->locales->default(),
			'binding'  => 'bindery/field',
		);

		wp_add_inline_script(
			self::HANDLE,
			'window.binderyEditor = ' . wp_json_encode( $data ) . ';',
			'before'
		);

		wp_set_script_translations( self::HANDLE, 'bindery' );
	}

	/**
	 * Enqueue the (already-registered) runtime on the block editor.
	 */
	public function enqueue(): void {
		wp_enqueue_script( self::HANDLE );
	}

	/**
	 * Build-generated dependencies + version for the editor runtime.
	 *
	 * @return array{dependencies: list<string>, version: string}
	 */
	private function asset(): array {
		$file  = BINDERY_DIR . 'build/editor/index.asset.php';
		$asset = is_file( $file ) ? require $file : array();

		return array(
			'dependencies' => ( isset( $asset['dependencies'] ) && is_array( $asset['dependencies'] ) ) ? $asset['dependencies'] : array(),
			'version'      => isset( $asset['version'] ) ? (string) $asset['version'] : BINDERY_VERSION,
		);
	}
}
