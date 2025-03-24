<?php

namespace MugoWeb\IbexaBundle;

use MugoWeb\IbexaBundle\DependencyInjection\PolicyProvider;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class MugoWebIbexaBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        /** @var \Ibexa\Bundle\Core\DependencyInjection\IbexaCoreExtension $ibexaCoreExtension */
        $ibexaCoreExtension = $container->getExtension('ibexa');
        $ibexaCoreExtension->addPolicyProvider( new PolicyProvider() );
    }

    public function getPath(): string
	{
		return \dirname(__DIR__);
	}
}