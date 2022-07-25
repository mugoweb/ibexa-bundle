<?php
namespace MugoWeb\IbexaBundle\Form\Type\EzFieldType;

use App\Form\DataWrapper;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;

class eZFloatType extends eZFieldType
{
    public function buildForm( FormBuilderInterface $builder, array $options ): void
    {
        /** @var DataWrapper $data */
        $data = $options[ 'data' ];

        $builder
            ->add(
                'ezfloat',
                NumberType::class,
                $this->getFieldOptions( $data )
            )
            ->setDataMapper( $this )
        ;
    }

    protected function getFieldOptions( $data ): array
    {
        $options = parent::getFieldOptions( $data );
        $options[ 'constraints' ] = [ new Type( 'float' ) ];

        return $options;
    }

    /**
     * @param DataWrapper $dataWrapper
     * @param \Traversable $forms
     */
    public function mapDataToForms( $dataWrapper, \Traversable $forms): void
    {
        /** @var FormInterface[] $forms */
        $forms = iterator_to_array( $forms );

        $forms[ 'ezfloat' ]->setData(
            $dataWrapper->fieldValue->value
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

        $viewData->fieldValue->value = $forms[ 'ezfloat' ]->getData();
    }
}