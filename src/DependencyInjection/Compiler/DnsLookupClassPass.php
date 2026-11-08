<?php

declare(strict_types=1);

namespace Nowo\OutboundUrlGuardBundle\DependencyInjection\Compiler;

use Nowo\OutboundUrlGuardBundle\DependencyInjection\NowoOutboundUrlGuardExtension;
use Nowo\OutboundUrlGuardBundle\Dns\HostnameDnsLookupInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;

use function class_exists;
use function is_a;
use function is_string;
use function sprintf;

/**
 * Fails the container compile when `dns_lookup` names a service whose class does not implement
 * {@see HostnameDnsLookupInterface} (instead of a TypeError on the first check). A service without a resolvable class
 * (built by a factory, for example) is left to the constructor type check.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class DnsLookupClassPass implements CompilerPassInterface
{
    /**
     * Checks the class behind the `nowo_outbound_url_guard.dns_lookup` alias.
     */
    public function process(ContainerBuilder $container): void
    {
        $alias = NowoOutboundUrlGuardExtension::DNS_LOOKUP_ALIAS;
        if (!$container->has($alias)) {
            return;
        }

        $class = $container->getParameterBag()->resolveValue($container->findDefinition($alias)->getClass());
        if (!is_string($class) || !class_exists($class)) {
            return;
        }

        if (!is_a($class, HostnameDnsLookupInterface::class, true)) {
            throw new InvalidArgumentException(sprintf('nowo_outbound_url_guard.dns_lookup: the service class "%s" must implement %s.', $class, HostnameDnsLookupInterface::class));
        }
    }
}
