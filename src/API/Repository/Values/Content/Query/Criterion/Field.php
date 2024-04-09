<?php

declare(strict_types=1);

namespace MugoWeb\IbexaBundle\API\Repository\Values\Content\Query\Criterion;

use Ibexa\Contracts\Core\Repository\Values\Content\Query\Criterion;
use Ibexa\Contracts\Core\Repository\Values\Content\Query\Criterion\Operator;
use Ibexa\Contracts\Core\Repository\Values\Content\Query\Criterion\Operator\Specifications;
/**
 *
 */
final class Field extends Criterion
{
	public function __construct(?string $target, ?string $operator, $value )
	{
		$value = is_array( $value ) ? $value[0] : $value;

		$this->operator = $operator;
		$this->value = $value;
		$this->target = $target;
	}

    public function getSpecifications(): array
    {
        return [
            new Specifications(
                Operator::IN,
                Specifications::FORMAT_ARRAY,
                Specifications::TYPE_INTEGER | Specifications::TYPE_STRING
            ),
            new Specifications(
                Operator::EQ,
                Specifications::FORMAT_SINGLE,
                Specifications::TYPE_INTEGER | Specifications::TYPE_STRING
            ),
        ];
    }
}
