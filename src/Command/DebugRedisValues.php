<?php

namespace MugoWeb\IbexaBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Psr\Container\ContainerInterface;
use Ibexa\Contracts\Core\Repository\ObjectStateService;
use Symfony\Component\Cache\Adapter\AdapterInterface;

class DebugRedisValues extends Command
{
    public function __construct(
        protected readonly AdapterInterface   $cachePool,
        protected readonly ObjectStateService $objectStateService,
        string                                $name = null
    )
    {
        parent::__construct( $name );
    }

    protected function configure()
    {
        $this
            ->setName( 'redis:debug' )
            ->setDescription( 'Debug redis values.' )
            ->addArgument( 'coid', InputArgument::REQUIRED, 'Content object id to debug.' )
            ->addOption( 'purge', null, InputOption::VALUE_NONE, 'Purge the cache' )
        ;
    }

    protected function execute( InputInterface $input, OutputInterface $output ) : int
    {
        $contentObjectId = (int)$input->getArgument( 'coid' );
        $purge = $input->getOption( 'purge' );

        if( $contentObjectId )
        {
            $this->outputContentInfo( $output, $contentObjectId, $purge );

            $this->outputObjectStates( $output, $contentObjectId, $purge );
        }

        return Command::SUCCESS;
    }

    private function outputObjectStates( OutputInterface $output, $contentObjectId, $purge ) : void
    {
        foreach( $this->objectStateService->loadObjectStateGroups() as $i => $objectStateGroup )
        {
            $key = "ibx-sbg-{$objectStateGroup->id}-oc-$contentObjectId";
            $item = $this->cachePool->getItem( $key );
            if( $item->isHit() )
            {
                $objectState = $item->get();

                $purgedString = '';
                if( $purge )
                {
                    $this->cachePool->deleteItem( $key );
                    $purgedString = '<error>( purged )</error>';
                }

                $output->writeln('');
                $output->writeln("<info>Object State #{$i} — {$objectStateGroup->identifier} $purgedString</info>");
                $output->writeln('<comment>' . str_repeat('─', 58) . '</comment>');

                $rows = [
                    'ID'               => $objectState->id,
                    'Identifier'       => $objectState->identifier,
                    'Group ID'          => $objectState->groupId,
                    'Priority'          => $objectState->priority,
                    'Default Language'  => $objectState->defaultLanguage,
                    'Languages'         => implode(', ', $objectState->languageCodes),
                    'Name'              => $objectState->name['eng-US'] ?? '',
                    'Description'       => $objectState->description['eng-US'] ?? '',
                ];

                $maxLabelLength = max(array_map('strlen', array_keys($rows)));

                foreach ($rows as $label => $value) {
                    $output->writeln(sprintf(
                        '  <comment>%-' . $maxLabelLength . 's</comment>  %s',
                        $label,
                        $value
                    ));
                }

                $output->writeln('<comment>' . str_repeat('─', 58) . '</comment>');
                $output->writeln('');
            }
        }
    }

    private function outputContentInfo( OutputInterface $output, $contentObjectId, $purge ) : void
    {
        $key = "ibx-ci-$contentObjectId";
        $item = $this->cachePool->getItem( $key );

        if( $item->isHit() )
        {
            $contentInfo = $item->get();
            $purgedString = '';
            if( $purge )
            {
                $this->cachePool->deleteItem( $key );
                $purgedString = '<error>( purged )</error>';
            }


            $output->writeln('');
            $output->writeln("<info>ContentInfo #' . $contentInfo->id . ' — ' . $contentInfo->name $purgedString</info>");
            $output->writeln('<comment>' . str_repeat('─', 58) . '</comment>');

            $rows = [
                'ID'                => $contentInfo->id,
                'Name'              => $contentInfo->name,
                'Content Type'      => $contentInfo->contentTypeId,
                'Section'           => $contentInfo->sectionId,
                'Version'           => $contentInfo->currentVersionNo,
                'Language'          => $contentInfo->mainLanguageCode,
                'Published'         => $contentInfo->isPublished ? '<info>✓ Yes</info>' : '<error>✗ No</error>',
                'Hidden'            => $contentInfo->isHidden ? '<error>✓ Yes</error>' : '<info>✗ No</info>',
                'Always Available'  => $contentInfo->alwaysAvailable ? '<info>✓ Yes</info>' : '<comment>✗ No</comment>',
                'Owner'             => $contentInfo->ownerId,
                'Main Location'     => $contentInfo->mainLocationId,
                'Status'            => $contentInfo->status,
                'Modified'          => date('Y-m-d H:i:s', $contentInfo->modificationDate),
                'Published At'      => date('Y-m-d H:i:s', $contentInfo->publicationDate),
                'Remote ID'         => $contentInfo->remoteId,
                'Redis Cache Key' => sprintf('ibexa:ibx-ci-%d', $contentInfo->id),
            ];

            $maxLabelLength = max(array_map('strlen', array_keys($rows)));

            foreach ($rows as $label => $value) {
                $output->writeln(sprintf(
                    '  <comment>%-' . $maxLabelLength . 's</comment>  %s',
                    $label,
                    $value
                ));
            }

            $output->writeln('<comment>' . str_repeat('─', 58) . '</comment>');
            $output->writeln('');
        }
    }

    private function getCachePoolName() : string
    {
        $name = getenv('APP_ENV') . '_redis';
        $name = 'cache.redis';

        return $name;
    }

}