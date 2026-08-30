<?php
/**
 * Settings admin page.
 *
 * @package Bindery
 */

declare(strict_types=1);

namespace Bindery\Admin;

/**
 * Registers the "Bindery" admin menu under Tools and mounts the React settings app.
 *
 * The page itself is just a root node; all UI lives in the built React bundle,
 * which talks to {@see \Bindery\Rest\SettingsController} over REST.
 */
final class SettingsPage {

	public const MENU_SLUG = 'bindery';
	private const HANDLE   = 'bindery-settings';

	/**
	 * Page-hook suffix, set when the menu is added, used to scope asset loading.
	 */
	private string $hook = '';

	public function registerMenu(): void {
		$this->hook = (string) add_submenu_page(
			'tools.php',
			__( 'Bindery', 'bindery' ),
			__( 'Bindery', 'bindery' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * Enqueue the React app only on the Bindery settings screen.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 */
	public function enqueue( string $hook_suffix ): void {
		if ( $hook_suffix !== $this->hook ) {
			return;
		}

		$asset_file = BINDERY_DIR . 'build/settings/index.asset.php';
		$asset      = is_file( $asset_file ) ? require $asset_file : array(
			'dependencies' => array( 'wp-element', 'wp-components', 'wp-api-fetch', 'wp-i18n' ),
			'version'      => BINDERY_VERSION,
		);

		wp_enqueue_script(
			self::HANDLE,
			BINDERY_URL . 'build/settings/index.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_enqueue_style( 'wp-components' );

		wp_add_inline_script(
			self::HANDLE,
			'window.binderySettings = ' . wp_json_encode(
				array(
					'rest'  => esc_url_raw( rest_url( 'bindery/v1' ) ),
					'nonce' => wp_create_nonce( 'wp_rest' ),
				)
			) . ';',
			'before'
		);
	}

	public function render(): void {
		echo '<div class="wrap"><div id="bindery-settings-root"></div></div>';
	}
}
