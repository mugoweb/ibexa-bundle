<?php

namespace MugoWeb\IbexaBundle\Repository;

use PHPUnit\Util\Exception;
use eZ\Publish\API\Repository\Values\Content\Query;
use eZ\Publish\API\Repository\Values\Content\LocationQuery as eZLocationQuery;

/**
 * The idea is to simplify the code to create LocationQueries.
 *
 * Conditions:
 * - Subtree
 *      Example: Subtree:/1/2/
 * - ParentLocationId
 * - ContentTypeIdentifier
 * - Field.<identifier>
 *
 * Sort Fields - eZ\Publish\API\Repository\Values\Content\Query\SortClause\*
 * - ContentName
 * - DatePublished
 * - DateModified
 * - Location\Priority
 *
 */
class LocationQuery extends eZLocationQuery
{
    protected static $subQueries;

    /**
     * @param string $queryString
     * @param string $sortString
     * @param int $limit
     * @return eZLocationQuery
     */
    static public function build( string $queryString, string $sortString = '', int $limit = 0 ) : eZLocationQuery
    {
        $locationQuery = new self();

        $locationQuery->query = self::parseQueryString( $queryString );

        if( $sortString )
        {
            self::parseSorting( $locationQuery, $sortString );
        }

        if( $limit )
        {
            $locationQuery->limit = $limit;
        }

        return $locationQuery;
    }

    /**
     * @param $queryString
     * @return object
     */
    static protected function parseQueryString( $queryString )
    {
        // resolve brackets
        preg_match( '/\((?:[^)(]+|(?R))*+\)/', $queryString, $matches );

        if( !empty( $matches ) )
    {
            foreach( $matches as $index => $match )
            {
                $subQueryString = trim( substr( $match, 1, -1 ) );

                $id = md5( $index . '_' . $subQueryString );
                self::$subQueries[ $id ] = self::parseQueryString( $subQueryString );
                $queryString = str_replace( $match, 'SUBQUERY:' . $id, $queryString );
            }
        }

        // Matching strings like "ContentTypeIdentifier:product_category"
        preg_match_all( '/(.+?)(?:$|\s+)/', $queryString, $matches );
        $words = $matches[1];
        $conditionGroups = [];

        $conditions = [];
        $logicalOperator = '';
        foreach( $words as $word )
        {
            if( strpos( $word, ':' ) )
            {
                $conditions[] = self::parseCondition( $word );
            }
            elseif( $word == 'and' )
            {
                $logicalOperator = 'LogicalAnd';
            }
            elseif( $word == 'or' )
            {
                $logicalOperator = 'LogicalOr';
            }
        }

        if( $logicalOperator )
        {
            return self::linkConditions( $conditions, $logicalOperator );
        }
        else
        {
            throw new Exception( 'No logical operator found' );
        }
    }

    static protected function parseSorting( $locationQuery, $sortString )
    {
        $parts = explode( ':', $sortString );
        $field = $parts[0];
        $method = strtoupper( trim( $parts[1] ) );

        $methods =
            [
                'ASC' => Query::SORT_ASC,
                'DESC' => Query::SORT_DESC,
            ];

        $myClass = 'eZ\Publish\API\Repository\Values\Content\Query\SortClause\\' . $field;
        $refl = new \ReflectionClass( $myClass );
        $sortClause = $refl->newInstanceArgs( [ $methods[ strtoupper( $method ) ] ] );

        $locationQuery->sortClauses = [ $sortClause ];
    }

    static protected function parseCondition( $word )
    {
        preg_match( '/(?<operator>!|)(?<field>.*?):(?<value>.*)/', $word, $matches );

        if( $matches[ 'operator' ] == '!' )
        {
            return new Query\Criterion\LogicalNot(
                self::getCriterion( $matches[ 'field' ], $matches[ 'value' ] )
            );
        }
        else
        {
            return self::getCriterion( $matches[ 'field' ], $matches[ 'value' ] );
        }
    }

    /**
     *
     * @param string $fieldName for example 'ParentLocationId'
     * @param $matchString
     * @return object|void
     * @throws \ReflectionException
     */
    static protected function getCriterion( string $fieldName, $matchString )
    {
        if( strpos( $fieldName, '.' ) )
        {
            $parts = explode( '.', $fieldName );
            $fieldName = $parts[0];
            $fieldIdentifier = $parts[1];

            $myClass = 'eZ\Publish\API\Repository\Values\Content\Query\Criterion\Field';
            $refl = new \ReflectionClass( $myClass );
            return $refl->newInstanceArgs( [ $fieldIdentifier, Query\Criterion\Operator::CONTAINS ,$matchString ] );
        }
        elseif( $fieldName == 'SUBQUERY' )
        {
            return self::$subQueries[ $matchString ];
        }
        else
        {
            $myClass = 'eZ\Publish\API\Repository\Values\Content\Query\Criterion\\' . $fieldName;
            $refl = new \ReflectionClass( $myClass );
            return $refl->newInstanceArgs( [ $matchString ] );
        }
    }

    static protected function linkConditions( $conditions, $logicalOperator )
    {
        $myClass = 'eZ\Publish\API\Repository\Values\Content\Query\Criterion\\' . $logicalOperator;
        $refl = new \ReflectionClass( $myClass );
        return $refl->newInstanceArgs( [ $conditions ] );
    }
}