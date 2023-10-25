<?php

namespace MugoWeb\IbexaBundle\Tests;

use MugoWeb\IbexaBundle\Parser\QueryStringParser;
use MugoWeb\IbexaBundle\Repository\LocationQuery;
use MugoWeb\IbexaBundle\Repository\Query;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use PHPUnit\Exception;

class QueryStringParserTest extends KernelTestCase
{
	public function testBuildWithEmptySingleCondition()
	{
		$condition = QueryStringParser::parseCriterions( 'Subtree:/1/2/' );

		$this->assertInstanceOf(
			'eZ\Publish\API\Repository\Values\Content\Query\Criterion\Subtree',
			$condition
		);

		$condition2 = QueryStringParser::parseCriterions( 'Subtree:[/1/2/,/1/54]' );

		$this->assertEquals(
			'/1/54/',
			$condition2->value[1]
		);
	}

	public function testParseMatchStringNoOperator()
	{
		$queryStringParser = new QueryStringParser();

		$returnVal = $this->callStaticMethod(
			$queryStringParser,
			'parseMatchString',
			array( '100' )
		);

		$expected =
			[
				'operator' => '=',
				'values' => [ '100' ],
			];

		$this->assertEquals( $expected, $returnVal );
	}

	public function testParseMatchStringGreatherThanOperator()
	{
		$queryStringParser = new QueryStringParser();

		$returnVal = $this->callStaticMethod(
			$queryStringParser,
			'parseMatchString',
			array( '>100' )
		);

		$expected =
			[
				'operator' => '>',
				'values' => [ '100' ],
			];

		$this->assertEquals( $expected, $returnVal );
	}

	public function testParseMatchStringInGivenValues()
	{
		$queryStringParser = new QueryStringParser();

		$returnVal = $this->callStaticMethod(
			$queryStringParser,
			'parseMatchString',
			array( '[123,321]' )
		);

		$expected =
			[
				'operator' => 'IN',
				'values' => [ '123', '321' ],
			];

		$this->assertEquals( $expected, $returnVal );
	}

	public function testParseSortClausesLocation()
	{
		$sort = QueryStringParser::parseSortClauses( 'Location\Priority:ASC' );

		$this->assertInstanceOf(
			'eZ\Publish\API\Repository\Values\Content\Query\SortClause\Location\Priority',
			$sort[ 0 ]
		);

		$this->assertEquals( 'ascending', $sort[0]->direction );
	}

	public function testParseSortClausesField()
	{
		$sort = QueryStringParser::parseSortClauses( 'Field.article.publish_date:desc' );

		$this->assertInstanceOf(
			'eZ\Publish\API\Repository\Values\Content\Query\SortClause\Field',
			$sort[ 0 ]
		);

		$this->assertEquals( 'publish_date', $sort[ 0 ]->targetData->fieldIdentifier );
		$this->assertEquals( 'descending', $sort[0]->direction );
	}

	private function callStaticMethod( $obj, $name, array $args )
	{
		$class = new \ReflectionClass( $obj );
		$method = $class->getMethod( $name );
		$method->setAccessible( true );
		return $method->invokeArgs( null, $args );
	}
}
