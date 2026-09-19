<?php

namespace MugoWeb\IbexaBundle\Controller;

//use eZ\Publish\API\Repository\LocationService;
use eZ\Publish\Core\MVC\Symfony\Security\Authorization\Attribute;
use Ibexa\Contracts\Core\Repository\LocationService;
use Ibexa\Contracts\Core\Repository\SearchService;
use MugoWeb\IbexaBundle\Parser\QueryStringParser;
use MugoWeb\IbexaBundle\Repository\LocationQuery;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class QueryController extends AbstractController
{
	public function query(
        Request $request,
        LocationService $locationService,
        SearchService $searchService,
    )
	{
        $attribute = new Attribute( 'mugo_ibexa_bundle', 'query' );
        $this->denyAccessUnlessGranted( $attribute );

        $result = null;
		$queryString = $request->request->get( 'query', '' );
		$sortString = $request->request->get( 'sort', '' );
		$limit = $request->request->get( 'limit', 25 );
		$queryType = $request->request->get( 'queryType', 'DbQuery' );
        //TODO: add location vs content query option

        $query = null;

		if( $queryString )
		{

            switch( $queryType )
            {
                case 'FilterQuery':
                    $query = QueryStringParser::getQueryObject(
                        'Filter',
                        $queryString,
                        $sortString,
                        $limit
                    );

                    $result = $locationService->find( $query );
                break;

                case 'DbQuery':
                    $query = QueryStringParser::getQueryObject(
                        'Query',
                        $queryString,
                        $sortString,
                        $limit
                    );

                    $fetchResult = $searchService->findContentInfo( $query );

                    $result =
                        [
                            'totalCount' => $fetchResult->totalCount,
                            'locations' => [],
                        ];

                    foreach( $fetchResult->searchHits as $hit )
                    {
                        $contentInfo = $hit->valueObject;
                        $result[ 'locations' ][] = $locationService->loadLocation( $contentInfo->mainLocationId );
                    }
                break;

                case 'DbLocationQuery':
                    $query = QueryStringParser::getQueryObject(
                        'LocationQuery',
                        $queryString,
                        $sortString,
                        $limit
                    );

                    $result = $searchService->findContentInfo( $query );
                break;

                case 'SolrQuery':
                    $query = QueryStringParser::getQueryObject(
                        'Query',
                        $queryString,
                        $sortString,
                        $limit
                    );

                    $result = $searchService->findLocations( $query );
                break;

                case 'SolrLocationQuery':
                    $query = QueryStringParser::getQueryObject(
                        'LocationQuery',
                        $queryString,
                        $sortString,
                        $limit
                    );

                    $result = $searchService->findLocations( $query );
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
                'queryType' => $queryType,
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
