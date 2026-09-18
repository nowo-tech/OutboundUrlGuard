<?php

declare(strict_types=1);

namespace Nowo\OutboundUrlGuardBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

/**
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class NowoOutboundUrlGuardExtension extends Extension
{
    /**
     * Loads services and publishes the safe defaults as container parameters.
     *
     * @param array<int, array<string, mixed>> $configs
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yaml');

        $config = $this->processConfiguration(new Configuration(), $configs);

        $container->setParameter(Configuration::ALIAS.'.allow_private', $config['allow_private']);
        $container->setParameter(Configuration::ALIAS.'.resolve_dns', $config['resolve_dns']);
        $container->setParameter(Configuration::ALIAS.'.dns_timeout', $config['dns_timeout']);
    }

    /**
     * Returns the nowo_outbound_url_guard configuration alias.
     */
    public function getAlias(): string
    {
        return Configuration::ALIAS;
    }
}
