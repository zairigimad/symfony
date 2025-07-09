<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Tests\Debug;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Debug\TraceableFirewallListener;
use Symfony\Bundle\SecurityBundle\Security\FirewallMap;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Authentication\Token\AbstractToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticatorManager;
use Symfony\Component\Security\Http\Authenticator\Debug\TraceableAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Debug\TraceableAuthenticatorManagerListener;
use Symfony\Component\Security\Http\Authenticator\InteractiveAuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Firewall\AbstractListener;
use Symfony\Component\Security\Http\Firewall\AuthenticatorManagerListener;
use Symfony\Component\Security\Http\Logout\LogoutUrlGenerator;
use Symfony\Component\VarDumper\Caster\ClassStub;

/**
 * @group time-sensitive
 */
class TraceableFirewallListenerTest extends TestCase
{
    public function testOnKernelRequestRecordsListeners()
    {
        $request = new Request();
        $event = new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);
        $event->setResponse(new Response());
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
        $firewallMap = $this->createMock(FirewallMap::class);
        $firewallMap
            ->expects($this->once())
            ->method('getFirewallConfig')
            ->with($request)
            ->willReturn(null);
        $firewallMap
            ->expects($this->once())
            ->method('getListeners')
            ->with($request)
            ->willReturn([[$listener], null, null]);

        $firewall = new TraceableFirewallListener($firewallMap, new EventDispatcher(), new LogoutUrlGenerator());
        $firewall->configureLogoutUrlGenerator($event);
        $firewall->onKernelRequest($event);

        $listeners = $firewall->getWrappedListeners();
        $this->assertCount(1, $listeners);
        $this->assertInstanceOf(ClassStub::class, $listeners[0]['stub']);
        $this->assertSame((string) new ClassStub($listener::class), (string) $listeners[0]['stub']);
    }

    public function testOnKernelRequestRecordsAuthenticatorsInfo()
    {
        $request = new Request();

        $event = new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);
        $event->setResponse($response = new Response());

        $supportingAuthenticator = $this->createMock(DummyAuthenticator::class);
        $supportingAuthenticator
            ->method('supports')
            ->with($request)
            ->willReturn(true);
        $supportingAuthenticator
            ->expects($this->once())
            ->method('authenticate')
            ->with($request)
            ->willReturn(new SelfValidatingPassport(new UserBadge('robin', function () {})));
        $supportingAuthenticator
            ->expects($this->once())
            ->method('onAuthenticationSuccess')
            ->willReturn($response);
        $supportingAuthenticator
            ->expects($this->once())
            ->method('createToken')
            ->willReturn(new class extends AbstractToken {});

        $notSupportingAuthenticator = $this->createMock(DummyAuthenticator::class);
        $notSupportingAuthenticator
            ->method('supports')
            ->with($request)
            ->willReturn(false);

        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $dispatcher = new EventDispatcher();
        $authenticatorManager = new AuthenticatorManager(
            [new TraceableAuthenticator($notSupportingAuthenticator), new TraceableAuthenticator($supportingAuthenticator)],
            $tokenStorage,
            $dispatcher,
            'main'
        );

        $listener = new TraceableAuthenticatorManagerListener(new AuthenticatorManagerListener($authenticatorManager));
        $firewallMap = $this->createMock(FirewallMap::class);
        $firewallMap
            ->expects($this->once())
            ->method('getFirewallConfig')
            ->with($request)
            ->willReturn(null);
        $firewallMap
            ->expects($this->once())
            ->method('getListeners')
            ->with($request)
            ->willReturn([[$listener], null, null]);

        $firewall = new TraceableFirewallListener($firewallMap, $dispatcher, new LogoutUrlGenerator());
        $firewall->configureLogoutUrlGenerator($event);
        $firewall->onKernelRequest($event);

        $this->assertCount(2, $authenticatorsInfo = $firewall->getAuthenticatorsInfo());

        $this->assertFalse($authenticatorsInfo[0]['supports']);
        $this->assertStringContainsString('DummyAuthenticator', $authenticatorsInfo[0]['stub']);

        $this->assertTrue($authenticatorsInfo[1]['supports']);
        $this->assertStringContainsString('DummyAuthenticator', $authenticatorsInfo[1]['stub']);
    }
}

abstract class DummyAuthenticator implements InteractiveAuthenticatorInterface
{
    public function createToken(Passport $passport, string $firewallName): TokenInterface
    {
    }
}
