<?php
/**
 * Base unit test case (Brain Monkey lifecycle).
 *
 * @package Bindery
 */

declare(strict_types=1);

namespace Bindery\Tests\Unit;

use Brain\Monkey;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

abstract class TestCase extends PHPUnitTestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}
}
