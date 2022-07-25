<?php

namespace MugoWeb\IbexaBundle\Form;

use Netgen\EzPlatformSiteApi\Core\Site\Values\Field;
use eZ\Publish\Core\Repository\Values\ContentType\FieldDefinition;
use eZ\Publish\SPI\FieldType\Value as ValueInterface;

class DataWrapper
{
    /** @var FieldDefinition */
    public $fieldDefinition;

    /** @var ValueInterface */
    public $fieldValue;

    /** @var mixed */
    public $context;

    public $isDefaultValue = false;

    public function __construct( $dataObject )
    {
        $this->dataObject = $dataObject;

        if( $this->dataObject instanceof Field )
        {
            $this->fieldDefinition = $dataObject->innerFieldDefinition;
            $this->fieldValue = $dataObject->value;
        }
        elseif( $this->dataObject instanceof FieldDefinition )
        {
            $this->fieldDefinition = $dataObject;
            $this->fieldValue = $dataObject->defaultValue;
            $this->isDefaultValue = true;
        }
        elseif( $this->dataObject instanceof DataWrapper )
        {
            $this->fieldDefinition = $dataObject->fieldDefinition;
            $this->fieldValue = $dataObject->fieldValue;
            $this->context = $dataObject->context;
        }
    }
}