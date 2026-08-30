<?php
/**
 * @package Bindery
 */

declare(strict_types=1);

namespace Bindery\Tests\Unit;

use Bindery\Fields\Scope;

final class ScopeTest extends TestCase {

	public function test_from_loose_resolves_known_strings(): void {
		$this->assertSame( Scope::Page, Scope::fromLoose( 'page' ) );
		$this->assertSame( Scope::Global, Scope::fromLoose( 'global' ) );
	}

	public function test_from_loose_defaults_to_page(): void {
		$this->assertSame( Scope::Page, Scope::fromLoose( 'nonsense' ) );
		$this->assertSame( Scope::Page, Scope::fromLoose( null ) );
	}

	public function test_from_loose_passes_through_enum(): void {
		$this->assertSame( Scope::Global, Scope::fromLoose( Scope::Global ) );
	}
}
