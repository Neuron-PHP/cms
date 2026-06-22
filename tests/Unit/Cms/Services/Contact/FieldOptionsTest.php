<?php

namespace Tests\Unit\Cms\Services\Contact;

use Neuron\Cms\Services\Contact\FieldOptions;
use PHPUnit\Framework\TestCase;

class FieldOptionsTest extends TestCase
{
	public function testFlatStringOptionsNormalizeToValueLabelPairs(): void
	{
		$groups = FieldOptions::groups( [ 'options' => [ 'A', 'B' ] ] );

		$this->assertCount( 1, $groups );
		$this->assertSame( '', $groups[0]['label'] );
		$this->assertSame( [ 'value' => 'A', 'label' => 'A' ], $groups[0]['options'][0] );
	}

	public function testValueLabelPairsArePreserved(): void
	{
		$groups = FieldOptions::groups( [ 'options' => [ [ 'value' => 'a', 'label' => 'Option A' ] ] ] );

		$this->assertSame( 'a', $groups[0]['options'][0]['value'] );
		$this->assertSame( 'Option A', $groups[0]['options'][0]['label'] );
	}

	public function testGroupedOptionsAreReturnedPerGroup(): void
	{
		$field = [
			'groups' => [
				[ 'label' => 'G1', 'options' => [ 'a' ] ],
				[ 'label' => 'G2', 'options' => [ [ 'value' => 'b', 'label' => 'B' ] ] ]
			]
		];

		$groups = FieldOptions::groups( $field );

		$this->assertCount( 2, $groups );
		$this->assertSame( 'G1', $groups[0]['label'] );
		$this->assertSame( 'G2', $groups[1]['label'] );
	}

	public function testAllowedValuesFlattensAcrossGroups(): void
	{
		$field = [
			'groups' => [
				[ 'label' => 'G1', 'options' => [ 'a', 'b' ] ],
				[ 'label' => 'G2', 'options' => [ [ 'value' => 'c', 'label' => 'C' ] ] ]
			]
		];

		$this->assertSame( [ 'a', 'b', 'c' ], FieldOptions::allowedValues( $field ) );
	}

	public function testLabelsForPrefixesGroupLabel(): void
	{
		$field = [
			'groups' => [
				[ 'label' => 'Sarasota', 'options' => [ [ 'value' => 'sar_judge', 'label' => 'Judge' ] ] ],
				[ 'label' => 'South', 'options' => [ [ 'value' => 'south_judge', 'label' => 'Judge' ] ] ]
			]
		];

		$labels = FieldOptions::labelsFor( $field, [ 'sar_judge', 'south_judge' ] );

		$this->assertSame( [ 'Sarasota - Judge', 'South - Judge' ], $labels );
	}

	public function testLabelsForFallsBackToRawValueWhenUnknown(): void
	{
		$labels = FieldOptions::labelsFor( [ 'options' => [ 'a' ] ], [ 'a', 'zzz' ] );

		$this->assertSame( [ 'a', 'zzz' ], $labels );
	}

	public function testEmptyOptionValuesAreSkipped(): void
	{
		$field = [ 'options' => [ '', [ 'value' => '', 'label' => 'x' ], 'keep' ] ];

		$this->assertSame( [ 'keep' ], FieldOptions::allowedValues( $field ) );
	}
}
