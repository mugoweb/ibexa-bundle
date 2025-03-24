<?php

// Limitations: does not support multi-lang setups

declare(strict_types=1);

namespace MugoWeb\IbexaBundle\API\Repository\Values\Content\Query\QueryBuilder;

use MugoWeb\IbexaBundle\API\Repository\Values\Content\Query\Criterion\Field as FieldCriterion;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Ibexa\Contracts\Core\Persistence\Filter\Doctrine\FilteringQueryBuilder;
use Ibexa\Contracts\Core\Repository\Values\Content\Query\Criterion;
use Ibexa\Contracts\Core\Repository\Values\Filter\CriterionQueryBuilder;
use Ibexa\Contracts\Core\Repository\Values\Filter\FilteringCriterion;
use Ibexa\Core\Persistence\Legacy\Content\Gateway as ContentGateway;
use Ibexa\Core\Persistence\Legacy\Content\Location\Gateway as LocationGateway;
use Ibexa\Core\Persistence\Legacy\Content\Type\Gateway as TypeGateway;
use RuntimeException;

final class Field implements CriterionQueryBuilder
{
    public function accepts(FilteringCriterion $criterion): bool
    {
        return $criterion instanceof FieldCriterion;
    }

    public function buildQueryConstraint(
        FilteringQueryBuilder $queryBuilder,
        FilteringCriterion    $criterion
    ): ?string
    {
        $fieldIdentifier = $criterion->target;
        $operator = $criterion->operator;
        $value = $criterion->value;

        $qbInner = $queryBuilder->getConnection()->createQueryBuilder();

        $booleanCondition = $qbInner->expr()->in(
            'attribute_type.data_type_string',
            $queryBuilder->createNamedParameter(['ezboolean'], Connection::PARAM_STR_ARRAY)
        );

        $stringCondition = $qbInner->expr()->in(
            'attribute_type.data_type_string',
            $queryBuilder->createNamedParameter(['ezstring'], Connection::PARAM_STR_ARRAY)
        );


        switch ($operator) {
            case Criterion\Operator::EQ:
                $fieldValueClause = "CASE
                    WHEN ${booleanCondition} THEN " . (string)$qbInner->expr()->eq('attribute_value.data_int', $queryBuilder->createNamedParameter($value ? 1 : 0, ParameterType::INTEGER)) . "
                    WHEN ${stringCondition} THEN " . (string)$qbInner->expr()->eq('attribute_value.data_text', $queryBuilder->createNamedParameter($value, ParameterType::STRING)) . "
                    ELSE false
                END";
                break;
            default:
                throw new RuntimeException(
                    "Unknown operator '{$operator}' for Field Criterion handler."
                );
        }

        $qbInner
            ->select(1)
            ->from(TypeGateway::FIELD_DEFINITION_TABLE, 'attribute_type')
            ->innerJoin(
                'attribute_type',
                ContentGateway::CONTENT_FIELD_TABLE,
                'attribute_value',
                $qbInner->expr()->and(
                    'attribute_type.version = 0',
                    'attribute_type.id = attribute_value.contentclassattribute_id'
                )
            )
            ->where(
                $qbInner->expr()->and(
                    'attribute_type.contentclass_id = content_type.id',
                    $qbInner->expr()->eq(
                        'attribute_type.identifier',
                        $queryBuilder->createNamedParameter($fieldIdentifier, ParameterType::STRING)
                    ),
                    $fieldValueClause,
                    'attribute_value.contentobject_id = content.id',
                    'attribute_value.version = content.current_version'
                )
            );

        $condition = 'exists(' . $qbInner->getSQL() . ')';

        return $condition;
    }
}