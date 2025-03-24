<?php

namespace MugoWeb\IbexaBundle\Controller;

//use eZ\Publish\API\Repository\LocationService;
use Ibexa\Contracts\Core\Repository\LocationService;
use Ibexa\Contracts\Core\Repository\SearchService;

use MugoWeb\IbexaBundle\Parser\QueryStringParser;
use MugoWeb\IbexaBundle\Repository\LocationQuery;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class QueryController extends AbstractController
{
	public function query( Request $request, LocationService $locationService )
	{
		$result = null;
		$queryString = $request->request->get( 'query', '' );
		$sortString = $request->request->get( 'sort', '' );
		$limit = $request->request->get( 'limit', 100 );
		$queryType = $request->request->get( 'queryType', 'db' );
        //TODO: add location vs content query option

        $query = null;

		if( $queryString )
		{

            switch( $queryType )
            {
                case 'db':
                    $query = QueryStringParser::getQueryObject(
                        'Filter',
                        $queryString,
                        $sortString,
                        $limit
                    );

                    $result = $locationService->find( $query );
                    //$result = $ngServices->getFilterService()->filterContent( $query );
                    break;

                case 'solr':
                    $query = QueryStringParser::getQueryObject(
                        'LocationQuery',
                        $queryString,
                        $sortString,
                        $limit
                    );

                    $result = $ngServices->getFindService()->findLocations( $query );
                    break;

                default:
                    dd( $queryType );
            }
		}

		return $this->render(
			'@MugoWebIbexa/query.html.twig',
			[
				'queryString' => $queryString,
				'sortString' => $sortString,
				'limit' => $limit,
				'result' => $result,
                'query' => $query,
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
