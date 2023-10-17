<?php

namespace MugoWeb\IbexaBundle\Controller;

use eZ\Publish\API\Repository\Repository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Ibexa\Core\MVC\Symfony\Security\Authorization\Attribute;

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
                    function (Repository $repository) use ($location)
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

                        return 'Url Aliases regenerated.';
                    }
                );
            } catch (Exception $e) {
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
