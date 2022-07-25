<?php
namespace MugoWeb\IbexaBundle\Form\Type\EzFieldType;

use App\Form\DataWrapper;
use MugoWeb\Bundle\Ibexa\Repository\LocationQuery;
use eZ\Publish\Core\Repository\Values\ContentType\FieldDefinition;
use Netgen\EzPlatformSiteApi\API\Site as NgServices;
use Netgen\EzPlatformSiteApi\Core\Site\Values\Field;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\DataMapperInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Regex;

class eZUserType extends eZFieldType
{
    public function buildForm( FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add(
                'email',
                TextType::class,
                [
                    'label' => 'Email'
                ]
            )
            ->add(
                'password',
                PasswordType::class,
                [
                    'required' => true,
                    'label' => 'Password',
                    'constraints' => [
                        new Length( [ 'min' => 10 ] ),
                        new Regex( '/(?=.*[a-z])(?=.*[A-Z])/', 'You need at least one lower and one upper case.' ),
                    ],
                ]
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

        $forms[ 'email' ]->setData(
            $dataWrapper->fieldValue->email
        );

        $forms[ 'password' ]->setData( '' );
    }

    /**
     * @param \Traversable $forms
     * @param DataWrapper $viewData
     */
    public function mapFormsToData(\Traversable $forms, &$viewData ): void
    {
        /** @var FormInterface[] $forms */
        $forms = iterator_to_array($forms);

        $viewData->fieldValue->email = $forms[ 'email' ]->getData();
        $viewData->fieldValue->enabled = true;
        //TODO: PMG specific
        $viewData->fieldValue->login = $forms[ 'email' ]->getData();

        if( $forms[ 'password' ]->getData() )
        {
            $viewData->fieldValue->plainPassword = $forms[ 'password' ]->getData();
        }
    }

    public function buildView( FormView $view, FormInterface $form, array $options ): void
    {
        $view->vars[ 'is_new_user' ] = $options[ 'data' ]->isDefaultValue;
    }

    public function getBlockPrefix()
    {
        return 'ez_field_user';
    }

}