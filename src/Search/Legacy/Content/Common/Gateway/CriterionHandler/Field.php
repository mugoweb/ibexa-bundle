<?php

declare(strict_types=1);

namespace MugoWeb\IbexaBundle\Search\Legacy\Content\Common\Gateway\CriterionHandler;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Ibexa\Contracts\Core\Persistence\Content\Type\Handler as ContentTypeHandler;
use Ibexa\Contracts\Core\Repository\Values\Content\Query\Criterion;
use Ibexa\Core\Search\Legacy\Content\Common\Gateway\CriteriaConverter;
use Ibexa\Core\Search\Legacy\Content\Common\Gateway\CriterionHandler;
use Ibexa\Contracts\Core\Persistence\Content\Type\FieldDefinition;
use MugoWeb\IbexaBundle\API\Repository\Values\Content\Query\Criterion\Field as FieldCriterion;

final class Field extends CriterionHandler
{
	protected $contentTypeHandler;

	public function __construct(
		Connection $connection,
		ContentTypeHandler $contentTypeHandler
	)
	{
		parent::__construct($connection);

		$this->contentTypeHandler = $contentTypeHandler;
	}

	public function accept( Criterion $criterion ): bool
    {
        return $criterion instanceof FieldCriterion;
    }

    public function handle( CriteriaConverter $converter, QueryBuilder $queryBuilder, Criterion $criterion, array $languageSettings): string
    {
		$fieldDefinition = $this->getFieldDefinition( $criterion->target );

		$valueMatch = $criterion->value;
		$operator = $criterion->operator;
		$dbColumn = $this->getDbColumn( $fieldDefinition );

		$comparefunctions =
			[
				'=' => 'eq',
				'>' => 'gt',
				'>=' => 'gte',
				'<' => 'lt',
				'<=' => 'lte',
			];

		$compareFunction = $comparefunctions[ $operator ];

		$queryBuilder->innerJoin(
			'c',
			'ezcontentobject_attribute',
			'a0',
			$queryBuilder->expr()->and(
				"a0.contentobject_id = c.id",
				"a0.contentclassattribute_id = {$fieldDefinition->id}",
				$queryBuilder->expr()->$compareFunction(
					"a0.{$dbColumn}",
					$queryBuilder->createNamedParameter( $valueMatch )
				),
				// some language mapping - copied from legacy
				'a0.language_id & c.language_mask > 0',
				'( (   c.language_mask - ( c.language_mask & a0.language_id ) ) & 1 ) + ( ( ( c.language_mask - ( c.language_mask & a0.language_id ) ) & 2 ) ) < ( a0.language_id & 1 ) + ( a0.language_id & 2 )'
				)
		);

        return '';
    }

	private function getFieldDefinition( $identifierStrings ) :? FieldDefinition
	{
		$identifiers = explode( '.', $identifierStrings );

		$contentType = $this->contentTypeHandler->loadByIdentifier( $identifiers[0] );

		foreach( $contentType->fieldDefinitions as $fieldDefinition )
		{
			if( $fieldDefinition->identifier == $identifiers[1] )
			{
				return $fieldDefinition;
			}
		}
	}

	//TODO: use the function getIndexColumn on converters
	private function getDbColumn( FieldDefinition $fieldDefinition ) : string
	{
		switch( $fieldDefinition->fieldType )
		{
			case 'ezdate':
			case 'ezdatetime':
			case 'ezfloat': // guessing here
			case 'ezinteger':
			case 'eztime':
			{
				return 'sort_key_int';
			}

			default:
				return 'sort_key_string';
		}
	}
}
