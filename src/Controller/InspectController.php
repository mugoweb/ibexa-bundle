<?php

namespace MugoWeb\IbexaBundle\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

class InspectController extends AbstractController
{
	private Connection $connection;

	private array $languages;

	private array $fieldNames;

	public function __construct( Connection $connection )
	{
		$this->connection = $connection;

		$query = $this->connection->createQueryBuilder()
			->select( '*' )
			->from( 'ezcontent_language' )
			->execute();

		while( $row = $query->fetchAssociative() )
		{
			$this->languages[ $row[ 'id' ] ] = $row[ 'locale' ];
		}

		$query = $this->connection->createQueryBuilder()
			->select( '*' )
			->from( 'ezcontentclass_attribute' )
			->execute();

		while( $row = $query->fetchAssociative() )
		{
			$this->fieldNames[ $row[ 'id' ] ] = $row[ 'identifier' ];
		}
	}

	public function inspect( $type, $id1, $id2 ) : Response
	{
		$dom = new DOMDocument( '1.0', 'UTF-8' );

		$objectId = $this->getObjectId( $dom, $type, $id1, $id2 );

		$resultNode = $dom->appendChild( new DOMElement( 'Result' ) );
		$resultNode->appendChild( new DOMElement( 'Errors' ) );

		if( $objectId )
		{
			$this->buildObjectXml( $resultNode, $objectId );
		}

		$response = new Response( $dom->saveXML() );
		$response->headers->set('Content-Type', 'text/xml');

		return $response;
	}

	private function getObjectId( DOMDocument $dom, string $type, int $id1, int $id2 ) : int
	{
		$objectId = 0;

		switch( $type )
		{
			case 'field':
				{
					if( (int)$id1 )
					{
						$query = $this->connection->createQueryBuilder()
							->select( '*' )
							->from( 'ezcontentobject_attribute' )
							->where( 'id = :id' )
							->setParameter( 'id', (int)$id1 )
							->execute();

						$row = $query->fetchAssociative();

						if( $row )
						{
							return $row[ 'contentobject_id' ];
						}
						else
						{
							$this->addError(
								$dom->firstChild,
								'Cannot find content object for given field ID.',
								''
							);
						}
					}
				}
				break;

			case 'object':
			{
				$objectId = $id1;
			}
		}

		return $objectId;
	}

	private function buildObjectXml( DOMNode $resultNode, int $objectId )
	{
		$cNode = $resultNode->appendChild( new DOMElement( 'ContentObject' ) );
		$cNode->setAttribute( 'id', $objectId );

		$this->getLocations( $cNode );
		$this->getVersions( $cNode, $objectId );
	}

	private function getLocations( $cNode )
	{
		$objectId = $cNode->getAttribute( 'id' );

		$query = $this->connection->createQueryBuilder()
			->select( '*' )
			->from( 'ezcontentobject_tree' )
			->where( 'contentobject_id = :id' )
			->setParameter( 'id', $objectId )
			->execute();

		while( $row = $query->fetchAssociative() )
		{
			$attributes =
				[
					'id' => $row[ 'node_id' ],
					'path_string' => $row[ 'path_string' ],
					'version_nr' => $row[ 'contentobject_version' ],
				];

			$lNode = $cNode->appendChild( new DOMElement( 'Location' ) );
			$this->addAttributes( $lNode, $attributes );
		}
	}

	private function getVersions( DOMNode $cNode, $objectId )
	{
		$statusLabels =
			[
				'DRAFT',
				'PUBLISHED',
				'PENDING',
				'ARCHIVED',
				'REJECTED',
				'INTERNAL_DRAFT',
				'REPEAT',
				'QUEUED',
			];

		$query = $this->connection->createQueryBuilder()
			->select( '*' )
			->from( 'ezcontentobject_version' )
			->where( 'contentobject_id = :id' )
			->setParameter( 'id', $objectId )
			->execute();

		while( $row = $query->fetchAssociative() )
		{
			$attributes =
				[
					'id' => $row[ 'id' ],
					'version_nr' => $row[ 'version' ],
					'initial_language_id' => $row[ 'initial_language_id' ],
					'initial_language' => $this->getLanguageLocale( $row[ 'initial_language_id' ] ),
					'created' => date( 'r', $row[ 'created' ] ),
					'status' => $row[ 'status' ],
					'status_label' => $statusLabels[ $row[ 'status' ] ],
				];

			$vNode = $cNode->appendChild( new DOMElement( 'Version' ) );
			$this->addAttributes( $vNode, $attributes );

			$this->getObjectNames( $vNode, $objectId, $row[ 'version' ] );
			$this->getFields( $vNode );
		}
	}

