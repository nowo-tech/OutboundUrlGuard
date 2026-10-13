<?php

declare(strict_types=1);

namespace Nowo\OutboundUrlGuardBundle\Tests\Integration\DependencyInjection;

use Nowo\OutboundUrlGuardBundle\DependencyInjection\NowoOutboundUrlGuardExtension;
use Nowo\OutboundUrlGuardBundle\Guard\AllowPrivateResolverInterface;
use Nowo\OutboundUrlGuardBundle\Guard\OutboundUrlGuard;
use Nowo\OutboundUrlGuardBundle\Guard\OutboundUrlGuardFactory;
use Nowo\OutboundUrlGuardBundle\Guard\RuntimeOutboundUrlGuard;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

final class AllowPrivateResolverIntegrationTest extends TestCase
{
    public function testDefaultResolverUsesStaticAllowPrivate(): void
    {
        $container = $this->compile([['allow_private' => true, 'resolve_dns' => false]]);

        $runtime = $container->get(RuntimeOutboundUrlGuard::class);
        self::assertInstanceOf(RuntimeOutboundUrlGuard::class, $runtime);
        self::assertTrue($runtime->inspect('http://127.0.0.1/hook')->isValid());
        self::assertFalse($runtime->inspect('http://169.254.169.254/latest/meta-data')->isValid());

        $defaults = $this->compile([['resolve_dns' => false]])->get(RuntimeOutboundUrlGuard::class);
        self::assertInstanceOf(RuntimeOutboundUrlGuard::class, $defaults);
        self::assertFalse($defaults->inspect('http://127.0.0.1/hook')->isValid());
    }

    public function testCustomResolverServiceIsReadPerCall(): void
    {
        $container = $this->compile(
            [['allow_private' => false, 'resolve_dns' => false, 'allow_private_resolver' => '@app.ops_resolver']],
            static function (ContainerBuilder $container): void {
                $container->setDefinition('app.ops_resolver', (new Definition(SwitchableResolver::class))->setPublic(true));
            },
        );

        $resolver = $container->get('app.ops_resolver');
        self::assertInstanceOf(SwitchableResolver::class, $resolver);
        $runtime = $container->get(RuntimeOutboundUrlGuard::class);
        self::assertInstanceOf(RuntimeOutboundUrlGuard::class, $runtime);
        $factory = $container->get(OutboundUrlGuardFactory::class);
        self::assertInstanceOf(OutboundUrlGuardFactory::class, $factory);

        self::assertFalse($runtime->inspect('http://127.0.0.1/hook')->isValid());
        $resolver->value = true;
        self::assertTrue($runtime->inspect('http://127.0.0.1/hook')->isValid());
        self::assertTrue($factory->create()->inspect('http://127.0.0.1/hook')->isValid());
        self::assertFalse($runtime->inspect('http://169.254.169.254/latest/meta-data')->isValid());

        // The static guard keeps the compiled allow_private value.
        $static = $container->get(OutboundUrlGuard::class);
        self::assertInstanceOf(OutboundUrlGuard::class, $static);
        self::assertFalse($static->inspect('http://127.0.0.1/hook')->isValid());
    }

    public function testRejectsEmptyResolverId(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        (new NowoOutboundUrlGuardExtension())->load([['allow_private_resolver' => '@']], new ContainerBuilder());
    }

    public function testRejectsNonStringResolverId(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        (new NowoOutboundUrlGuardExtension())->load([['allow_private_resolver' => 42]], new ContainerBuilder());
    }

    /**
     * @param array<int, array<string, mixed>>        $configs
     * @param (callable(ContainerBuilder): void)|null $register
     */
    private function compile(array $configs, ?callable $register = null): ContainerBuilder
    {
        $container = new ContainerBuilder();
        (new NowoOutboundUrlGuardExtension())->load($configs, $container);
        if (null !== $register) {
            $register($container);
        }
        $container->compile();

        return $container;
    }
}

/**
 * Host-style resolver (for example an operator setting).
 */
final class SwitchableResolver implements AllowPrivateResolverInterface
{
    public bool $value = false;

    public function allowPrivate(): bool
    {
        return $this->value;
    }
}
