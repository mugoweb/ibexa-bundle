<?php

namespace MugoWeb\IbexaBundle\Command;

use MugoWeb\IbexaBundle\Service\DebugHashGenerator;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;


class DumpUserHashVariations extends Command
{
    /**
     * @var Repository
     */
    private TagAwareAdapterInterface $cachePool;

    public function __construct( TagAwareAdapterInterface $cachePool, string $name = null )
    {
        $this->cachePool = $cachePool;
        parent::__construct( $name );
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName( 'mugo:dump:userCacheVariations' )
            ->setDescription( 'Dumps user cache variations' )
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute( InputInterface $input, OutputInterface $output )
    {
        $userHashes = $this->cachePool->getItem( DebugHashGenerator::CACHE_POOL_KEY );
        $storedData = $userHashes->get() ?? [];

        foreach( $storedData as $variationString => $count )
        {
            $output->writeln( 'Count: ' . $count );
            print_r( unserialize( base64_decode( $variationString ) ) );
        }

        return Command::SUCCESS;
    }
}
