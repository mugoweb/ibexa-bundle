<?php

namespace MugoWeb\IbexaBundle\Service;

use \Symfony\Component\DependencyInjection\ContainerInterface;
use EzSystems\PlatformHttpCacheBundle\PurgeClient\RepositoryPrefixDecorator;
use eZ\Publish\API\Repository\Values\Content\Content;
use \Symfony\Component\Cache\Adapter\AdapterInterface;

class ContentManager
{

    /** @var ContainerInterface */
    private $container;

    /** @var \eZ\Publish\API\Repository\Repository */
    private $repository;

    /** @var \eZ\Publish\API\Repository\LocationService */
    private $locationService;

    /** @var \eZ\Publish\API\Repository\ContentService */
    private $contentService;

    /** @var \eZ\Publish\API\Repository\TrashService */
    private $trashService;

    /** @var \eZ\Bundle\EzPublishCoreBundle\DependencyInjection\Configuration\ConfigResolver */
    private $configResolver;

    /** @var \Psr\Log\LoggerInterface */
    public $logger;

    /** @var RepositoryPrefixDecorator */
    public $cachePurger;

    /** @var AdapterInterface */
    public $pool;


    /**
     * Class constructor
     *
     * @param ContainerInterface $container The container to be used
     */
    function __construct( ContainerInterface $container, \Psr\Log\LoggerInterface $logger, RepositoryPrefixDecorator $cachePurger, AdapterInterface $pool )
    {
        $this->container = $container;
        $this->repository = $this->container->get( 'ezpublish.api.repository' );
        $this->locationService = $this->repository->getLocationService();
        $this->trashService = $this->repository->getTrashService();
        $this->contentService = $this->repository->getContentService();
        $this->configResolver = $this->container->get( 'ezpublish.config.resolver' );
        $this->logger = $logger;
        $this->cachePurger = $cachePurger;
        $this->pool = $pool;

    }

    private function _createContent($parentLocationId, $contentTypeIdentifier, $data)
    {
        $locationService = $this->repository->getLocationService();
        $parentLocation = $locationService->loadLocation( $parentLocationId );
        $languageCode = $parentLocation->getContentInfo()->mainLanguageCode;
        $contentService = $this->repository->getContentService();
        $contentTypeService = $this->repository->getContentTypeService();
        $contentType = $contentTypeService->loadContentTypeByIdentifier( $contentTypeIdentifier );
        $contentCreateStruct = $contentService->newContentCreateStruct( $contentType, $languageCode );
        foreach( $data as $fieldIdentifier => $fieldValue )
        {
            $contentCreateStruct->setField( $fieldIdentifier, $fieldValue );
        }

        $locationCreateStruct = $locationService->newLocationCreateStruct( $parentLocationId );

        $draft = $contentService->createContent( $contentCreateStruct, array( $locationCreateStruct ) );
        $content = $contentService->publishVersion( $draft->versionInfo );
        $this->cachePurger->purge( [ 'purge-location-' . $parentLocationId ] );
        return $content;
    }

    /**
     *
     * @param int $parentLocationId
     * @param string $contentTypeIdentifier
     * @param array $data
     * @param boolean $sudo
     * @return Content
     */
    public function createContent( $parentLocationId, $contentTypeIdentifier, $data, $sudo = false )
    {
        /*
        * We are keeping the code simple at this point
        * $data is just a simple associative array
        * Example: ['title'=>'Sample String', 'tag_field'=> $arrayOfTags...]
        * eztag sample fieldValue:
        $categoriesTag = $this->tagsService->loadTag(1);
        $tagList = $this->tagsService->loadTagChildren($categoriesTag)->getTags();
        $$data['eztag_field'] = [$tagList[0], $tagList[1]];
        * ezurl sample fieldValue
        $data['ezurl_field'] = new \eZ\Publish\Core\FieldType\Url\Value('https://google.com', 'This is google url');
        * ezdate - TODO: Need to double check timezone
        $date = \DateTime::createFromFormat( 'U', strtotime(trim($case[$index])) );
        $data[$columnToFieldIdentifierMap[$cName]] = $date;
        * ezobjectrelationlist
        $data['ezobjectrelationlist_field'] = [14, 54];
        * richtext
        $data['ezobjectrelationlist_field'] = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL
        . '<section xmlns="http://ez.no/namespaces/ezpublish5/xhtml5/edit"><p>This is a paragraph.</p></section>';
        */
        $result = false;
        if(!$sudo)
        {
            $result = $this->_createContent($parentLocationId, $contentTypeIdentifier, $data);
        }
        else
        {
            $self = $this;
            $result = $this->repository->sudo(
                function( $repository ) use ( $self, $parentLocationId, $contentTypeIdentifier, $data ) {
                    return $self->_createContent($parentLocationId, $contentTypeIdentifier, $data);
                } );
        }
        return $result;
    }

