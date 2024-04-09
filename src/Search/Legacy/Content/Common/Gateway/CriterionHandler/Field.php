<?php

declare(strict_types=1);

namespace MugoWeb\IbexaBundle\Search\Legacy\Content\Common\Gateway\CriterionHandler;

use Doctrine\DBAL\Query\QueryBuilder;
use Ibexa\Contracts\Core\Repository\Values\Content\Query\Criterion;
use Ibexa\Core\Search\Legacy\Content\Common\Gateway\CriteriaConverter;
use Ibexa\Core\Search\Legacy\Content\Common\Gateway\CriterionHandler;
use MugoWeb\IbexaBundle\API\Repository\Values\Content\Query\Criterion\Field as FieldCriterion;

final class Field extends CriterionHandler
{
    public function accept(Criterion $criterion): bool
    {
        return $criterion instanceof FieldCriterion;
    }

    public function handle(CriteriaConverter $converter, QueryBuilder $queryBuilder, Criterion $criterion, array $languageSettings): string
    {
        $fieldId = (int) $criterion->target;
        $valueMatch = $criterion->value;

        if( $fieldId && $valueMatch )
        {
            $queryBuilder->innerJoin(
                'c',
                'ezcontentobject_attribute',
                'a0',
                $queryBuilder->expr()->and(
                    "a0.contentobject_id = c.id",
                    "a0.contentclassattribute_id = $fieldId",
                    $queryBuilder->expr()->eq(
                        'a0.sort_key_string',
                        $queryBuilder->createNamedParameter( $valueMatch )
                    ),
                    // some language mapping - copied from legacy
                    'a0.language_id & c.language_mask > 0',
                    '( (   c.language_mask - ( c.language_mask & a0.language_id ) ) & 1 ) + ( ( ( c.language_mask - ( c.language_mask & a0.language_id ) ) & 2 ) ) < ( a0.language_id & 1 ) + ( a0.language_id & 2 )'
                    )
            );
        }

        return '';
    }
}
