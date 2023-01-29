<?php

namespace MugoWeb\IbexaBundle\Command;

use MugoWeb\IbexaBundle\Repository\LocationQuery;
use Exception;
use eZ\Publish\API\Repository\Exceptions\NotFoundException;
use eZ\Publish\API\Repository\Repository;
use eZ\Publish\API\Repository\Values\Content\Trash\TrashItemDeleteResult;
use eZ\Publish\Core\Repository\Values\Content\Content;
use eZ\Publish\Core\Repository\Values\Content\TrashItem;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;


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
            ->addArgument( 'limit', InputArgument::OPTIONAL, 'The limit of trash items to delete.' )
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute( InputInterface $input, OutputInterface $output )
    {
        $limit = $input->getArgument( 'limit' ) ?? 100;

        $result = null;
        try
        {
            $result = $this->repository->sudo(
                function() use ( $limit ) {
                    $contentService = $this->repository->getTrashService();

                    $locationQuery = LocationQuery::build(
                        'Subtree:/1/ and Subtree:/1/', //build function needs it
                        '',
                        $limit
                    );

                    return $contentService->findTrashItems( $locationQuery );
                }
            );
        }
        catch( Exception $e )
        {
            $output->writeln( 'Problem to fetch trash items: ' . $e->getMessage() );
        }

        if( $result )
        {
            $output->writeln(
                'Found '. $result->totalCount . ' trash item(s) in total - going to purge '. $limit .' item(s).' );

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
