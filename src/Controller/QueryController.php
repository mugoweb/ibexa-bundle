<?php

namespace MugoWeb\IbexaBundle\Controller;

use eZ\Publish\API\Repository\LocationService;
use Netgen\IbexaSiteApi\API\Site as NgServices;
use MugoWeb\IbexaBundle\Parser\QueryStringParser;
use MugoWeb\IbexaBundle\Repository\LocationQuery;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class QueryController extends AbstractController
{
	public function query( Request $request, NgServices $ngServices )
	{
		$result = null;
		$queryString = $request->request->get( 'query', '' );
		$sortString = $request->request->get( 'sort', '' );
		$limit = $request->request->get( 'limit', 100 );
		$useSearch = $request->request->get( 'useSearch', false );

		if( $queryString )
		{
			$locationQuery = QueryStringParser::getQueryObject(
				'LocationQuery',
				$queryString,
				$sortString,
				$limit
			);

			if( $useSearch )
			{
				$result = $ngServices->getFindService()->findLocations( $locationQuery );
			}
			else
			{
				$result = $ngServices->getFilterService()->filterContent( $locationQuery );
			}
		}

		return $this->render(
			'@MugoWebIbexa/query.html.twig',
			[
				'queryString' => $queryString,
				'sortString' => $sortString,
				'limit' => $limit,
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
