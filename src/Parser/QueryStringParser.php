<?php

namespace MugoWeb\IbexaBundle\Parser;

use ReflectionClass;
use eZ\Publish\API\Repository\Values\Content\Query as eZQuery;
use eZ\Publish\SPI\Repository\Values\Filter\FilteringCriterion;
use eZ\Publish\API\Repository\Values\Filter\Filter;

class QueryStringParser
{
	protected static $subQueries;
	protected static $quotedStrings;

	static public function getQueryObject(
		string $objectType,
		string $queryString,
		string $sortString = '',
		int $limit = 0,
		int $offset = 0,
		bool $performCount = false
	)
	{
		switch( $objectType )
		{
			case 'Query':
			case 'LocationQuery':
			{
				$className = 'eZ\Publish\API\Repository\Values\Content\\' . $objectType;
				$query = new $className;
				$query->query = self::parseCriterions( $queryString );
				$query->performCount = $performCount;

				if( $sortString )
				{
					$query->sortClauses = self::parseSortClauses( $sortString );
				}

				if( $limit )
				{
					$query->limit = $limit;
				}

				if( $offset )
				{
					$query->offset = $offset;
				}

				return $query;
			}
			break;

			case 'Filter':
			{
				$filter = new Filter();
				$filter->withCriterion( self::parseCriterions( $queryString ) );

				if( $sortString )
				{
					$filter->withSortClause( self::parseSortClauses( $sortString ) );
				}

				if( $limit )
				{
					$filter->withLimit( $limit );
				}

				if( $offset )
				{
					$filter->withOffset( $offset );
				}

				return $filter;
			}
			break;

			default:
				// unsupported objectType
				return null;
		}
	}

	/**
	 *  * Conditions:
	 * - Subtree
	 *      Example: Subtree:/1/2/
	 * - ParentLocationId
	 * - ContentTypeIdentifier
	 * - Visibility
	 *      Example for visible content: Visibility:visible
	 *      Example for hidden content: Visibility:hidden
	 * - Field.<identifier> (search only - not filter)
	 *      Example: Field.name:"Top News"
	 *      Example: Field.name:~<substring>
	 * - DatePublished
	 * - DateModified
	 *      Example: DatePublished:>2020-10-20
	 *
	 * See more criterions:
	 * https://github.com/ibexa/core/tree/main/src/contracts/Repository/Values/Content/Query/Criterion
	 *
	 * @param string $queryString
	 * @return eZQuery\CriterionInterface|FilteringCriterion|null
	 * @throws \Exception
	 */
	static public function parseCriterions( string $queryString )
	{
		$queryString = trim( $queryString );

		if( $queryString )
		{
			// identifies all quoted strings
			preg_match_all( '/(["\'])((\\\\{2})*|(.*?[^\\\\](\\\\{2})*))\1/', $queryString, $matches );

			if( !empty( $matches[0] ) )
			{
				foreach( $matches[0] as $index => $match )
				{
					// Remove quotes
					$unQuotedString = trim( substr( $match, 1, -1 ) );

					$id = md5( $index . '_' . $unQuotedString );
					self::$quotedStrings[ $id ] = $unQuotedString;
					$queryString = str_replace( $match, '"'. $id . '"', $queryString );
				}
			}

			return self::parseSimplifiedQueryString( $queryString );
		}

		return null;
	}

