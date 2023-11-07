<?php

namespace MugoWeb\IbexaBundle\Command;

use eZ\Publish\API\Repository\Values\Content\Query;
use MugoWeb\IbexaBundle\Repository\LocationQuery;
use Exception;
use eZ\Publish\API\Repository\Exceptions\NotFoundException;
use eZ\Publish\API\Repository\Repository;
use eZ\Publish\API\Repository\Values\Content\Trash\TrashItemDeleteResult;
use eZ\Publish\Core\Repository\Values\Content\Content;
use eZ\Publish\Core\Repository\Values\Content\TrashItem;
use eZ\Publish\API\Repository\Values\Content\Query\Criterion;
use eZ\Publish\API\Repository\Values\Content\Query\Criterion\Operator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * ./bin/console ibexa:trash:purge -a 30 -l 10
 */
class PurgeTrashCommand extends Command
{
    /**
     * @var Repository
     */
    private $repository;


    public function __construct( Repository $repository, string $name = null )
    {
        $this->repository = $repository;
        parent::__construct( $name );
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName( 'ibexa:trash:purge' )
            ->setDescription( 'Purge items from trash.' )
            ->addOption( 'minAge', 'a', InputOption::VALUE_OPTIONAL, 'Age in days', 30 )
            ->addOption( 'limit', 'l', InputOption::VALUE_OPTIONAL, 'The limit of trash items to delete.', 0 )
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute( InputInterface $input, OutputInterface $output )
    {
        $minAge = (int)$input->getOption( 'minAge' );
        $limit = $input->getOption( 'limit' );

        $result = null;
        try
        {
            $result = $this->repository->sudo(
                function() use ( $limit, $minAge )
                {
                    $contentService = $this->repository->getTrashService();

                    $query = new Query();
                    $query->filter = new Criterion\DateMetadata(
                        Criterion\DateMetadata::TRASHED,
                        Operator::LT,
                        strtotime( $minAge . ' days ago' )
                    );
                    $query->sortClauses = [new Query\SortClause\Trash\DateTrashed()];
                    if( $limit )
                    {
                        $query->limit = $limit;
                    }

                    return $contentService->findTrashItems( $query );
                }
            );
        }
        catch( Exception $e )
        {
            $output->writeln( 'Problem to fetch trash items: ' . $e->getMessage() );
        }

        if( $result )
        {
            $limitString = $limit ? $limit : 'all';
            if( $limit >= count( $result->items ) )
            {
                $limitString = 'all';
            }

            $output->writeln(
                'Found '. count( $result->items ) . ' trash item(s) in total - going to purge '. $limitString .' item(s).' );

            /** @var TrashItem[] $items */
            $items = $result->getIterator();

            foreach( $items as $item )
            {
                try
                {
                    /** @var TrashItemDeleteResult $trashItemDeleteResult */
                    $trashItemDeleteResult = $this->repository->sudo(
                        function() use ( $item )
                        {
                            $contentService = $this->repository->getTrashService();
                            return $contentService->deleteTrashItem( $item );
                        }
                    );

                    $output->write( $trashItemDeleteResult->contentRemoved ? '.' : '0' );
                }
                catch( Exception $e )
                {
                    $output->writeln( 'Problem to purge trash item: ' . $e->getMessage() );
                }
            }

            return Command::SUCCESS;
        }

        return Command::FAILURE;
    }
}