    private function _updateContent($contentId, $newData)
    {
        /** @var \eZ\Publish\API\Repository\ContentService $contentService */
        $contentService = $this->repository->getContentService();
        $contentInfo = $contentService->loadContentInfo( $contentId );
        $contentDraft = $contentService->createContentDraft( $contentInfo );
        $contentUpdateStruct = $contentService->newContentUpdateStruct();
        foreach( $newData as $fieldIdentifier => $fieldValue )
        {
            $contentUpdateStruct->setField( $fieldIdentifier, $fieldValue );
        }
        $contentDraftUpdated = $contentService->updateContent( $contentDraft->versionInfo, $contentUpdateStruct );
        $content = $contentService->publishVersion( $contentDraftUpdated->versionInfo );

        $this->cachePurger->purge( [ 'purge-location-' . $contentInfo->mainLocation->id ] );
        $this->cachePurger->purge( [ 'purge-location-' . $contentInfo->mainLocation->parentLocationId ] );
        return $content;
    }

    /**
     * @param int $contentId
     * @param array $newData
     * @param boolean $sudo
     * @return Content
     * @see createContent
     */
    public function updateContent( $contentId, $newData, $sudo = false )
    {
        $result = false;
        if(!$sudo)
        {
            $result = $this->_updateContent($contentId, $newData);
        }
        else
        {
            $self = $this;
            $result = $this->repository->sudo(
                function( $repository ) use ( $contentId, $newData, $self ) {
                    return $self->_updateContent($contentId, $newData);
                } );
        }
        return $result;
    }

    private function _updateContentName( $contentId, $newName )
    {
        /** @var \eZ\Publish\API\Repository\ContentService $contentService */
        $contentService = $this->repository->getContentService();
        $contentInfo = $contentService->loadContentInfo( $contentId );
        $contentMetaStruct = $contentService->newContentMetadataUpdateStruct();
        $contentMetaStruct->name = $newName;
        $contentService->updateContentMetadata( $contentInfo, $contentMetaStruct );
        if( $contentInfo->mainLocation )
        {
            $this->cachePurger->purge( [ 'purge-location-' . $contentInfo->mainLocation->id ] );
            $this->cachePurger->purge( [ 'purge-location-' . $contentInfo->mainLocation->parentLocationId ] );
        }
    }

    /**
     * @param int $contentId
     * @param array $newName
     * @param boolean $sudo
     * @see Update Content Name
     */
    public function updateContentName( $contentId, $newName, $sudo = false )
    {
        if(!$sudo)
        {
            $this->_updateContentName( $contentId, $newName );
        }
        else
        {
            $self = $this;
            $this->repository->sudo(
                function( $repository ) use ( $contentId, $newName, $self ) {
                    return $self->_updateContentName( $contentId, $newName );
                } );
        }
    }

    private function _deleteContent( $contentId )
    {
        /** @var \eZ\Publish\API\Repository\ContentService $contentService */
        $contentService = $this->repository->getContentService();
        $contentInfo = $contentService->loadContentInfo( $contentId );
        $contentDraft = $contentService->deleteContent( $contentInfo );
    }

    /**
     * Deletes a content object
     * @param int $contentId
     * @param boolean $sudo
     */
    public function deleteContent( int $contentId, $sudo = false )
    {
        if(!$sudo)
        {
            $this->_deleteContent( $contentId );
        }
        else
        {
            $self = $this;
            $this->repository->sudo(
                function( $repository ) use ( $contentId, $self ) {
                    return $self->_deleteContent( $contentId );
                } );
        }
    }

    /**
     * Clear persistent cache of a location
     * @param int $locationId
     */
    public function clearLocationPersistentCache( $locationId )
    {
        $locationService = $this->repository->getLocationService();
        $location = $locationService->loadLocation( $locationId );
        // Clearing Persistence cache location
        $this->pool->deleteItems([ 'ez-content-info-' . $location->contentId ]);
    }

