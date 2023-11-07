<?php

namespace MugoWeb\IbexaBundle\Repository;

use eZ\Publish\API\Repository\Values\Content\LocationQuery as eZLocationQuery;

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
class LocationQuery extends Query
{
	/**
	 * @depracated Use QueryParser instead
	 * @param string $queryString
	 * @param string $sortString
	 * @param int $limit
	 * @return eZLocationQuery
	 */
    static public function build(
        string $queryString,
        string $sortString = '',
        int $limit = 0
    ) : eZLocationQuery
    {
        $query = new eZLocationQuery();

        return self::buildByInstance(
            $query,
            $queryString,
            $sortString,
            $limit
        );
    }
}
