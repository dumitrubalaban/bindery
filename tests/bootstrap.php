<?php
/**
 * PHPUnit bootstrap.
 *
 * Pure unit tests: plugin classes autoload via Composer (Bindery\ → src/);
 * WordPress functions are mocked per-test with Brain Monkey. No WP install
 * required.
 *
 * @package Bindery
 */

declare(strict_types=1);

$autoload = dirname( __DIR__ ) . '/vendor/autoload.php';

if ( ! is_file( $autoload ) ) {
	fwrite( STDERR, "Run `composer install` before the test suite.\n" );
	exit( 1 );
}

require $autoload;
