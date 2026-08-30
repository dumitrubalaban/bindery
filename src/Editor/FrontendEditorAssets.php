<?php
/**
 * Front-end edit-overlay assets.
 *
 * @package Bindery
 */

declare(strict_types=1);

namespace Bindery\Editor;

use Bindery\Locale\LocaleManager;
use Bindery\Settings\Settings;
use Bindery\Support\Capabilities;

/**
 * Loads the front-end inline edit overlay on singular pages — but only for users
 * who hold {@see Capabilities::EDIT_CONTENT}, so visitors never receive it.
 * Overlay behaviour (toggle visibility, strict mode, accent, auto-enter) comes
 * from the settings page.
 */
final class FrontendEditorAssets {

	private const HANDLE = 'bindery-frontend';

	public function __construct(
		private readonly LocaleManager $locales,
		private readonly Settings $settings
	) {
	}

	public function enqueue(): void {
		if ( is_admin() || ! is_singular() || ! current_user_can( Capabilities::EDIT_CONTENT ) ) {
			return;
		}

		// Master switch: editing turned off site-wide → no overlay at all.
		if ( ! $this->settings->get( 'enabled' ) ) {
			return;
		}

		wp_enqueue_style( self::HANDLE, BINDERY_URL . 'assets/frontend.css', array(), BINDERY_VERSION );
		// Image fields open the native media library from the front end, which
		// (unlike wp-admin) needs its scripts/templates explicitly requested.
		wp_enqueue_media();
		wp_enqueue_script( self::HANDLE, BINDERY_URL . 'assets/frontend.js', array(), BINDERY_VERSION, true );

		/**
		 * Strict overlay mode (settings-backed): when true, the overlay only edits
		 * hand-coded `bindery_attrs()` regions and leaves blocks to the editor.
		 *
		 * @param bool $strict Whether to restrict the overlay.
		 */
		$strict = (bool) apply_filters( 'bindery/strict_overlay', (bool) $this->settings->get( 'overlay.strict' ) );

		$data = array(
			'rest'       => esc_url_raw( rest_url( 'bindery/v1' ) ),
			'nonce'      => wp_create_nonce( 'wp_rest' ),
			'post'       => (int) get_queried_object_id(),
			'locale'     => $this->locales->current(),
			'locales'    => $this->locales->available(),
			'canEdit'    => true,
			'strict'     => $strict,
			'showToggle' => (bool) $this->settings->get( 'overlay.show_toggle' ),
			'autoEnter'  => (bool) $this->settings->get( 'overlay.auto_enter' ),
			'accent'     => (string) $this->settings->get( 'overlay.accent', '#d8b75d' ),
		);

		wp_add_inline_script( self::HANDLE, 'window.binderyFront = ' . wp_json_encode( $data ) . ';', 'before' );
	}
}
