<?php

namespace MugoWeb\IbexaBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader;

class MugoWebIbexaExtension extends Extension //implements PrependExtensionInterface
{

	public function load( array $configs, ContainerBuilder $container )
	{
		$loader = new Loader\YamlFileLoader( $container, new FileLocator( __DIR__ . '/../../config' ) );
		$loader->load( 'services.yml' );
	}

//    /**
//     * Prepend configuration.
//     *
//     * @param ContainerBuilder $container
//     */
//    public function prepend( ContainerBuilder $container )
//    {
//        $configFile = __DIR__ . '/../Resources/config/image_variations.yaml'; # ../Resources/config/image_variations.yaml
//        $config     = Yaml::parse( file_get_contents( $configFile ) );
//        $container->prependExtensionConfig( 'ezpublish', $config );
//        $container->addResource( new FileResource( $configFile ) );
//    }
}
