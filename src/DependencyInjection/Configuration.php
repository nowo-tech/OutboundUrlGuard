<?php

declare(strict_types=1);

namespace Nowo\OutboundUrlGuardBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

use function is_string;

/**
 * Defaults for the registered OutboundUrlGuard. Each call can still override them.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class Configuration implements ConfigurationInterface
{
    public const ALIAS = 'nowo_outbound_url_guard';

    /**
     * Builds the configuration tree for nowo_outbound_url_guard.
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder(self::ALIAS);
        $root = $treeBuilder->getRootNode();

        $root
            ->children()
                ->booleanNode('allow_private')
                    ->info('When true, loopback, RFC1918, and blocked hostnames are allowed. Cloud metadata stays blocked.')
                    ->defaultFalse()
                ->end()
                ->scalarNode('allow_private_resolver')
                    ->info('Service id of an AllowPrivateResolverInterface read on every OutboundUrlGuardFactory / RuntimeOutboundUrlGuard call. Null uses the static allow_private value. Cloud metadata stays blocked whatever it returns.')
                    ->defaultNull()
                    ->validate()
                        ->ifTrue(static fn (mixed $v): bool => null !== $v && (!is_string($v) || '' === ltrim($v, '@')))
                        ->thenInvalid('allow_private_resolver must be a non-empty service id or null, got %s.')
                    ->end()
                ->end()
                ->booleanNode('resolve_dns')
                    ->info('When true, hostnames are resolved and the first public address is returned as an HttpClient resolve pin. Set false for Docker service names.')
                    ->defaultTrue()
                ->end()
                ->floatNode('dns_timeout')
                    ->info('Socket timeout in seconds for DNS lookups. Must stay below the host PHP max_execution_time.')
                    ->min(0.1)
                    ->defaultValue(2.0)
                ->end()
                ->scalarNode('php_binary')
                    ->info('PHP CLI used for the bounded DNS child process. Null auto-detects it (PHP_BINARY is not the CLI under php-fpm or FrankenPHP). An empty string, or no CLI found, resolves in-process without the dns_timeout bound.')
                    ->defaultNull()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
