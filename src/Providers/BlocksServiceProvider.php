<?php
/**
 * Blocks service provider.
 *
 * @package Bindery
 */

declare(strict_types=1);

namespace Bindery\Providers;

use Bindery\Container;
use Bindery\Contracts\ServiceProvider;

/**
 * Auto-registers every self-contained block under `blocks/` (any directory
 * containing a block.json). Each block ships its own assets via block.json
 * (`style`, `viewScript`, `editorScript`), which WordPress enqueues only when
 * the block is actually present on a page.
 */
final class BlocksServiceProvider implements ServiceProvider {

	public function register( Container $container ): void {
		// No services to bind.
	}

	public function boot( Container $container ): void {
		add_action( 'init', array( $this, 'registerBlocks' ) );
	}

	/**
	 * Discover and register block.json-based blocks from the build output.
	 */
	public function registerBlocks(): void {
		$blocks_dir = BINDERY_DIR . 'build/blocks';

		if ( ! is_dir( $blocks_dir ) ) {
			return;
		}

		$dirs = glob( $blocks_dir . '/*', GLOB_ONLYDIR );
		foreach ( (array) $dirs as $dir ) {
			if ( is_file( $dir . '/block.json' ) ) {
				register_block_type( $dir );
			}
		}

		$this->ensureEditorRuntimeDependency();
		$this->registerBlockStyles();
	}

	/**
	 * Register design variants (block styles) for the structured blocks.
	 */
	private function registerBlockStyles(): void {
		if ( ! function_exists( 'register_block_style' ) ) {
			return;
		}

		register_block_style(
			'bindery/cards',
			array(
				'name'  => 'minimal',
				'label' => __( 'Minimal', 'bindery' ),
			)
		);
		register_block_style(
			'bindery/slider',
			array(
				'name'  => 'boxed',
				'label' => __( 'Boxed', 'bindery' ),
			)
		);
	}

	/**
	 * Make every Bindery block's editor script depend on the `bindery-editor`
	 * runtime (which provides window.bindery). The build-generated asset files
	 * only list @wordpress/* dependencies, so this wiring is added here.
	 */
	private function ensureEditorRuntimeDependency(): void {
		if ( ! function_exists( 'wp_scripts' ) ) {
			return;
		}

		$scripts = wp_scripts();
		foreach ( \WP_Block_Type_Registry::get_instance()->get_all_registered() as $name => $type ) {
			if ( ! str_starts_with( (string) $name, 'bindery/' ) ) {
				continue;
			}
			foreach ( (array) $type->editor_script_handles as $handle ) {
				$dependency = $scripts->query( $handle, 'registered' );
				if ( $dependency && ! in_array( 'bindery-editor', $dependency->deps, true ) ) {
					$dependency->deps[] = 'bindery-editor';
				}
			}
		}
	}
}
