<?php

declare(strict_types=1);

namespace MugoWeb\IbexaBundle\API\Repository\Values\Content\Query\QueryBuilder;

use MugoWeb\IbexaBundle\API\Repository\Values\Content\Query\Criterion\Keyword as KeywordCriterion;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Ibexa\Contracts\Core\Persistence\Content\Type\Handler as ContentTypeHandler;
use Ibexa\Contracts\Core\Persistence\Filter\Doctrine\FilteringQueryBuilder;
use Ibexa\Contracts\Core\Repository\Values\Content\Query\Criterion;
use Ibexa\Contracts\Core\Repository\Values\Filter\CriterionQueryBuilder;
use Ibexa\Contracts\Core\Repository\Values\Filter\FilteringCriterion;
use Ibexa\Core\Persistence\Legacy\Content\Gateway as ContentGateway;
use Ibexa\Core\Persistence\Legacy\Content\Location\Gateway as LocationGateway;
use Ibexa\Core\Persistence\Legacy\Content\Type\Gateway as TypeGateway;
use RuntimeException;

final class Keyword implements CriterionQueryBuilder
{
    public function __construct(
        private readonly Connection $connection,
        private readonly ContentTypeHandler $contentTypeHandler,
    )
    {}

    public function accepts(FilteringCriterion $criterion): bool
    {
        return $criterion instanceof KeywordCriterion;
    }

    public function buildQueryConstraint(
        FilteringQueryBuilder $queryBuilder,
        FilteringCriterion    $criterion
    ): ?string
    {
        //TODO Duplicated code - compare CriterionHandler\Keyword.php, just the ezcontentobject alias is different
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
                    'attribute_value.contentobject_id = content.id',
                    'attribute_value.version = content.current_version'
                )
            );

        $condition = 'exists(' . $qbInner->getSQL() . ')';

        return $condition;
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