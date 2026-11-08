<?php

declare(strict_types=1);

namespace Nowo\OutboundUrlGuardBundle\DependencyInjection;

use Nowo\OutboundUrlGuardBundle\Dns\HostnameDnsLookup;
use Nowo\OutboundUrlGuardBundle\Guard\ConfiguredAllowPrivateResolver;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Alias;
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
     * Private alias that the factory receives: the configured resolver, or the static default.
     */
    public const ALLOW_PRIVATE_RESOLVER_ALIAS = Configuration::ALIAS.'.allow_private_resolver';

    /**
     * Private alias that every registered guard receives as its DNS lookup: `dns_lookup`, or the bundled lookup.
     */
    public const DNS_LOOKUP_ALIAS = Configuration::ALIAS.'.dns_lookup';

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
        $container->setParameter(Configuration::ALIAS.'.php_binary', $config['php_binary']);

        $resolverId = null === $config['allow_private_resolver']
            ? ConfiguredAllowPrivateResolver::class
            : ltrim((string) $config['allow_private_resolver'], '@');
        $container->setAlias(self::ALLOW_PRIVATE_RESOLVER_ALIAS, new Alias($resolverId, false));

        $dnsLookupId = null === $config['dns_lookup']
            ? HostnameDnsLookup::class
            : ltrim((string) $config['dns_lookup'], '@');
        $container->setAlias(self::DNS_LOOKUP_ALIAS, new Alias($dnsLookupId, false));
    }

    /**
     * Returns the nowo_outbound_url_guard configuration alias.
     */
    public function getAlias(): string
    {
        return Configuration::ALIAS;
    }
}
