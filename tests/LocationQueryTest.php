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

    public function testParseMatchStringNoOperator()
    {
        $locationQuery = new LocationQuery();

        $returnVal = $this->callStaticMethod(
            $locationQuery,
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
        $locationQuery = new LocationQuery();

        $returnVal = $this->callStaticMethod(
            $locationQuery,
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
        $locationQuery = new LocationQuery();

        $returnVal = $this->callStaticMethod(
            $locationQuery,
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

    public function testSimpleSortString()
    {
        $locationQuery = LocationQuery::build( '', 'DatePublished:ASC' );

        $this->assertInstanceOf(
            'eZ\Publish\API\Repository\Values\Content\Query\SortClause\DatePublished',
            $locationQuery->sortClauses[ 0 ]
        );
    }

    private function callStaticMethod( $obj, $name, array $args )
    {
        $class = new \ReflectionClass( $obj );
        $method = $class->getMethod( $name );
        $method->setAccessible( true );
        return $method->invokeArgs( null, $args );
    }
}