	/**
	 * Sort Fields - eZ\Publish\API\Repository\Values\Content\Query\SortClause\*
	 * - ContentName
	 * - DatePublished
	 * - DateModified
	 * - Location\Priority
	 * - Field.<content type identifier>.<field identifier>
	 * - CustomField.<field identifier> (only for index based search)
	 * - $location->sortField . ':' . $location->sortOrder (for example "9:1")
	*/
	static public function parseSortClauses( string $sortString )
	{
		list( $method, $sortField, $contentTypeIdentifier, $fieldIdentifier ) =
			self::parseSortClausesParts( $sortString );

		$myClass = 'eZ\Publish\API\Repository\Values\Content\Query\SortClause\\' . $sortField;
		$refl = new \ReflectionClass( $myClass );

		switch( $sortField )
		{
			case 'Field':
			{
				$sortClause = $refl->newInstanceArgs( [ $contentTypeIdentifier, $fieldIdentifier, $method ] );
			}
			break;

			case 'CustomField':
			{
				$sortClause = $refl->newInstanceArgs( [ $fieldIdentifier, $method ] );
			}
			break;

			default:
			{
				$sortClause = $refl->newInstanceArgs( [ $method ] );
			}
		}

		return [ $sortClause ];
	}

	static protected function parseSortClausesParts( string $sortString ) : array
	{
		$return =
			[
				'method' => null,
				'sortField' => null,
				'contentTypeIdentifier' => null,
				'fieldIdentifier' => null,
			];

		$methods =
			[
				'ASC' => eZQuery::SORT_ASC,
				'DESC' => eZQuery::SORT_DESC,
				'0' => eZQuery::SORT_DESC,
				'1' => eZQuery::SORT_ASC,
			];

		$parts = explode( ':', $sortString );
		$method = $methods[ strtoupper( trim( $parts[1] ) ) ] ?? eZQuery::SORT_ASC;

		if( $method )
		{
			$return[ 'method' ] = $method;

			$sortFieldParts = explode( '.', $parts[0] );

			switch( count( $sortFieldParts ) )
			{
				case 1:
					{
						if( preg_match( '#\d#', $sortFieldParts[0], $matches ) )
						{
							$sortFieldMap =
								[
									1 => 'Location\Path',
									2 => 'DatePublished',
									3 => 'DateModified',
									4 => 'SectionIdentifier',
									5 => 'Location\Depth',
									8 => 'Location\Priority',
									9 => 'ContentName',
								];

							$return[ 'sortField' ] = $sortFieldMap[ $sortFieldParts[0] ] ?? '';
						}
						else
						{
							$return[ 'sortField' ] = $sortFieldParts[0];
						}
					}
					break;

				case 2:
					{
						$return[ 'sortField' ] = $sortFieldParts[0];
						$return[ 'fieldIdentifier' ] = $sortFieldParts[1];
					}
					break;

				case 3:
					{
						$return[ 'sortField' ] = $sortFieldParts[0];
						$return[ 'contentTypeIdentifier' ] = $sortFieldParts[1];
						$return[ 'fieldIdentifier' ] = $sortFieldParts[2];
					}
					break;
			}
		}

		return array_values( $return );
	}

	static protected function parseSimplifiedQueryString( $queryString )
	{
		// resolve most outer brackets
		// TODO: cannot handle quoted strings
		preg_match( '/\((?:[^)(]+|(?R))*+\)/', $queryString, $matches );

		if( !empty( $matches ) )
		{
			foreach( $matches as $index => $match )
			{
				// Remove brackets
				$subQueryString = trim( substr( $match, 1, -1 ) );

				$id = md5( $index . '_' . $subQueryString );
				self::$subQueries[ $id ] = self::parseSimplifiedQueryString( $subQueryString );
				$queryString = str_replace( $match, 'SUBQUERY:' . $id, $queryString );
				//echo $queryString . "\n";
			}
		}

		// Matching all word of the query sting.
		// For example:
		// - "ContentTypeIdentifier:product_category"
		// - "and"
		preg_match_all( '/(.+?)(?:$|\s+)/', $queryString, $matches );
		$words = $matches[1];

		$conditions = [];
		// Only supporting single type of operator
		$logicalOperator = '';
		foreach( $words as $word )
		{
			//TODO: only matching when outside of a quoted strings
			if( strpos( $word, ':' ) )
			{
				$criterion = self::parsePreOperatorAndCriterion( $word );
				if( $criterion )
				{
					$conditions[] = $criterion;
				}
			}
			elseif( strtoupper( $word ) == 'AND' )
			{
				$logicalOperator = 'LogicalAnd';
			}
			elseif( strtoupper( $word ) == 'OR' )
			{
				$logicalOperator = 'LogicalOr';
			}
		}

		if( $logicalOperator )
		{
			return self::linkConditions( $conditions, $logicalOperator );
		}
		// Single condition without logical operator
		elseif( !$logicalOperator && count( $conditions ) == 1 )
		{
			return $conditions[0];
		}
		else
		{
			throw new \Exception( 'No logical operator found' );
		}
	}