	private function getObjectNames( DOMNode $vNode, $objectId, $versionNr )
	{
		$query = $this->connection->createQueryBuilder()
			->select( '*' )
			->from( 'ezcontentobject_name' )
			->where( 'contentobject_id = :id AND content_version = :versionNr' )
			->setParameter( 'id', $objectId )
			->setParameter( 'versionNr', $versionNr )
			->execute();

		$row = $query->fetchAssociative();

		if( $row )
		{
			$attributes =
				[
					'name' => $row[ 'name' ],
					'language_id' => $row[ 'language_id' ],
					'language' => $this->getLanguageLocale( $row[ 'language_id' ] ),
				];

			$nameNode = $vNode->appendChild(
				new DOMElement( 'Name' )
			);

			$this->addAttributes( $nameNode, $attributes );

			if( !$attributes[ 'language' ] )
			{
				$this->addError(
					$nameNode,
					'Referencing an unknown language.',
					'UPDATE ezcontentobject_name SET language_id=<id> WHERE contentobject_id='. $objectId .' AND content_version='. $versionNr .' limit;'
				);
			}
		}
		else
		{
			$values =
				[
					$objectId,
					$versionNr,
					'"' . $vNode->getAttribute( 'initial_language' ) . '"',
					$vNode->getAttribute( 'initial_language_id' ),
					'"Missing object name added"',
					'"' . $vNode->getAttribute( 'initial_language' ) . '"',
				];

			$this->addError(
				$vNode,
				'Missing content object name for version number "'. $versionNr .'". ',
				'INSERT INTO ezcontentobject_name (contentobject_id,content_version,content_translation,language_id,name,real_translation) VALUES ('. implode( ',', $values ) .');'
			);
		}
	}

	private function getFields( DOMNode $vNode )
	{
		$versionNr = $vNode->getAttribute( 'version_nr' );
		$objectId = $vNode->parentNode->getAttribute( 'id' );

		$fieldsNode = $vNode->appendChild( new DOMElement( 'fields' ) );

		$query = $this->connection->createQueryBuilder()
        		->select( '*' )
        		->from( 'ezcontentobject_attribute' )
        		->where( 'contentobject_id = :id AND version = :versionNr' )
        		->setParameter( 'id', $objectId )
				->setParameter( 'versionNr', $versionNr )
        		->execute();

		while( $row = $query->fetchAssociative() )
		{
			$attributes =
				[
					'id' => $row[ 'id' ],
					'data_type' => $row[ 'data_type_string' ],
					'language_code' => $row[ 'language_code' ],
					'data_text' => $row[ 'data_text' ],
					'field_identifier' => $this->fieldNames[ $row[ 'contentclassattribute_id' ] ],
				];

			$fieldNode = $fieldsNode->appendChild( new DOMElement( 'field' ) );
			$this->addAttributes( $fieldNode, $attributes );
		}
	}

	private function addAttributes( DOMNode $node, $attributes )
	{
		foreach( $attributes as $key => $value )
    	{
    		$node->setAttribute( $key, $value );
    	}
	}

	private function getLanguageLocale( $id ) : string
	{
		if( isset( $this->languages[ $id ] ) )
		{
			return $this->languages[ $id ];
		}

		return '';
	}

	private function addError( DOMNode $node, $message, $sql = '' )
	{
		$xPath = new DOMXPath( $node->ownerDocument );
		$errorsNode = $xPath->query( '/Result/Errors' )->item(0);

		$eNode = $errorsNode->appendChild(
			new DOMElement( 'Item', $message )
		);

		$attributes =
			[
				'path' => $node->getNodePath(),
				'sql' => $sql,
			];

		$this->addAttributes( $eNode, $attributes );
	}
}
