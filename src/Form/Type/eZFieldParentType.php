<?php

namespace MugoWeb\IbexaBundle\Form\Type;

use App\Form\DataWrapper;
use App\Form\Type\EzFieldType\eZFloatType;
use App\Form\Type\EzFieldType\eZImageType;
use App\Form\Type\EzFieldType\eZMatrixType;
use App\Form\Type\EzFieldType\eZObjectRelationListType;
use App\Form\Type\EzFieldType\eZObjectRelationType;
use App\Form\Type\EzFieldType\eZSelectionType;
use App\Form\Type\EzFieldType\eZStringType;
use App\Form\Type\EzFieldType\eZTextType;
use App\Form\Type\EzFieldType\eZUserType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\DataMapperInterface;
use Symfony\Component\Form\FormBuilderInterface;
use eZ\Publish\Core\Repository\Values\ContentType\FieldDefinition;
use Netgen\EzPlatformSiteApi\Core\Site\Values\Field;
use Symfony\Component\OptionsResolver\OptionsResolver;

class eZFieldParentType extends AbstractType implements DataMapperInterface
{
    public function buildForm( FormBuilderInterface $builder, array $options ): void
    {
        $data = null;

        // Works with Netgen Fields or FieldDefinitions or DataWrappers
        if(
            $options[ 'data' ] instanceof Field ||
            $options[ 'data' ] instanceof FieldDefinition ||
            $options[ 'data' ] instanceof DataWrapper
        )
        {
            $data = new DataWrapper( $options[ 'data' ] );
        }

        if( $data instanceof DataWrapper )
        {
            $parameters =
                [
                    'constraints' => $this->buildExtraConstraints( $options ),
                    'data' => $data,
                ];
            $builder->add(
                'ezfield',
                $this->getChildFormFieldType( $data, $options[ 'child_class' ] ),
                $parameters
            )
                ->setDataMapper( $this );
        }
    }

    protected function getChildFormFieldType( $data, $childClass )
    {
        if( $childClass )
        {
            if( !class_exists( $childClass ) )
            {
                throw new \Exception( 'Unknown eZFieldType child: '. $childClass );
            }

            return $childClass;
        }
        else
        {
            $fieldMap =
                [
                    'ezstring' => eZStringType::class,
                    'ezfloat' => eZFloatType::class,
                    'ezuser' => eZUserType::class,
                    'ezimage' => eZImageType::class,
                    'ezmatrix' => eZMatrixType::class,
                    'ezobjectrelation' => eZObjectRelationType::class,
                    'eztext' => eZTextType::class,
                    'ezselection' => eZSelectionType::class,
                    'ezobjectrelationlist' => eZObjectRelationListType::class,
                ];

            if( !isset( $fieldMap[ $data->fieldDefinition->fieldTypeIdentifier ] ) )
            {
                throw new \Exception( 'Unknown eZFieldType child: '. $data->fieldDefinition->fieldTypeIdentifier );
            }

            return $fieldMap[ $data->fieldDefinition->fieldTypeIdentifier ];
        }
    }

    protected function buildExtraConstraints( $options )
    {
        $extraConstraints = [];

        // Hackish
        $childConstraints = $options[ 'child_constraints' ];

        if( !empty( $childConstraints ) )
        {
            foreach( $childConstraints as $constraint )
            {
                //Todo: Check type?
                $extraConstraints[] = $constraint;
            }
        }

        return $extraConstraints;
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
        $dataWrapper = new DataWrapper( $viewData );
        $viewData = $dataWrapper->fieldValue;
    }

    public function configureOptions( OptionsResolver $resolver ): void
    {
        // this defines the available options and their default values when
        // they are not configured explicitly when using the form type
        $resolver->setDefaults( [
            'child_class' => null,
            'child_constraints' => [],
        ] );

        // optionally you can also restrict the options type or types (to get
        // automatic type validation and useful error messages for end users)
        $resolver->setAllowedTypes( 'child_class', [ 'null', 'string' ] );
        $resolver->setAllowedTypes( 'child_constraints', [ 'null', 'array' ] );
    }

    public function getBlockPrefix()
    {
        return 'ez_parent';
    }

}