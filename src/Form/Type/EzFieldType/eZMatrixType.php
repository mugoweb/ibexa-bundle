<?php
namespace MugoWeb\IbexaBundle\Form\Type\EzFieldType;

use App\Form\DataWrapper;
use EzSystems\EzPlatformMatrixFieldtype\FieldType\Value as MatrixValue;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\DataMapperInterface;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;

class eZMatrixType extends eZFieldType
{
    public function buildForm( FormBuilderInterface $builder, array $options ): void
    {
        /** @var DataWrapper $data */
        $data = $options[ 'data' ];

        $builder
            ->add(
                'ezmatrix',
                CollectionType::class,
                $this->getFieldOptions( $data )
            )
            ->setDataMapper( $this )
        ;
    }

    protected function getFieldOptions( $data ) : array
    {
        $options = parent::getFieldOptions( $data );
        $options[ 'allow_add' ] = true;
        $options[ 'allow_delete' ] = true;

        return $options;
    }

    public function getBlockPrefix()
    {
        return 'ez_field_matrix';
    }

    /**
     * @param DataWrapper $dataWrapper
     * @param \Traversable $forms
     */
    public function mapDataToForms( $dataWrapper, \Traversable $forms): void
    {
        /** @var FormInterface[] $forms */
        $forms = iterator_to_array( $forms );

        $data = [];

        foreach( $dataWrapper->fieldValue->rows as $index => $row )
        {
            foreach( $row->getCells() as $key => $value )
            {
                $data[ $index .'-'. $key ] = $value;
            }
        }

        $forms[ 'ezmatrix' ]->setData( $data );
    }

    /**
     * @param \Traversable $forms
     * @param DataWrapper $viewData
     */
    public function mapFormsToData(\Traversable $forms, &$viewData ): void
    {
        /** @var FormInterface[] $forms */
        $forms = iterator_to_array($forms);

        $arrayRebuilded = [];

        foreach( $forms[ 'ezmatrix' ]->getData() as $key => $value )
        {
            $parts = explode( '-', $key );
            $arrayRebuilded[$parts[0]][$parts[1]] = $value;
        }

        $rows = [];
        foreach( $arrayRebuilded as $rowData )
        {
            $rows[] = new MatrixValue\Row( $rowData );
        }

        $viewData->fieldValue->setRows(
            new MatrixValue\RowsCollection( $rows )
        );
    }

    public function buildView( FormView $view, FormInterface $form, array $options ): void
    {
        /** @var DataWrapper $dataWrapper */
        $dataWrapper = $options[ 'data' ];

        $headers = [];
        foreach( $dataWrapper->fieldDefinition->fieldSettings[ 'columns' ] as $column )
        {
            $headers[ $column[ 'identifier' ] ] = $column[ 'name' ];
        }

        $view->vars[ 'headers' ] = $headers;
    }
}