<?php
/**
 * @package Bindery
 */

declare(strict_types=1);

namespace Bindery\Tests\Unit;

use Bindery\Contracts\LocaleProvider;
use Bindery\Contracts\ValueSource;
use Bindery\Fields\FieldContext;
use Bindery\Fields\FieldDefinition;
use Bindery\Fields\Scope;
use Bindery\Fields\ValueResolver;
use Bindery\Locale\LocaleManager;
use Bindery\Sources\SourceRegistry;
use Brain\Monkey\Functions;

final class ValueResolverTest extends TestCase {

	private function resolver( mixed $stored_return ): ValueResolver {
		$source = new class( $stored_return ) implements ValueSource {
			public function __construct( private mixed $ret ) {}
			public function id(): string {
				return 'stored';
			}
			public function resolve( FieldDefinition $definition, FieldContext $context ): mixed {
				return $this->ret;
			}
		};

		$sources = new SourceRegistry();
		$sources->register( $source );

		$provider = new class() implements LocaleProvider {
			public function current(): string {
				return 'en_US';
			}
			public function default(): string {
				return 'en_US';
			}
			public function available(): array {
				return array( 'en_US' => 'EN' );
			}
		};

		return new ValueResolver( $sources, new LocaleManager( $provider ) );
	}

	private function def( mixed $default ): FieldDefinition {
		return new FieldDefinition( 'k', 'text', null, $default, Scope::Page, 'stored', true, 'cap', null, array() );
	}

	public function test_override_wins_over_default(): void {
		Functions\when( 'apply_filters' )->returnArg( 2 );
		$this->assertSame( 'OVERRIDE', $this->resolver( 'OVERRIDE' )->resolve( $this->def( 'DEFAULT' ), 0 ) );
	}

	public function test_falls_back_to_default_when_unset(): void {
		Functions\when( 'apply_filters' )->returnArg( 2 );
		$this->assertSame( 'DEFAULT', $this->resolver( null )->resolve( $this->def( 'DEFAULT' ), 0 ) );
	}

	public function test_empty_string_override_beats_default(): void {
		Functions\when( 'apply_filters' )->returnArg( 2 );
		$this->assertSame( '', $this->resolver( '' )->resolve( $this->def( 'DEFAULT' ), 0 ) );
	}

	public function test_structured_value_passes_through(): void {
		Functions\when( 'apply_filters' )->returnArg( 2 );
		$rows = array( array( 'title' => 'A' ), array( 'title' => 'B' ) );
		$this->assertSame( $rows, $this->resolver( $rows )->resolve( $this->def( array() ), 0 ) );
	}
}
