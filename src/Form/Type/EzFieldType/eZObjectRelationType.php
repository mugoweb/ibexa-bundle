<?php

namespace MugoWeb\IbexaBundle\Form\Type\EzFieldType;

use App\Form\DataWrapper;
use MugoWeb\Bundle\Ibexa\Repository\LocationQuery;
use eZ\Publish\Core\FieldType\Relation\Value as RelationValue;
use Netgen\EzPlatformSiteApi\API\Site as NgServices;
use Symfony\Component\Form\DataMapperInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;

class eZObjectRelationType extends eZFieldType
{
    protected $ngServices;

    public function __construct( NgServices $ngServices )
    {
        $this->ngServices = $ngServices;
    }

    public function buildForm( FormBuilderInterface $builder, array $options ): void
    {
        /** @var DataWrapper $data */
        $data = $options[ 'data' ];

        $builder
            ->add(
                'ezobjectrelation',
                ChoiceType::class,
                $this->getFieldOptions( $data )
            )
            ->setDataMapper( $this )
        ;
    }

    protected function getFieldOptions( $data ) : array
    {
        $options = parent::getFieldOptions( $data );
        $options[ 'choices' ] = $this->getChoices( $data );

        return $options;
    }

    //TODO: not generic enough
    protected function getChoices( $data )
    {
        $choices = [];

        $queryString =
            'Subtree:/1/2/ and '.
            'ContentTypeIdentifier:floor_plan';

        $query = LocationQuery::build(
            $queryString,
            '',
            0
        );

        $filterService = $this->ngServices->getFilterService();
        $searchResult = $filterService->filterLocations( $query );

        foreach( $searchResult->searchHits as $hit )
        {
            $location = $hit->valueObject;

            $choices[ $location->content->name ] = $location->content->id;
        }

        return $choices;
    }

    /**
     * @param DataWrapper $dataWrapper
     * @param \Traversable $forms
     */
    public function mapDataToForms( $dataWrapper, \Traversable $forms): void
    {
        /** @var FormInterface[] $forms */
        $forms = iterator_to_array( $forms );

        $forms[ 'ezobjectrelation' ]->setData(
            $dataWrapper->fieldValue->destinationContentId
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

        $viewData->fieldValue->destinationContentId = $forms[ 'ezobjectrelation' ]->getData();
    }
}