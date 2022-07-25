<?php

namespace MugoWeb\IbexaBundle\Controller;

use eZ\Publish\API\Repository\LocationService;
use eZ\Publish\Core\Event\SearchService;
use MugoWeb\IbexaBundle\Repository\LocationQuery;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class QueryController extends AbstractController
{
	public function query( Request $request, SearchService $searchService )
	{
		$result = null;
		$queryString = $request->request->get( 'query', '' );

		if( $queryString )
		{
			$locationQuery = LocationQuery::build( $queryString );

			$result = $searchService->findLocations( $locationQuery );
		}

		return $this->render(
			'@MugoWebIbexa/query.html.twig',
			[
				'queryString' => $queryString,
				'result' => $result,
			]
		);
	}

	public function location( int $locationId, LocationService $locationService )
	{
		try
		{
			$location = $locationService->loadLocation( $locationId );

			return $this->redirect('/view/content/'. $location->contentInfo->id  .'/full');
		}
		catch( \Exception $e )
		{
			var_dump( $e );
		}
	}
}
