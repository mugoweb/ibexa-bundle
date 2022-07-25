<?php
namespace MugoWeb\IbexaBundle\Form\Type\EzFieldType;

use App\Form\DataWrapper;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use eZ\Publish\Core\FieldType\Selection\Value as SelectionValue;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;

class eZSelectionType extends eZFieldType
{
    public function buildForm( FormBuilderInterface $builder, array $options ): void
    {
        /** @var DataWrapper $data */
        $data = $options[ 'data' ];

        $builder
            ->add(
                'ezselection',
                ChoiceType::class,
                $this->getFieldOptions( $data )
            )
            ->setDataMapper( $this )
        ;
    }

    protected function getFieldOptions( $data ): array
    {
        $options = parent::getFieldOptions( $data );

        $options[ 'choices' ] = $this->getChoices( $data );
        $options[ 'multiple' ] = $this->isMultiple( $data );

        return $options;
    }

    protected function getChoices( DataWrapper $data )
    {
        $fieldSettings = $data->fieldDefinition->getFieldSettings();
        return array_flip( $fieldSettings[ 'options' ] );
    }

    protected function isMultiple( $data )
    {
        $fieldSettings = $data->fieldDefinition->getFieldSettings();
        return $fieldSettings[ 'isMultiple' ];
    }

    /**
     * @param DataWrapper $dataWrapper
     * @param \Traversable $forms
     */
    public function mapDataToForms( $dataWrapper, \Traversable $forms): void
    {
        /** @var FormInterface[] $forms */
        $forms = iterator_to_array( $forms );

        $fieldSettings = $dataWrapper->fieldDefinition->getFieldSettings();

        $fieldValue = $dataWrapper->fieldValue->selection;

        if( !empty( $fieldValue) )
        {
            if( $fieldSettings[ 'isMultiple' ] )
            {
                $forms[ 'ezselection' ]->setData( $fieldValue );
            }
            else
            {
                $forms[ 'ezselection' ]->setData( $fieldValue[0] );
            }
        }
    }

    /**
     * @param \Traversable $forms
     * @param DataWrapper $viewData
     */
    public function mapFormsToData(\Traversable $forms, &$viewData ): void
    {
        /** @var FormInterface[] $forms */
        $forms = iterator_to_array($forms);

        $formValue = $forms[ 'ezselection' ]->getData();

        if( !is_array( $formValue ) )
        {
            $formValue = [ $formValue ];
        }

        $viewData->fieldValue->selection = $formValue;
    }
}