	static protected function parsePreOperatorAndCriterion( $word )
	{
		preg_match( '/(?<operator>!|)(?<criterionName>.*?):(?<value>.*)/', $word, $matches );

		$criterion = self::parseCriterion( $matches[ 'criterionName' ], $matches[ 'value' ] );

		if( $matches[ 'operator' ] == '!' )
		{
			$criterion = new eZQuery\Criterion\LogicalNot( $criterion );
		}

		return $criterion;
	}

	/**
	 * @param string $className either shortcut class name or full class name
	 * @param $matchString
	 * @return mixed|object|string|void|null
	 * @throws \ReflectionException
	 */
	static protected function parseCriterion( string $className, string $matchString )
	{
		// TODO: document this
		if( $className == 'SUBQUERY' )
		{
			return self::$subQueries[ $matchString ];
		}

		$reflectionClass = self::classNameToCriterion( $className );
		$matchData = self::parseMatchString( $matchString );
		$matchData[ 'target' ] = self::addTargetToMatchData( $className );

		return $reflectionClass->newInstanceArgs( self::matchDataToCriterionParameters( $matchData, $reflectionClass ) );
	}

	static protected function matchDataToCriterionParameters( array $matchData, ReflectionClass $reflectionClass ) : array
	{
		switch( $reflectionClass->name )
		{
			case 'eZ\Publish\API\Repository\Values\Content\Query\Criterion\Field':
			case 'Ibexa\Contracts\Core\Repository\Values\Content\Query\Criterion\Field':
				{
					return
						[
							$matchData[ 'target' ],
							$matchData[ 'operator' ],
							$matchData[ 'values' ][ 0 ]
						];
				}
				break;

			case 'eZ\Publish\API\Repository\Values\Content\Query\Criterion\Location\Depth':
			case 'Ibexa\Contracts\Core\Repository\Values\Content\Query\Criterion\Location\Depth':
				{
					return [ '=', $matchData[ 'values' ][ 0 ] ];
				}
				break;

			case 'eZ\Publish\API\Repository\Values\Content\Query\Criterion\Visibility':
			case 'Ibexa\Contracts\Core\Repository\Values\Content\Query\Criterion\Visibility':
				{
					$parameter = strtoupper( $matchData[ 'values' ][ 0 ] ) === 'HIDDEN' ? 1 : 0;
					return [ $parameter ];
				}
				break;

			case 'eZ\Publish\API\Repository\Values\Content\Query\Criterion\DateMetadata':
			case 'Ibexa\Contracts\Core\Repository\Values\Content\Query\Criterion\DateMetadata':
				{
					return
						[
							$matchData[ 'target' ],
							$matchData[ 'operator' ],
							strtotime( $matchData[ 'values' ][ 0 ] ),
						];
				}
				break;

			case 'eZ\Publish\API\Repository\Values\Content\Query\Criterion\Subtree':
			case 'Ibexa\Contracts\Core\Repository\Values\Content\Query\Criterion\Subtree':
				{
					$values = $matchData[ 'values' ];
					array_walk($values, function( &$item, $key )
					{
						$item = rtrim( $item,'/' ) .'/';
					});

					return [ $values ];
				}
				break;

			case 'MugoWeb\IbexaBundle\API\Repository\Values\Content\Query\Criterion\Field':
				{
					return
						[
							$matchData[ 'target' ],
							$matchData[ 'operator' ],
							$matchData[ 'values' ]
						];
				}
				break;

			default:
			{
				return [ $matchData[ 'values' ] ];
			}
		}
	}

