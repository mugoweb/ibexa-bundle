<?php

namespace MugoWeb\IbexaBundle\Controller;

use eZ\Publish\API\Repository\Repository;
use eZ\Publish\Core\Base\Exceptions\UnauthorizedException;
use eZ\Publish\Core\Base\Exceptions\NotFoundException;
use eZ\Publish\Core\Base\Exceptions\BadStateException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class ToolsController extends AbstractController
{
	public function regenerateUrlAlias(
        Request $request,
        Repository $repository
    )
	{
        $msg = '';
        $locationId = $request->request->get( 'locationId', 0 );

        if( $locationId )
        {
            try {
                $msg = $repository->sudo(
                    function (Repository $repository) use ( $locationId )
                    {
                        try
                        {
                            $location = $repository->getLocationService()->loadLocation( $locationId );
                        }
                        catch( UnauthorizedException | NotFoundException $e )
                        {
                            return 'Could not find Location with ID: ' . $locationId;
                        }

                        $repository->getURLAliasService()->refreshSystemUrlAliasesForLocation(
                            $location
                        );

                        // Recursive function to delete broken (missing parent) path elements
                        // The way deleteCorruptedUrlAliases works forces us to run it multiple times
                        // until we delete all corupted URL Aliases
                        function listCustomURLAliases( $repository, $location, $iteration )
                        {
                            try
                            {
                                $urlAliases = $repository->getURLAliasService()->listLocationAliases( $location );
                            }
                            catch( BadStateException $e)
                            {
                                // Avoid endless recursion - a DB master/slave setup has maybe issues with this
                                if( $iteration < 20 )
                                {
                                    // It tries to fix all corrupted URL Aliases (not only for the location context)
                                    // Not very efficient but it takes only a few seconds to run that function.
                                    $repository->getURLAliasService()->deleteCorruptedUrlAliases();

                                    listCustomURLAliases( $repository, $location, $iteration + 1 );
                                }
                            }
                        }

                        listCustomURLAliases( $repository, $location, 0 );

                        return 'Url Aliases regenerated.';
                    }
                );
            }
            catch (Exception $e)
            {
                $contentInfo = $location->getContentInfo();
                $msg = sprintf(
                    'Failed processing location %d - [%d] %s (%s: %s)',
                    $location->id,
                    $contentInfo->id,
                    $contentInfo->name,
                    get_class($e),
                    $e->getMessage()
                );
            } finally {
            }
        }

        return $this->render(
            '@MugoWebIbexa/regenerateUrlAlias.html.twig',
            [
                'message' => $msg,
            ]
        );
    }
}