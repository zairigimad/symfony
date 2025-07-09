<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PhpUnit\ExpectUserDeprecationMessageTrait;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Http\Firewall;
use Symfony\Component\Security\Http\Firewall\AbstractListener;
use Symfony\Component\Security\Http\Firewall\ExceptionListener;
use Symfony\Component\Security\Http\Firewall\FirewallListenerInterface;
use Symfony\Component\Security\Http\FirewallMapInterface;

class FirewallTest extends TestCase
{
    use ExpectUserDeprecationMessageTrait;

    public function testOnKernelRequestRegistersExceptionListener()
    {
        $dispatcher = $this->createMock(EventDispatcherInterface::class);

        $listener = $this->createMock(ExceptionListener::class);
        $listener
            ->expects($this->once())
            ->method('register')
            ->with($this->equalTo($dispatcher))
        ;

        $request = $this->createMock(Request::class);

        $map = $this->createMock(FirewallMapInterface::class);
        $map
            ->expects($this->once())
            ->method('getListeners')
            ->with($this->equalTo($request))
            ->willReturn([[], $listener, null])
        ;

        $event = new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);

        $firewall = new Firewall($map, $dispatcher);
        $firewall->onKernelRequest($event);
    }

    public function testOnKernelRequestStopsWhenThereIsAResponse()
    {
        $listener = new class extends AbstractListener {
            public int $callCount = 0;

            public function supports(Request $request): ?bool
            {
                return true;
            }

            public function authenticate(RequestEvent $event): void
            {
                ++$this->callCount;
            }
        };

        $map = $this->createMock(FirewallMapInterface::class);
        $map
            ->expects($this->once())
            ->method('getListeners')
            ->willReturn([[$listener, $listener], null, null])
        ;

        $event = new RequestEvent($this->createMock(HttpKernelInterface::class), new Request(), HttpKernelInterface::MAIN_REQUEST);
        $event->setResponse(new Response());

        $firewall = new Firewall($map, $this->createMock(EventDispatcherInterface::class));
        $firewall->onKernelRequest($event);

        $this->assertSame(1, $listener->callCount);
    }

    public function testOnKernelRequestWithSubRequest()
    {
        $map = $this->createMock(FirewallMapInterface::class);
        $map
            ->expects($this->never())
            ->method('getListeners')
        ;

        $event = new RequestEvent(
            $this->createMock(HttpKernelInterface::class),
            $this->createMock(Request::class),
            HttpKernelInterface::SUB_REQUEST
        );

        $firewall = new Firewall($map, $this->createMock(EventDispatcherInterface::class));
        $firewall->onKernelRequest($event);

        $this->assertFalse($event->hasResponse());
    }

    public function testFirewallListenersAreCalled()
    {
        $calledListeners = [];

        $firewallListener = new class($calledListeners) implements FirewallListenerInterface {
            public function __construct(private array &$calledListeners)
            {
            }

            public function supports(Request $request): ?bool
            {
                return true;
            }

            public function authenticate(RequestEvent $event): void
            {
                $this->calledListeners[] = 'firewallListener';
            }

            public static function getPriority(): int
            {
                return 0;
            }
        };
        $callableFirewallListener = new class($calledListeners) extends AbstractListener {
            public function __construct(private array &$calledListeners)
            {
            }

            public function supports(Request $request): ?bool
            {
                return true;
            }

            public function authenticate(RequestEvent $event): void
            {
                $this->calledListeners[] = 'callableFirewallListener';
            }
        };

        $request = $this->createMock(Request::class);

        $map = $this->createMock(FirewallMapInterface::class);
        $map
            ->expects($this->once())
            ->method('getListeners')
            ->with($this->equalTo($request))
            ->willReturn([[$firewallListener, $callableFirewallListener], null, null])
        ;

        $event = new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);

        $firewall = new Firewall($map, $this->createMock(EventDispatcherInterface::class));
        $firewall->onKernelRequest($event);

        $this->assertSame(['firewallListener', 'callableFirewallListener'], $calledListeners);
    }
}
