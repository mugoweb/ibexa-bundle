<?php
namespace MugoWeb\IbexaBundle\Form\Type\EzFieldType;

use App\Form\DataWrapper;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;

class eZObjectRelationListType extends eZFieldType
{
    public function buildForm( FormBuilderInterface $builder, array $options ): void
    {
        /** @var DataWrapper $data */
        $data = $options[ 'data' ];

        $builder
            ->add(
                'ezobjectrelationlist',
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
        $options[ 'multiple' ] = true;

        return $options;
    }

    protected function getChoices( DataWrapper $data )
    {
        $fieldSettings = $data->fieldDefinition->getFieldSettings();

        //TODO: use settings to fetch choices
        //return array_flip( $result );
    }

    /**
     * @param DataWrapper $dataWrapper
     * @param \Traversable $forms
     */
    public function mapDataToForms( $dataWrapper, \Traversable $forms): void
    {
        /** @var FormInterface[] $forms */
        $forms = iterator_to_array( $forms );

        $forms[ 'ezobjectrelationlist' ]->setData(
            $dataWrapper->fieldValue->destinationContentIds
        );
    }

    /**
     * @param \Traversable $forms
     * @param DataWrapper $viewData
     */
    public function mapFormsToData(\Traversable $forms, &$viewData ): void
    {
        /** @var FormInterface[] $forms */
        $forms = iterator_to_array($forms);

        $viewData->fieldValue->destinationContentIds = $forms[ 'ezobjectrelationlist' ]->getData();
    }
}