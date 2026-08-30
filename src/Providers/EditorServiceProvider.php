<?php
/**
 * Editor service provider.
 *
 * @package Bindery
 */

declare(strict_types=1);

namespace Bindery\Providers;

use Bindery\Container;
use Bindery\Contracts\ServiceProvider;
use Bindery\Editor\AutoContentEditor;
use Bindery\Editor\EditorAssets;
use Bindery\Editor\FrontendEditorAssets;
use Bindery\Editor\TemplateFieldCollector;
use Bindery\Locale\LocaleManager;
use Bindery\Settings\Settings;
use Bindery\Storage\StorageManager;

/**
 * Wires the block-editor sidebar assets.
 */
final class EditorServiceProvider implements ServiceProvider {

	public function register( Container $container ): void {
		$container->singleton(
			EditorAssets::class,
			static fn ( Container $c ): EditorAssets => new EditorAssets(
				$c->get( LocaleManager::class )
			)
		);

		$container->singleton(
			FrontendEditorAssets::class,
			static fn ( Container $c ): FrontendEditorAssets => new FrontendEditorAssets(
				$c->get( LocaleManager::class ),
				$c->get( Settings::class )
			)
		);

		$container->singleton(
			AutoContentEditor::class,
			static fn ( Container $c ): AutoContentEditor => new AutoContentEditor(
				$c->get( StorageManager::class ),
				$c->get( LocaleManager::class ),
				$c->get( TemplateFieldCollector::class ),
				$c->get( Settings::class )
			)
		);
	}

	public function boot( Container $container ): void {
		// Register early so the block's editorScript can depend on `bindery-editor`.
		add_action(
			'init',
			static function () use ( $container ): void {
				$container->get( EditorAssets::class )->register();
			}
		);

		add_action(
			'enqueue_block_editor_assets',
			static function () use ( $container ): void {
				$container->get( EditorAssets::class )->enqueue();
			}
		);

		add_filter( 'block_editor_settings_all', array( $this, 'maybeLockLayout' ), 10, 2 );

		add_action(
			'wp_enqueue_scripts',
			static function () use ( $container ): void {
				$container->get( FrontendEditorAssets::class )->enqueue();
			}
		);

		// Make existing page body content editable in place. Late priority so it
		// runs after blocks/shortcodes have rendered to final HTML.
		add_filter(
			'the_content',
			static fn ( string $html ): string => $container->get( AutoContentEditor::class )->process( $html ),
			99
		);
	}

	/**
	 * Opt-in layout lock: when `bindery/lock_editor` is true for a post, force
	 * the editor into `contentOnly` mode so the client edits content (which,
	 * for bound blocks, flows through Bindery) but cannot restructure the layout.
	 *
	 * Disabled by default. Enable globally with
	 * `add_filter( 'bindery/lock_editor', '__return_true' )`, or per-post via the
	 * filter's second argument.
	 *
	 * @param array<string, mixed> $settings Editor settings.
	 * @param mixed                $context  Block editor context (has ->post).
	 * @return array<string, mixed>
	 */
	public function maybeLockLayout( array $settings, mixed $context ): array {
		$post = is_object( $context ) && isset( $context->post ) ? $context->post : null;

		/**
		 * Filters whether to lock the editor layout to content-only for a post.
		 *
		 * @param bool         $lock Whether to lock. Default false.
		 * @param \WP_Post|null $post The post being edited.
		 */
		if ( apply_filters( 'bindery/lock_editor', false, $post ) ) {
			/**
			 * Template-lock mode when the editor is locked. 'insert' prevents
			 * adding/removing blocks while keeping each block fully editable
			 * (safer with custom blocks than 'contentOnly'); 'contentOnly' or
			 * 'all' are also valid.
			 *
			 * @param string        $mode The templateLock value. Default 'insert'.
			 * @param \WP_Post|null $post The post being edited.
			 */
			$settings['templateLock'] = apply_filters( 'bindery/lock_mode', 'insert', $post );
		}

		return $settings;
	}
}
