<?php
namespace MugoWeb\IbexaBundle\Form\Type\EzFieldType;

use App\Form\DataWrapper;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\DataMapperInterface;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\File;

class eZImageType extends eZFieldType
{
    public function buildForm( FormBuilderInterface $builder, array $options ): void
    {
        /** @var DataWrapper $data */
        $data = $options[ 'data' ];

        $builder
            ->add(
                'new_image',
                FileType::class,
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

        $forms[ 'new_image' ]->setData(
            new File(
                $dataWrapper->fieldValue->uri ?? '',
                false
            )
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

        // new image or updated
        if( $forms[ 'new_image' ]->getData() )
        {
            $viewData->fieldValue->inputUri = $forms[ 'new_image' ]->getData()->getPathname();
            $viewData->fieldValue->fileName = $forms[ 'new_image' ]->getData()->getClientOriginalName();
            $viewData->fieldValue->fileSize = filesize( $forms[ 'new_image' ]->getData()->getPathname() );
        }
    }

    public function getBlockPrefix()
    {
        return 'ez_field_image';
    }
}