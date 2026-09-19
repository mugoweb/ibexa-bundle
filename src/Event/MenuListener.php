<?php

declare(strict_types=1);

namespace MugoWeb\IbexaBundle\Event;

use Ibexa\AdminUi\Menu\Event\ConfigureMenuEvent;
use Ibexa\AdminUi\Menu\MainMenuBuilder;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class MenuListener implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            ConfigureMenuEvent::MAIN_MENU => [ 'onMenuConfigure', 0 ],
        ];
    }

    public function onMenuConfigure( ConfigureMenuEvent $event ): void
    {
        $menu = $event->getMenu();

        if( !isset( $menu[ MainMenuBuilder::ITEM_ADMIN ] ) )
        {
            return;
        }

        $menu[ MainMenuBuilder::ITEM_ADMIN ]->addChild(
            'mugoweb_ibexa_bundle_query',
            [
                'label' => 'Query for content',
                'route' => 'mugoweb_ibexa_query',
                'extras' => [
                    'translation_domain' => 'mugoweb_ibexa_bundle',
                    'routes' => [
                        'foo' => 'mugoweb_ibexa_query',
                    ],
                ],
            ]
        );

        $menu[ MainMenuBuilder::ITEM_ADMIN ]->addChild(
            'mugoweb_ibexa_regenerate_url_alias',
            [
                'label' => 'Regenerate URL alias',
                'route' => 'mugoweb_ibexa_regenerate_url_alias',
                'extras' => [
                    'translation_domain' => 'mugoweb_ibexa_bundle',
                    'routes' => [
                        'foo' => 'mugoweb_ibexa_regenerate_url_alias',
                    ],
                ],
            ]
        );
    }
}