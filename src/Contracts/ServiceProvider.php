<?php
/**
 * Service provider contract.
 *
 * @package Bindery
 */

declare(strict_types=1);

namespace Bindery\Contracts;

use Bindery\Container;

/**
 * A unit of bootable functionality.
 *
 * Providers run in two ordered passes so that every binding exists before any
 * provider wires WordPress hooks against another provider's services:
 *
 *  1. register() — bind services into the container only. No WP hooks.
 *  2. boot()     — wire WordPress hooks; safe to resolve any service.
 */
interface ServiceProvider {

	/**
	 * Bind services into the container. Do not touch WordPress here.
	 */
	public function register( Container $container ): void;

	/**
	 * Wire WordPress hooks. All services are available to resolve.
	 */
	public function boot( Container $container ): void;
}
