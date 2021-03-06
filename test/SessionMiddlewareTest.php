<?php

/**
 * @see       https://github.com/mezzio/mezzio-session for the canonical source repository
 * @copyright https://github.com/mezzio/mezzio-session/blob/master/COPYRIGHT.md
 * @license   https://github.com/mezzio/mezzio-session/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace MezzioTest\Session;

use Mezzio\Session\LazySession;
use Mezzio\Session\SessionMiddleware;
use Mezzio\Session\SessionPersistenceInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class SessionMiddlewareTest extends TestCase
{
    public function testConstructorAcceptsConcretePersistenceInstances()
    {
        $persistence = $this->prophesize(SessionPersistenceInterface::class)->reveal();
        $middleware = new SessionMiddleware($persistence);
        $this->assertAttributeSame($persistence, 'persistence', $middleware);
    }

    public function testMiddlewareCreatesLazySessionAndPassesItToDelegateAndPersistsSessionInResponse()
    {
        $request = $this->prophesize(ServerRequestInterface::class);
        $request
            ->withAttribute(SessionMiddleware::SESSION_ATTRIBUTE, Argument::type(LazySession::class))
            ->will([$request, 'reveal']);

        $response = $this->prophesize(ResponseInterface::class);

        $handler = $this->prophesize(RequestHandlerInterface::class);
        $handler->handle(Argument::that([$request, 'reveal']))->will([$response, 'reveal']);

        $persistence = $this->prophesize(SessionPersistenceInterface::class);
        $persistence
            ->persistSession(
                Argument::that(function ($session) use ($persistence, $request) {
                    $this->assertInstanceOf(LazySession::class, $session);
                    $this->assertAttributeSame($persistence->reveal(), 'persistence', $session);
                    $this->assertAttributeSame($request->reveal(), 'request', $session);
                    return $session;
                }),
                Argument::that([$response, 'reveal'])
            )
            ->will([$response, 'reveal']);

        $middleware = new SessionMiddleware($persistence->reveal());
        $this->assertSame($response->reveal(), $middleware->process($request->reveal(), $handler->reveal()));
    }
}
