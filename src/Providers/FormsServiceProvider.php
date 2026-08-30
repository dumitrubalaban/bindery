<?php
/**
 * Forms service provider.
 *
 * @package Bindery
 */

declare(strict_types=1);

namespace Bindery\Providers;

use Bindery\Container;
use Bindery\Contracts\ServiceProvider;
use Bindery\Rest\FormController;

/**
 * Registers the form-submissions post type and the public submit endpoint.
 */
final class FormsServiceProvider implements ServiceProvider {

	public function register( Container $container ): void {
		$container->singleton( FormController::class, static fn (): FormController => new FormController() );
	}

	public function boot( Container $container ): void {
		add_action( 'init', array( $this, 'registerSubmissionType' ) );

		add_action(
			'rest_api_init',
			static function () use ( $container ): void {
				$container->get( FormController::class )->registerRoutes();
			}
		);
	}

	/**
	 * A private post type that stores incoming form submissions, visible to
	 * editors+ in wp-admin.
	 */
	public function registerSubmissionType(): void {
		register_post_type(
			FormController::POST_TYPE,
			array(
				'labels'          => array(
					'name'          => __( 'Form Submissions', 'bindery' ),
					'singular_name' => __( 'Submission', 'bindery' ),
					'menu_name'     => __( 'Submissions', 'bindery' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => true,
				'menu_icon'       => 'dashicons-email-alt',
				'supports'        => array( 'title', 'editor' ),
				'capability_type' => 'post',
				'map_meta_cap'    => true,
				'rewrite'         => false,
				'query_var'       => false,
			)
		);
	}
}
