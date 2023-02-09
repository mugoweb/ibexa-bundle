<?php
namespace MugoWeb\IbexaBundle\Tests;

use MugoWeb\IbexaBundle\Repository\LocationQuery;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use PHPUnit\Exception;

class NewsletterGeneratorTest extends KernelTestCase
{
	public function testBuildWithEmptyString()
	{
		$locationQuery = LocationQuery::build( '' );
		$this->assertInstanceOf( 'MugoWeb\IbexaBundle\Repository\LocationQuery', $locationQuery );

		$locationQuery = LocationQuery::build( ' ' );
		$this->assertInstanceOf( 'MugoWeb\IbexaBundle\Repository\LocationQuery', $locationQuery );
	}

	public function testBuildWithEmptySingleCondition()
	{
		$locationQuery = LocationQuery::build( 'Subtree:/1/2/' );

		$this->assertInstanceOf(
			'eZ\Publish\API\Repository\Values\Content\Query\Criterion\Subtree',
			$locationQuery->query[0]
		);
	}

	public function testBuildMissingOperator()
	{
		$this->expectException( \Exception::class );

		$locationQuery = LocationQuery::build( 'Subtree:/1/2/ Subtree:/1/2/' );
	}

	public function testBuildVisibilityConditionHidden()
	{
		$locationQuery = LocationQuery::build( 'Visibility:hidden' );

		$this->assertEquals( 1, $locationQuery->query[0]->value[0 ]);
	}

	public function testBuildVisibilityConditionVisible()
	{
		$locationQuery = LocationQuery::build( 'Visibility:visible' );

		$this->assertEquals( 0, $locationQuery->query[0]->value[0 ]);
	}
}
