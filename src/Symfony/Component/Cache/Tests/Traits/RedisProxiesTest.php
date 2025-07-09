<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Tests\Traits;

use PHPUnit\Framework\TestCase;
use Relay\Cluster as RelayCluster;
use Relay\Relay;
use Symfony\Component\Cache\Traits\RedisProxyTrait;
use Symfony\Component\Cache\Traits\RelayClusterProxy;
use Symfony\Component\Cache\Traits\RelayProxy;
use Symfony\Component\VarExporter\ProxyHelper;

class RedisProxiesTest extends TestCase
{
    /**
     * @requires extension redis
     *
     * @testWith ["Redis"]
     *           ["RedisCluster"]
     */
    public function testRedisProxy($class)
    {
        $proxy = file_get_contents(\dirname(__DIR__, 2)."/Traits/{$class}Proxy.php");
        $proxy = substr($proxy, 0, 2 + strpos($proxy, '[];'));
        $expected = substr($proxy, 0, 2 + strpos($proxy, '}'));
        $methods = [];

        foreach ((new \ReflectionClass(\sprintf('Symfony\Component\Cache\Traits\\%sProxy', $class)))->getMethods() as $method) {
            if ('reset' === $method->name || method_exists(RedisProxyTrait::class, $method->name) || $method->isInternal()) {
                continue;
            }
            $return = '__construct' === $method->name || $method->getReturnType() instanceof \ReflectionNamedType && 'void' === (string) $method->getReturnType() ? '' : 'return ';
            $methods[$method->name] = "\n    ".ProxyHelper::exportSignature($method, true, $args)."\n".<<<EOPHP
                {
                    {$return}\$this->initializeLazyObject()->{$method->name}({$args});
                }

            EOPHP;
        }

        uksort($methods, 'strnatcmp');
        $proxy .= implode('', $methods)."}\n";

        $methods = [];

        foreach ((new \ReflectionClass($class))->getMethods() as $method) {
            if ('__destruct' === $method->name || 'reset' === $method->name) {
                continue;
            }
            $return = '__construct' === $method->name || $method->getReturnType() instanceof \ReflectionNamedType && 'void' === (string) $method->getReturnType() ? '' : 'return ';
            $methods[$method->name] = "\n    ".ProxyHelper::exportSignature($method, false, $args)."\n".<<<EOPHP
                {
                    {$return}\$this->initializeLazyObject()->{$method->name}({$args});
                }

            EOPHP;
        }

        uksort($methods, 'strnatcmp');
        $expected .= implode('', $methods)."}\n";

        if (!str_contains($expected, '#[\SensitiveParameter] ')) {
            $proxy = str_replace('#[\SensitiveParameter] ', '', $proxy);
        }

        $this->assertSame($expected, $proxy);
    }

    /**
     * @requires extension relay
     */
    public function testRelayProxy()
    {
        $proxy = file_get_contents(\dirname(__DIR__, 2).'/Traits/RelayProxy.php');
        $proxy = substr($proxy, 0, 2 + strpos($proxy, '}'));
        $expectedProxy = $proxy;
        $methods = [];
        $expectedMethods = [];

        foreach ((new \ReflectionClass(RelayProxy::class))->getMethods() as $method) {
            if ('reset' === $method->name || method_exists(RedisProxyTrait::class, $method->name) || $method->isInternal()) {
                continue;
            }

            $return = '__construct' === $method->name || $method->getReturnType() instanceof \ReflectionNamedType && 'void' === (string) $method->getReturnType() ? '' : 'return ';
            $expectedMethods[$method->name] = "\n    ".ProxyHelper::exportSignature($method, true, $args)."\n".<<<EOPHP
                {
                    {$return}\$this->initializeLazyObject()->{$method->name}({$args});
                }

            EOPHP;
        }

        foreach ((new \ReflectionClass(Relay::class))->getMethods() as $method) {
            if ('__destruct' === $method->name || 'reset' === $method->name || $method->isStatic()) {
                continue;
            }
            $return = '__construct' === $method->name || $method->getReturnType() instanceof \ReflectionNamedType && 'void' === (string) $method->getReturnType() ? '' : 'return ';
            $methods[$method->name] = "\n    ".ProxyHelper::exportSignature($method, false, $args)."\n".<<<EOPHP
                {
                    {$return}\$this->initializeLazyObject()->{$method->name}({$args});
                }

            EOPHP;
        }

        uksort($methods, 'strnatcmp');
        $proxy .= implode('', $methods)."}\n";

        uksort($expectedMethods, 'strnatcmp');
        $expectedProxy .= implode('', $expectedMethods)."}\n";

        $this->assertEquals($expectedProxy, $proxy);
    }

    /**
     * @requires extension relay
     */
    public function testRelayClusterProxy()
    {
        $proxy = file_get_contents(\dirname(__DIR__, 2).'/Traits/RelayClusterProxy.php');
        $proxy = substr($proxy, 0, 2 + strpos($proxy, '}'));
        $expectedProxy = $proxy;
        $methods = [];
        $expectedMethods = [];

        foreach ((new \ReflectionClass(RelayClusterProxy::class))->getMethods() as $method) {
            if ('reset' === $method->name || method_exists(RedisProxyTrait::class, $method->name) || $method->isInternal()) {
                continue;
            }

            $return = '__construct' === $method->name || $method->getReturnType() instanceof \ReflectionNamedType && 'void' === (string) $method->getReturnType() ? '' : 'return ';
            $expectedMethods[$method->name] = "\n    ".ProxyHelper::exportSignature($method, true, $args)."\n".<<<EOPHP
                {
                    {$return}\$this->initializeLazyObject()->{$method->name}({$args});
                }

            EOPHP;
        }

        foreach ((new \ReflectionClass(RelayCluster::class))->getMethods() as $method) {
            if ('__destruct' === $method->name || 'reset' === $method->name || $method->isStatic()) {
                continue;
            }
            $return = '__construct' === $method->name || $method->getReturnType() instanceof \ReflectionNamedType && 'void' === (string) $method->getReturnType() ? '' : 'return ';
            $methods[$method->name] = "\n    ".ProxyHelper::exportSignature($method, false, $args)."\n".<<<EOPHP
                {
                    {$return}\$this->initializeLazyObject()->{$method->name}({$args});
                }

            EOPHP;
        }

        uksort($methods, 'strnatcmp');
        $proxy .= implode('', $methods)."}\n";

        uksort($expectedMethods, 'strnatcmp');
        $expectedProxy .= implode('', $expectedMethods)."}\n";

        $this->assertEquals($expectedProxy, $proxy);
    }
}
