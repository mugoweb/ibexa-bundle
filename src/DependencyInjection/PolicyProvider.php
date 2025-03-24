<?php

declare(strict_types=1);

namespace MugoWeb\IbexaBundle\DependencyInjection;

use Ibexa\Bundle\Core\DependencyInjection\Security\PolicyProvider\YamlPolicyProvider;

final class PolicyProvider extends YamlPolicyProvider
{
    public function getFiles(): array
    {
        return [
            __DIR__ . '/../../config/policies.yaml',
        ];
    }
}
