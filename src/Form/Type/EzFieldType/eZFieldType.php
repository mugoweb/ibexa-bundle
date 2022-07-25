<?php

namespace MugoWeb\IbexaBundle\Form\Type\EzFieldType;

use App\Form\DataWrapper;
use Netgen\EzPlatformSiteApi\Core\Site\Values\Field;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\DataMapperInterface;

class eZFieldType extends AbstractType implements DataMapperInterface
{
    protected function getFieldOptions( $data ) : array
    {
        return
            [
                'label' => $data->fieldDefinition->getName(),
                'required' => $data->fieldDefinition->isRequired,
                'help' => $data->fieldDefinition->getDescription(),
            ];
    }

    /**
     * @param Field $viewData
     * @param \Traversable $forms
     */
    public function mapDataToForms( $viewData, \Traversable $forms): void
    {
    }

    /**
     * Not returning the field object but the field value object.
     * We can directly use it in the ContentManager
     * @param \Traversable $forms
     * @param mixed $viewData
     */
    public function mapFormsToData(\Traversable $forms, &$viewData): void
    {
    }

    public function getBlockPrefix()
    {
        return 'ez_field';
    }
}