	static protected function addTargetToMatchData( string $className ) : string
	{
		$parts = explode( '.', $className );

		if( count( $parts ) > 1 )
		{
			array_shift( $parts );
			return implode( '.', $parts );
		}

		if( $className == 'DatePublished' )
		{
			return 'created';
		}

		if( $className == 'DateModified' )
		{
			return 'modified';
		}

		return '';
	}

	/**
	 * Gets the Reflection Class of a Criterion
	 */
	static protected function classNameToCriterion( string $className ) : ReflectionClass
	{
		if( class_exists( $className ) )
		{
			return new \ReflectionClass( $className );
		}

		// Class name with target info: "Field.title"
		if( strpos( $className, '.' ) )
		{
			$clasNameParts = explode( '.', $className );
			return self::classNameToCriterion( $clasNameParts[0] );
		}

		// Some name mapping
		switch( $className )
		{
			case 'DatePublished':
			case 'DateModified':
			{
				return self::classNameToCriterion( 'eZ\Publish\API\Repository\Values\Content\Query\Criterion\DateMetadata' );
			}
		}

		// Assuming that we have a shortcut to an Ibexa Criterion
		if( count( explode( '\\', $className ) ) < 3 )
		{
			return self::classNameToCriterion( 'eZ\Publish\API\Repository\Values\Content\Query\Criterion\\' . $className );
		}

		throw new \Exception( 'No valid Criterion specified' );
	}

	/**
	 * Supported operators:
	 *  > larger
	 *  > larger or equal
	 *  < smaller
	 *  < smaller or equal
	 *  ~ contains
	 *  [x,y,z] matching against a list of values
	 */
	static protected function parseMatchString( string $matchString ): array
	{
		// Unpackage quoted strings
		preg_match( '/"(.*?)"/', $matchString, $match );

		if( !empty( $match ) )
		{
			return self::parseMatchString( self::$quotedStrings[ $match[1] ] );
		}

		// Simple matchString
		$return =
			[
				'target' => '',
				'operator' => '=',
				'values' => [ $matchString ],
			];

		// MatchString with an operator at the beginning
		$operatorRegEx = '#\s*(>=|>|<|<=|~)\s*(".*?"|.*?)$#';
		preg_match( $operatorRegEx, $matchString, $matches );

		if( isset( $matches[1] ) && isset( $matches[2] ) )
		{
			$operatorMap =
				[
					'>=' => '>=',
					'>'  => '>',
					'<'  => '<',
					'<=' => '<=',
					'~'  => 'contains',
				];

			$return =
				[
					'operator' => $operatorMap[ $matches[1] ],
					'values' => [ $matches[2] ],
				];
		}

		// Matches [123,321] - Matching one of multiple values
		preg_match( '/\[(.*?)\]/', $matchString, $matches );

		if( isset( $matches[1] ) )
		{
			$return =
				[
					'operator' => 'IN',
					'values' => explode( ',', $matches[1] ),
				];
		}


		//TODO: implement [ 100 TO * ]
		// $rangeRegEx = '#\[\s*(".*?"|.*?)\s+..\s+(".*?"|.*?)s*\]#';
		// preg_match( $rangeRegEx, $matchString, $matches );

		return $return;
	}

	static protected function linkConditions( $conditions, $logicalOperator )
	{
		$myClass = 'eZ\Publish\API\Repository\Values\Content\Query\Criterion\\' . $logicalOperator;
		$refl = new \ReflectionClass( $myClass );
		return $refl->newInstanceArgs( [ $conditions ] );
	}

}
