<?php
/**
 * Plugin Name:       Bindery
 * Description:       Editable regions for hand-built themes. Declare what a client may edit in clean theme code; Bindery binds it to a locked, multilingual editor — no markup ownership, no builder bloat.
 * Version:           0.3.6
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Author:            Dumitru Balaban
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       bindery
 * Domain Path:       /languages
 *
 * @package Bindery
 */

declare(strict_types=1);

namespace Bindery;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( defined( 'BINDERY_VERSION' ) ) {
	// Already loaded.
	return;
}

define( 'BINDERY_VERSION', '0.3.6' );
define( 'BINDERY_FILE', __FILE__ );
define( 'BINDERY_DIR', plugin_dir_path( __FILE__ ) );
define( 'BINDERY_URL', plugin_dir_url( __FILE__ ) );

/**
 * PSR-4 autoloader for the Bindery\ namespace, mapped to src/.
 *
 * Runtime loading does not depend on Composer; composer.json exists only for
 * dev tooling (PHPUnit, PHPStan, PHPCS). If a Composer autoloader is present it
 * is loaded first and this fallback covers anything it misses.
 */
$bindery_composer = BINDERY_DIR . 'vendor/autoload.php';
if ( is_file( $bindery_composer ) ) {
	require $bindery_composer;
}

spl_autoload_register(
	static function ( string $class ): void {
		$prefix = 'Bindery\\';
		$length = strlen( $prefix );

		if ( strncmp( $class, $prefix, $length ) !== 0 ) {
			return;
		}

		$relative = substr( $class, $length );
		$path     = BINDERY_DIR . 'src/' . str_replace( '\\', '/', $relative ) . '.php';

		if ( is_file( $path ) ) {
			require $path;
		}
	}
);

// Public template API (global functions theme authors call).
require BINDERY_DIR . 'src/Support/api.php';

// Lifecycle hooks. Handlers are static so no container is required at (de)activation.
register_activation_hook( __FILE__, array( Activation\Activator::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( Activation\Deactivator::class, 'deactivate' ) );

// Multisite: provision Bindery's table + capabilities on any site created while
// the plugin is network-active.
add_action( 'wp_initialize_site', array( Activation\Activator::class, 'onNewSite' ), 99 );

/**
 * Boot the plugin once all plugins are loaded so extensions can hook in early.
 */
add_action(
	'plugins_loaded',
	static function (): void {
		Plugin::instance( BINDERY_FILE )->boot();
	}
);
