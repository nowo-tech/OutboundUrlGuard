<?php

declare(strict_types=1);

namespace Nowo\OutboundUrlGuardBundle\Tests\Integration\DependencyInjection;

use Closure;
use Nowo\OutboundUrlGuardBundle\DependencyInjection\NowoOutboundUrlGuardExtension;
use Nowo\OutboundUrlGuardBundle\Guard\OutboundUrlGuard;
use Nowo\OutboundUrlGuardBundle\Guard\OutboundUrlGuardInterface;
use Nowo\OutboundUrlGuardBundle\Guard\RuntimeOutboundUrlGuard;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * 1.5 (DDD P2): the guard port.
 */
final class GuardInterfaceIntegrationTest extends TestCase
{
    public function testInterfaceResolvesToTheRuntimeGuardAndAnswersLikeTheStaticGuard(): void
    {
        foreach ([false, true] as $allowPrivate) {
            $container = $this->container(['allow_private' => $allowPrivate, 'resolve_dns' => false]);

            $port = $container->get(OutboundUrlGuardInterface::class);
            $static = $container->get(OutboundUrlGuard::class);

            self::assertInstanceOf(RuntimeOutboundUrlGuard::class, $port);
            self::assertInstanceOf(OutboundUrlGuard::class, $static);

            foreach (['http://127.0.0.1/hook', 'https://1.1.1.1/hook', 'http://169.254.169.254/latest', 'ftp://example.com', null] as $url) {
                self::assertEquals($static->inspect($url), $port->inspect($url), (string) $url);
            }

            self::assertEquals($static->httpClientOptions('https://1.1.1.1/hook'), $port->httpClientOptions('https://1.1.1.1/hook'));
        }
    }

    public function testHostCanReplaceThePolicyThroughTheAlias(): void
    {
        $container = $this->container([], static function (ContainerBuilder $container): void {
            $container->setDefinition('app.allow_list_policy', new Definition(AllowListPolicy::class));
            $container->setAlias(OutboundUrlGuardInterface::class, 'app.allow_list_policy')->setPublic(true);
        });

        $policy = $container->get(OutboundUrlGuardInterface::class);

        self::assertInstanceOf(AllowListPolicy::class, $policy);
        self::assertTrue($policy->inspect('https://hooks.example.com/x')->isValid());
        self::assertFalse($policy->inspect('https://1.1.1.1/x')->isValid());
    }

    /**
     * @param array<string, mixed> $config
     */
    private function container(array $config, ?Closure $alter = null): ContainerBuilder
    {
        $container = new ContainerBuilder();
        (new NowoOutboundUrlGuardExtension())->load([$config], $container);

        if ($alter instanceof Closure) {
            $alter($container);
        }

        $container->compile();

        return $container;
    }
}
