<?php

namespace MugoWeb\IbexaBundle\Repository;

use eZ\Publish\API\Repository\Values\Content\Query as eZQuery;
use MugoWeb\IbexaBundle\Parser\QueryStringParser;

/**
 * The idea is to simplify the code to create LocationQueries.
 *
 * Conditions:
 * - Subtree
 *      Example: Subtree:/1/2/
 * - ParentLocationId
 * - ContentTypeIdentifier
 * - Visibility
 *      Example for visible content: Visibility:visible
 *      Example for hidden content: Visibility:hidden
 * - Field.<identifier>
 * - DatePublished
 * - DateModified
 *      Example: DatePublished:>2020-10-20
 *
 * Sort Fields - eZ\Publish\API\Repository\Values\Content\Query\SortClause\*
 * - ContentName
 * - DatePublished
 * - DateModified
 * - Location\Priority
 *
 */
class Query extends eZQuery
{
    protected static $subQueries;
    protected static $quotedStrings;

    /**
     * @param string $queryString
     * @param string $sortString
     * @param int $limit
     * @return eZLocationQuery
     */
    static public function build(
        string $queryString,
        string $sortString = '',
        int $limit = 0
    ) : eZQuery
    {
        $query = new eZQuery();

        return self::buildByInstance(
            $query,
            $queryString,
            $sortString,
            $limit
        );
    }

    static public function buildByInstance(
        eZQuery $query,
        string $queryString,
        string $sortString = '',
        int $limit = 0
    ) : eZQuery
    {
		$query->query = QueryStringParser::parseCriterions( $queryString );

        if( $sortString )
        {
            $query->sortClauses = QueryStringParser::parseSortClauses( $sortString );
        }

        if( $limit )
        {
            $query->limit = $limit;
        }

        return $query;
    }
}
