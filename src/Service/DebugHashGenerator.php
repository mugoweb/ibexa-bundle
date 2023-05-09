<?php

namespace MugoWeb\IbexaBundle\Service;

use FOS\HttpCache\Exception\InvalidArgumentException;
use FOS\HttpCache\UserContext\DefaultHashGenerator;
use FOS\HttpCache\UserContext\UserContext;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;

class DebugHashGenerator extends DefaultHashGenerator
{
    protected $providers;

    const CACHE_POOL_KEY = 'stats.user_hashes';

    protected TagAwareAdapterInterface $cachePool;

    public function __construct( array $providers, TagAwareAdapterInterface $cachePool )
    {
        if (0 === count($providers)) {
            throw new InvalidArgumentException('You must supply at least one provider');
        }

        $this->providers = $providers;
        $this->cachePool = $cachePool;
    }

    public function generateHash() : string
    {
        $userContext = new UserContext();

        foreach ($this->providers as $provider) {
            $provider->updateUserContext($userContext);
        }

        $parameters = $userContext->getParameters();

        // Sort by key (alphanumeric), as order should not make hash vary
        ksort($parameters);

        $serializedParameters = serialize( $parameters );

        // store stats in cachePool
        $key = base64_encode( $serializedParameters );
        $userHashes = $this->cachePool->getItem( self::CACHE_POOL_KEY );
        $storedData = $userHashes->get() ?? [];
        $currentCount = $storedData[ $key ] ?? 0;
        $storedData[ $key ] = $currentCount + 1;
        $userHashes->set( $storedData );
        $this->cachePool->save($userHashes);

        return hash('sha256', $serializedParameters );
    }
}
