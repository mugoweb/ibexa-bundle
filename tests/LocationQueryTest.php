<?php

namespace MugoWeb\IbexaBundle\Tests;

use eZ\Publish\API\Repository\Values\Content\Query\Criterion\ParentLocationId;
use eZ\Publish\API\Repository\Values\Filter\Filter;
use MugoWeb\IbexaBundle\Parser\QueryStringParser;
use MugoWeb\IbexaBundle\Repository\LocationQuery;
use MugoWeb\IbexaBundle\Repository\Query;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use PHPUnit\Exception;

/*
 * TODO: move the tests to the QueryStringParser.
 */
class LocationQueryTest extends KernelTestCase
{
    public function testQueryBuildWithEmptyString()
    {
        $locationQuery = Query::build( '' );
        $this->assertInstanceOf( 'eZ\Publish\API\Repository\Values\Content\Query', $locationQuery );
    }

    public function testBuildWithEmptyString()
    {
        $locationQuery = LocationQuery::build( '' );
        $this->assertInstanceOf( 'eZ\Publish\API\Repository\Values\Content\LocationQuery', $locationQuery );

        $locationQuery = LocationQuery::build( ' ' );
        $this->assertInstanceOf( 'eZ\Publish\API\Repository\Values\Content\LocationQuery', $locationQuery );
    }

    public function testBuildWithEmptySingleCondition()
    {
        $locationQuery = LocationQuery::build( 'Subtree:/1/2/' );

        $this->assertInstanceOf(
            'eZ\Publish\API\Repository\Values\Content\Query\Criterion\Subtree',
            $locationQuery->query
        );
    }

    public function testBuildMissingOperator()
    {
        $this->expectException( \Exception::class );

        $locationQuery = LocationQuery::build( 'Subtree:/1/2/ Subtree:/1/2/' );
    }

    public function testBuildSubtreeMissingEndingSlash()
    {
        $locationQuery = LocationQuery::build( 'Subtree:/1/2' );

        $this->assertEquals( '/1/2/', $locationQuery->query->value[ 0 ] );
    }

    public function testBuildVisibilityConditionHidden()
    {
        $locationQuery = LocationQuery::build( 'Visibility:hidden' );

        $this->assertEquals( 1, $locationQuery->query->value[ 0 ] );
    }

    public function testBuildVisibilityConditionVisible()
    {
        $locationQuery = LocationQuery::build( 'Visibility:visible' );

        $this->assertEquals( 0, $locationQuery->query->value[ 0 ] );
    }

    public function testBuildQueryStringWithBrackets()
    {
        $locationQuery = LocationQuery::build( 'Subtree:/1/2/ AND ( ContentTypeIdentifier:article OR ContentTypeIdentifier:blog_post )' );

        $this->assertInstanceOf(
            'eZ\Publish\API\Repository\Values\Content\Query\Criterion\LogicalOr',
            $locationQuery->query->criteria[ 1 ]
        );
    }

    public function testBuildQueryStringWithBracketsSingleCondition()
    {
        $locationQuery1 = LocationQuery::build( '( ContentTypeIdentifier:article )' );
        $locationQuery2 = LocationQuery::build( 'ContentTypeIdentifier:article' );

        $this->assertEquals( $locationQuery1, $locationQuery2 );
    }

    public function testBuildQueryStringWithQuotedString()
    {
        $locationQuery = LocationQuery::build( 'Field.identifier:"my quoted string" OR Field.identifier2:\'my 2nd quoted string\'' );

        $this->assertEquals(
            'my quoted string',
            $locationQuery->query->criteria[0]->value
        );
    }

    public function testBuildQueryStringGreaterThanPublishDate()
    {
        $locationQuery = LocationQuery::build( 'DatePublished:>2020-10-20' );

        $this->assertTrue( $locationQuery->query->value[0] > 0 );
    }

    public function testBuildQueryStringMultipleMatches()
    {
        $locationQuery = LocationQuery::build( 'ContentTypeIdentifier:[article,blog_post]' );

        $this->assertEquals(
            [ 'article', 'blog_post' ],
            $locationQuery->query->value
        );
    }

    public function testBuildQueryStringMultipleMatchesButSingleValue()
    {
        $locationQuery = LocationQuery::build( 'ContentTypeIdentifier:[article]' );

        $this->assertEquals(
            [ 'article' ],
            $locationQuery->query->value
        );
    }

	public function testSimpleSortString()
	{
		$locationQuery = LocationQuery::build( '', 'DatePublished:ASC' );

		$this->assertInstanceOf(
			'eZ\Publish\API\Repository\Values\Content\Query\SortClause\DatePublished',
			$locationQuery->sortClauses[ 0 ]
		);
	}

	public function testFilter()
	{
		$filter = (new Filter())->withCriterion( QueryStringParser::parseCriterions( 'ContentTypeIdentifier:article' ) );

		$this->assertInstanceOf(
			'eZ\Publish\SPI\Repository\Values\Filter\FilteringCriterion',
			$filter->getCriterion()
		);
	}
}
