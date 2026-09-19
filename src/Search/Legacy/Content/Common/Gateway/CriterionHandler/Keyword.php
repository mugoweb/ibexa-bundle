<?php

declare(strict_types=1);

namespace MugoWeb\IbexaBundle\Search\Legacy\Content\Common\Gateway\CriterionHandler;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Query\QueryBuilder;
use Ibexa\Contracts\Core\Persistence\Content\Type\Handler as ContentTypeHandler;
use Ibexa\Contracts\Core\Repository\Values\Content\Query\Criterion;
use Ibexa\Core\Persistence\Legacy\Content\Gateway as ContentGateway;
use Ibexa\Core\Search\Legacy\Content\Common\Gateway\CriteriaConverter;
use Ibexa\Core\Search\Legacy\Content\Common\Gateway\CriterionHandler;
use Ibexa\Contracts\Core\Persistence\Content\Type\FieldDefinition;
use MugoWeb\IbexaBundle\API\Repository\Values\Content\Query\Criterion\Keyword as KeywordCriterion;

final class Keyword extends CriterionHandler
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
        return $criterion instanceof KeywordCriterion;
    }

    public function handle( CriteriaConverter $converter, QueryBuilder $queryBuilder, Criterion $criterion, array $languageSettings): string
    {
        $parts = explode( '.', $criterion->target );

        if( count( $parts ) !== 2 ) // expecting `ContentTypeIdentifier.FieldIdentifier`
        {
            return '1=2';
        }

        $contenTypeIdentifier = $parts[0];
        $fieldIdentifier = $parts[1];

        $keywordId = $this->getKeywordId( $criterion->value );
        if( !$keywordId ) // unknown keyword
        {
            return '1=2';
        }

        $fieldTypeId = $this->getFieldTypeId( $contenTypeIdentifier, $fieldIdentifier );

        $qbInner = $queryBuilder->getConnection()->createQueryBuilder();

        switch( $criterion->operator )
        {
            case Criterion\Operator::EQ:
                $fieldValueClause = (string)$qbInner->expr()->eq(
                    'ezkeyword_attribute_link.keyword_id
                    ', $queryBuilder->createNamedParameter($keywordId, ParameterType::INTEGER)
                );
                break;
            default:
                throw new RuntimeException(
                    "Unknown operator '{$criterion->operator}' for Field Criterion handler."
                );
        }

        $qbInner
            ->select(1)
            ->from(ContentGateway::CONTENT_FIELD_TABLE, 'attribute_value')
            ->innerJoin(
                'attribute_value',
                'ezkeyword_attribute_link',
                'ezkeyword_attribute_link',
                'ezkeyword_attribute_link.objectattribute_id = attribute_value.id'
            )
            ->where(
                $qbInner->expr()->and(
                    "attribute_value.contentclassattribute_id = $fieldTypeId",
                    $fieldValueClause,
                    'attribute_value.contentobject_id = c.id',
                    'attribute_value.version = c.current_version'
                )
            );

        $condition = 'exists(' . $qbInner->getSQL() . ')';

        return $condition;

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
                "a0.version = c.current_version",
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

    private function getFieldTypeId( string $contentTypeIdentifier, string $fieldIdentifier ): int
    {
        $contentType = $this->contentTypeHandler->loadByIdentifier( $contentTypeIdentifier );

        foreach( $contentType->fieldDefinitions as $fieldDefinition )
        {
            if( $fieldDefinition->identifier == $fieldIdentifier )
            {
                return $fieldDefinition->id;
            }
        }

        throw new \Exception( "Unknown field identifier '{$fieldIdentifier}' for content type '{$contentTypeIdentifier}'." );
    }

    private function getKeywordId( string $keyword ): int
    {
        $result = $this->connection->executeQuery( 'SELECT id FROM ezkeyword WHERE keyword = :keyword', [ 'keyword' => $keyword ] );

        if( $result->rowCount() )
        {
            return $result->fetchColumn();
        }
        else
        {
            return 0;
        }
    }
}
