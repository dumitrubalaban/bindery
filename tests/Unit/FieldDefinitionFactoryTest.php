<?php
/**
 * @package Bindery
 */

declare(strict_types=1);

namespace Bindery\Tests\Unit;

use Bindery\Fields\FieldDefinitionFactory;
use Bindery\Fields\FieldTypeRegistry;
use Bindery\Fields\Scope;
use Bindery\Fields\Types\NumberField;
use Bindery\Fields\Types\RichTextField;
use Bindery\Fields\Types\TextField;
use Bindery\Fields\Types\UrlField;

final class FieldDefinitionFactoryTest extends TestCase {

	private function factory(): FieldDefinitionFactory {
		$types = new FieldTypeRegistry();
		foreach ( array( new TextField(), new RichTextField(), new UrlField(), new NumberField() ) as $type ) {
			$types->register( $type );
		}
		return new FieldDefinitionFactory( $types );
	}

	public function test_canonical_type_maps_tags_and_link(): void {
		$this->assertSame( 'text', FieldDefinitionFactory::canonicalType( 'h2' ) );
		$this->assertSame( 'text', FieldDefinitionFactory::canonicalType( 'blockquote' ) );
		$this->assertSame( 'url', FieldDefinitionFactory::canonicalType( 'link' ) );
		$this->assertSame( 'image', FieldDefinitionFactory::canonicalType( 'image' ) );
	}

	public function test_html_tag_shorthand_becomes_tagged_text(): void {
		$def = $this->factory()->create( 'hero', array( 'type' => 'h1' ) );
		$this->assertSame( 'text', $def->type );
		$this->assertSame( 'h1', $def->tag );
	}

	public function test_link_shorthand_becomes_url_anchor(): void {
		$def = $this->factory()->create( 'cta', array( 'type' => 'link' ) );
		$this->assertSame( 'url', $def->type );
		$this->assertSame( 'a', $def->tag );
	}

	public function test_unknown_type_falls_back_to_text(): void {
		$def = $this->factory()->create( 'x', array( 'type' => 'totally-unknown' ) );
		$this->assertSame( 'text', $def->type );
	}

	public function test_defaults_and_scope(): void {
		$def = $this->factory()->create( 'phone', array( 'default' => 42, 'scope' => 'global' ) );
		$this->assertSame( '42', $def->default ); // text normalises scalars to string
		$this->assertSame( Scope::Global, $def->scope );
		$this->assertTrue( $def->localeAware );
		$this->assertSame( 'stored', $def->source );
	}

	public function test_unknown_args_are_kept_as_extras(): void {
		$def = $this->factory()->create( 'k', array( 'type' => 'text', 'option' => 'foo', 'attrs' => array( 'id' => 'x' ) ) );
		$this->assertSame( 'foo', $def->arg( 'option' ) );
		$this->assertSame( array( 'id' => 'x' ), $def->arg( 'attrs' ) );
	}

	public function test_localized_flag_can_be_disabled(): void {
		$def = $this->factory()->create( 'k', array( 'localized' => false ) );
		$this->assertFalse( $def->localeAware );
	}
}
