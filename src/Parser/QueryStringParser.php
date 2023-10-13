<?php

namespace MugoWeb\IbexaBundle\Parser;

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
		int $limit = 0
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

				if( $sortString )
				{
					$query->sortClauses = self::parseSortClauses( $sortString );
				}

				if( $limit )
				{
					$query->limit = $limit;
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
	 * - Field.<identifier>
	 * - DatePublished
	 * - DateModified
	 *      Example: DatePublished:>2020-10-20
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

	/*
	 * Sort Fields - eZ\Publish\API\Repository\Values\Content\Query\SortClause\*
	 * - ContentName
	 * - DatePublished
	 * - DateModified
	 * - Location\Priority
	*/
	static public function parseSortClauses( string $sortString )
	{
		$parts = explode( ':', $sortString );
		$field = $parts[0];
		$method = strtoupper( trim( $parts[1] ) );

		$methods =
			[
				'ASC' => eZQuery::SORT_ASC,
				'DESC' => eZQuery::SORT_DESC,
			];

		$myClass = 'eZ\Publish\API\Repository\Values\Content\Query\SortClause\\' . $field;
		$refl = new \ReflectionClass( $myClass );
		$sortClause = $refl->newInstanceArgs( [ $methods[ strtoupper( $method ) ] ] );

		return [ $sortClause ];
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
				$conditions[] = self::preParseCriterion( $word );
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

	static protected function preParseCriterion( $word )
	{
		preg_match( '/(?<operator>!|)(?<field>.*?):(?<value>.*)/', $word, $matches );

		if( $matches[ 'operator' ] == '!' )
		{
			return new eZQuery\Criterion\LogicalNot(
				self::parseCriterion( $matches[ 'field' ], $matches[ 'value' ] )
			);
		}
		else
		{
			return self::parseCriterion( $matches[ 'field' ], $matches[ 'value' ] );
		}
	}

	static protected function parseCriterion( string $fieldName, $matchString )
	{
		$matchData = self::parseMatchString( $matchString );

		// $fieldName references a content type field
		if( strpos( $fieldName, '.' ) )
		{
			$parts = explode( '.', $fieldName );
			$fieldName = $parts[0];
			$fieldIdentifier = $parts[1];

			$myClass = 'eZ\Publish\API\Repository\Values\Content\Query\Criterion\Field';
			$refl = new \ReflectionClass( $myClass );
			return $refl->newInstanceArgs(
				[
					$fieldIdentifier,
					eZQuery\Criterion\Operator::CONTAINS,
					$matchData[ 'values' ][ 0 ]
				]
			);
		}
		elseif( $fieldName == 'SUBQUERY' )
		{
			return self::$subQueries[ $matchString ];
		}
		else
		{
			switch( $fieldName )
			{
				case 'Location\Depth':
					{
						$myClass = 'eZ\Publish\API\Repository\Values\Content\Query\Criterion\Location\Depth';
						$refl = new \ReflectionClass( $myClass );
						return $refl->newInstanceArgs( [ '=', $matchData[ 'values' ][ 0 ] ] );
					}
					break;

				case 'Visibility':
					{
						$myClass = 'eZ\Publish\API\Repository\Values\Content\Query\Criterion\\' . $fieldName;
						$refl = new \ReflectionClass( $myClass );
						$parameter = strtoupper( $matchData[ 'values' ][ 0 ] ) === 'HIDDEN' ? 1 : 0;
						return $refl->newInstanceArgs( [ $parameter ] );
					}
					break;

				case 'DatePublished':
				case 'DateModified':
					{
						$targetMetadata = $fieldName === 'DatePublished' ? 'created' : 'modified';

						$myClass = 'eZ\Publish\API\Repository\Values\Content\Query\Criterion\DateMetadata';
						$refl = new \ReflectionClass( $myClass );
						$parameters =
							[
								$targetMetadata,
								$matchData[ 'operator' ],
								strtotime( $matchData[ 'values' ][ 0 ] ),
							];
						return $refl->newInstanceArgs( $parameters );
					}
					break;

				case 'Subtree':
					{
						$normalizedString = rtrim( $matchData[ 'values' ][ 0 ],'/' ) .'/';

						$myClass = 'eZ\Publish\API\Repository\Values\Content\Query\Criterion\\' . $fieldName;
						$refl = new \ReflectionClass( $myClass );
						return $refl->newInstanceArgs( [ $normalizedString ] );
					}
					break;

				default:
				{
					$myClass = 'eZ\Publish\API\Repository\Values\Content\Query\Criterion\\' . $fieldName;
					$refl = new \ReflectionClass( $myClass );
					return $refl->newInstanceArgs( [ $matchData[ 'values' ] ] );
				}
			}
		}
	}

	static protected function parseMatchString( string $matchString ): array
	{
		// Simple matchString
		$return =
			[
				'operator' => '=',
				'values' => [ $matchString ],
			];

		// Unpackage quoted strings
		preg_match( '/"(.*?)"/', $matchString, $match );

		if( !empty( $match ) )
		{
			$return[ 'values' ][0] = self::$quotedStrings[ $match[1] ];
		}
		else
		{
			// MatchString with an operator at the beginning
			$operatorRegEx = '#\s*(>=|>|<|<=)\s*(".*?"|.*?)$#';
			preg_match( $operatorRegEx, $matchString, $matches );

			if( isset( $matches[1] ) && isset( $matches[2] ) )
			{
				$return =
					[
						'operator' => $matches[1],
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
		}

		return $return;
	}

	static protected function linkConditions( $conditions, $logicalOperator )
	{
		$myClass = 'eZ\Publish\API\Repository\Values\Content\Query\Criterion\\' . $logicalOperator;
		$refl = new \ReflectionClass( $myClass );
		return $refl->newInstanceArgs( [ $conditions ] );
	}

}
