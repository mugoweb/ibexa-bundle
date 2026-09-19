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

        $dateCondition = $qbInner->expr()->in(
            'attribute_type.data_type_string',
            $queryBuilder->createNamedParameter([ 'ezdate', 'ezdatetime' ], Connection::PARAM_STR_ARRAY)
        );

        switch( $operator )
        {
            case Criterion\Operator::EQ:
                $fieldValueClause = "CASE
                    WHEN {$booleanCondition} THEN " . (string)$qbInner->expr()->eq('attribute_value.data_int',  $queryBuilder->createNamedParameter( $value ? 1 : 0, ParameterType::INTEGER)) . "
                    WHEN {$stringCondition}  THEN " . (string)$qbInner->expr()->eq('attribute_value.data_text', $queryBuilder->createNamedParameter( $value, ParameterType::STRING)) . "
                    WHEN {$dateCondition}   THEN " . (string)$qbInner->expr()->eq('attribute_value.data_int',  $queryBuilder->createNamedParameter( strtotime( $value ), ParameterType::INTEGER )) . "
                    ELSE false
                END";
            break;
            case Criterion\Operator::GT:
                $fieldValueClause = "CASE
                    WHEN {$dateCondition} THEN " . (string)$qbInner->expr()->gt('attribute_value.data_int', $queryBuilder->createNamedParameter( strtotime( $value ), ParameterType::INTEGER )) . "
                    ELSE false
                END";
            break;
            case Criterion\Operator::LT:
                $fieldValueClause = "CASE
                    WHEN {$dateCondition} THEN " . (string)$qbInner->expr()->lt('attribute_value.data_int', $queryBuilder->createNamedParameter( strtotime( $value ), ParameterType::INTEGER )) . "
                    ELSE false
                END";
            break;
            case Criterion\Operator::GTE:
                $fieldValueClause = "CASE
                    WHEN {$dateCondition} THEN " . (string)$qbInner->expr()->gte('attribute_value.data_int', $queryBuilder->createNamedParameter( strtotime( $value ), ParameterType::INTEGER )) . "
                    ELSE false
                END";
                break;
            case Criterion\Operator::LTE:
                $fieldValueClause = "CASE
                    WHEN {$dateCondition} THEN " . (string)$qbInner->expr()->lte('attribute_value.data_int', $queryBuilder->createNamedParameter( strtotime( $value ), ParameterType::INTEGER )) . "
                    ELSE false
                END";
                break;
            default:
                throw new RuntimeException(
                    "Unknown operator '{$operator}' for Field Criterion handler."
                );
        }

        // Handle field identifier
        $fieldIdentifierParts = explode( '.', $fieldIdentifier );
        if( count( $fieldIdentifierParts ) === 2 )
        {
            // Building subquery to lookup atttribute id
            $qbAttributeIdLookup = $queryBuilder->getConnection()->createQueryBuilder();

            $qbAttributeIdLookup
                ->select('cca.id')
                ->from('ezcontentclass_attribute', 'cca')
                ->innerJoin(
                    'cca',
                    'ezcontentclass',
                    'cc',
                    'cca.contentclass_id = cc.id'
                )
                ->where(
                    $qbAttributeIdLookup->expr()->eq(
                        'cc.identifier',
                        $queryBuilder->createNamedParameter( $fieldIdentifierParts[0]
                    ) )
                )
                ->andWhere(
                    $qbAttributeIdLookup->expr()->eq(
                        'cca.identifier',
                        $queryBuilder->createNamedParameter( $fieldIdentifierParts[1]
                    ) )
                )
            ;

            // Adding subquery
            $fieldIdentifierCause = $qbInner->expr()->eq(
                'attribute_type.id',
                "( {$qbAttributeIdLookup->getSQL()} )"
            );
        }
        else // Simple field identifier
        {
            $fieldIdentifierCause =
                $qbInner->expr()->and(
                    // only works if there is content type criterion
                    'attribute_type.contentclass_id = content_type.id',
                    // matching field identifier
                    $qbInner->expr()->eq(
                        'attribute_type.identifier',
                        $queryBuilder->createNamedParameter( $fieldIdentifier, ParameterType::STRING )
                    )
                )
            ;
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
                    $fieldIdentifierCause,
                    $fieldValueClause,
                    'attribute_value.contentobject_id = content.id',
                    'attribute_value.version = content.current_version'
                )
            );

        dd( $condition );
        $condition = 'exists(' . $qbInner->getSQL() . ')';

        return $condition;
    }
}