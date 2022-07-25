<?php
namespace MugoWeb\IbexaBundle\Form\Type\EzFieldType;

use App\Form\DataWrapper;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;

class eZTextType extends eZFieldType
{
    public function buildForm( FormBuilderInterface $builder, array $options ): void
    {
        /** @var DataWrapper $data */
        $data = $options[ 'data' ];

        $builder
            ->add(
                'eztext',
                TextareaType::class,
                $this->getFieldOptions( $data )
            )
            ->setDataMapper( $this )
        ;
    }

    /**
     * @param DataWrapper $dataWrapper
     * @param \Traversable $forms
     */
    public function mapDataToForms( $dataWrapper, \Traversable $forms): void
    {
        /** @var FormInterface[] $forms */
        $forms = iterator_to_array( $forms );

        $forms[ 'eztext' ]->setData(
            $dataWrapper->fieldValue->text
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

        $viewData->fieldValue->text = $forms[ 'eztext' ]->getData();
    }
}