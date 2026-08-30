<?php
/**
 * Block patterns service provider.
 *
 * @package Bindery
 */

declare(strict_types=1);

namespace Bindery\Providers;

use Bindery\Container;
use Bindery\Contracts\ServiceProvider;

/**
 * Registers ready-made, editable Bindery layouts as block patterns so a
 * non-developer can build a full multilingual page by inserting a pattern and
 * editing the content inline — no code, no field declarations.
 */
final class PatternsServiceProvider implements ServiceProvider {

	public function register( Container $container ): void {
		// No services to bind.
	}

	public function boot( Container $container ): void {
		add_action( 'init', array( $this, 'registerPatterns' ), 11 );
	}

	public function registerPatterns(): void {
		if ( ! function_exists( 'register_block_pattern' ) ) {
			return;
		}

		if ( function_exists( 'register_block_pattern_category' ) ) {
			register_block_pattern_category( 'bindery', array( 'label' => __( 'Bindery', 'bindery' ) ) );
		}

		$hero = <<<'HTML'
<!-- wp:bindery/section {"fieldKey":"hero_bg","overlay":55,"minHeight":"480px","align":"full"} -->
<!-- wp:bindery/editable-text {"fieldKey":"hero_title","tag":"h1","placeholder":"Your big headline goes here"} /-->
<!-- wp:bindery/editable-text {"fieldKey":"hero_sub","tag":"p","placeholder":"A short supporting sentence that sells the idea."} /-->
<!-- wp:bindery/button {"fieldKey":"hero_cta"} /-->
<!-- /wp:bindery/section -->
HTML;

		$features = <<<'HTML'
<!-- wp:bindery/editable-text {"fieldKey":"features_title","tag":"h3","placeholder":"Why choose us"} /-->
<!-- wp:bindery/cards {"fieldKey":"features","columns":3,"subfields":{"title":"h4","body":"p"}} /-->
HTML;

		$testimonials = <<<'HTML'
<!-- wp:bindery/editable-text {"fieldKey":"tst_title","tag":"h3","placeholder":"What our clients say"} /-->
<!-- wp:bindery/slider {"fieldKey":"testimonials","subfields":{"title":"h3","text":"p"}} /-->
HTML;

		$contact = <<<'HTML'
<!-- wp:bindery/editable-text {"fieldKey":"contact_title","tag":"h3","placeholder":"Get in touch"} /-->
<!-- wp:bindery/form {"fieldKey":"contact"} /-->
HTML;

		$patterns = array(
			'bindery/hero'         => array( __( 'Bindery: Hero section', 'bindery' ), $hero ),
			'bindery/features'     => array( __( 'Bindery: Feature cards', 'bindery' ), $features ),
			'bindery/testimonials' => array( __( 'Bindery: Testimonials slider', 'bindery' ), $testimonials ),
			'bindery/contact'      => array( __( 'Bindery: Contact form', 'bindery' ), $contact ),
			'bindery/landing'      => array(
				__( 'Bindery: Full landing page', 'bindery' ),
				$hero . "\n" . $features . "\n" . $testimonials . "\n" . $contact,
			),
		);

		foreach ( $patterns as $name => $pattern ) {
			register_block_pattern(
				$name,
				array(
					'title'      => $pattern[0],
					'categories' => array( 'bindery' ),
					'content'    => $pattern[1],
				)
			);
		}
	}
}