    /**
     * Reveal (unhide) a Location
     * @param int $locationId
     */
    public function revealLocation( $locationId )
    {
        $locationService = $this->repository->getLocationService();
        $location = $locationService->loadLocation( $locationId );
        // Clearing Persistence cache location
        $locationService->unhideLocation( $location );
    }

    /**
     * Hide a Location
     * @param int $locationId
     */
    public function hideLocation( $locationId )
    {
        $locationService = $this->repository->getLocationService();
        $location = $locationService->loadLocation( $locationId );
        // Clearing Persistence cache location
        $locationService->hideLocation( $location );
    }

    private function _moveLocation( $sourceLocationId, $targetLocationId )
    {
        $locationService = $this->repository->getLocationService();
        $sourceLocation = $locationService->loadLocation( $sourceLocationId );
        $targetLocation = $locationService->loadLocation( $targetLocationId );
        // move source location to target location
        $locationService->moveSubtree( $sourceLocation, $targetLocation );
    }

    /**
     * Moves a Location from A to B with its whole subtree.
     * @param int $sourceLocationId
     * @param int $targetLocationId
     * @param boolean $sudo
     */
    public function moveLocation( $sourceLocationId, $targetLocationId, $sudo = false )
    {
        if(!$sudo)
        {
            $this->_moveLocation( $sourceLocationId, $targetLocationId );
        }
        else
        {
            $self = $this;
            $this->repository->sudo(
                function( $repository ) use ( $locationId, $self ) {
                    return $self->_moveLocation( $sourceLocationId, $targetLocationId );
                }
            );
        }
    }

    private function _deleteLocation( $locationId )
    {
        $locationService = $this->repository->getLocationService();
        $location = $locationService->loadLocation( $locationId );
        // delete location
        $locationService->deleteLocation( $location );
    }

    /**
     * It permanently deletes a Location, together with its whole subtree.
     * @param int $locationId
     * @param boolean $sudo
     */
    public function deleteLocation( $locationId, $sudo = false )
    {
        if(!$sudo)
        {
            $this->_deleteLocation( $locationId );
        }
        else
        {
            $self = $this;
            $this->repository->sudo(
                function( $repository ) use ( $locationId, $self ) {
                    return $self->_deleteLocation( $locationId );
                }
            );
        }
    }

    private function _trashLocation( $locationId )
    {
        $locationService = $this->repository->getLocationService();
        $location = $locationService->loadLocation( $locationId );
        // sent location to the trash
        $result = $this->trashService->trash($location);
        return $result;
    }

    /**
     * Trashes a location object
     * @param int $locationId
     * @param boolean $sudo
     */
    public function trashLocation( $locationId, $sudo = false )
    {
        $result = false;
        if(!$sudo)
        {
            $result = $this->_trashLocation( $locationId );
        }
        else
        {
            $self = $this;
            $result = $this->repository->sudo(
                function( $repository ) use ( $locationId, $self ) {
                    return $self->_trashLocation( $locationId );
                } );
        }
        return $result;
    }

    /**
     * Method to load a content object from a Content Id
     *
     * @param integer $contentId Content id
     * @return Content returns a Content object or false if the content cant be loaded
     */
    public function getContent( $contentId )
    {
        $result = false;
        if (!is_numeric($contentId)) {
            return $result;
        }

        try {
            $result = $this->contentService->loadContent( $contentId );
        } catch (Exceptions\NotFoundException $e) {
            $this->logger->info( 'Can\'t find content id: ' . $contentId );
        } catch (Exceptions\UnauthorizedException $e) {
            $this->logger->info( 'Unauthorized access to content id: ' . $contentId );
        }

        return $result;
    }

    /**
     * Method to provide reverse related objects
     *
     * @param eZ\Publish\API\Repository\Values\Content\ContentInfo
     * @return false|array returns list of content IDs
     */
    public function getReverseRelations( $contentInfo )
    {
        $reverseRelatedContentIds = false;
        $relations = $this->contentService->loadReverseRelations( $contentInfo );
        // store in a array a list of content ids of the reverse related objects
        foreach ($relations as $relation)
        {
            if( $relation->getSourceContentInfo()->id )
            {
                $reverseRelatedContentIds[] = $relation->getSourceContentInfo()->id;
            }
        }
        return $reverseRelatedContentIds;
    }
}
