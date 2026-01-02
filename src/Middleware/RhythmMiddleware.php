<?php
declare(strict_types=1);

namespace Crustum\Rhythm\Middleware;

use Cake\Event\EventManager;
use Crustum\Rhythm\Event\SlowRequestEvent;
use Crustum\Rhythm\Rhythm;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Rhythm Middleware
 *
 * Automatically monitors HTTP requests and records performance metrics.
 */
class RhythmMiddleware implements MiddlewareInterface
{
    /**
     * Rhythm instance.
     *
     * @var \Crustum\Rhythm\Rhythm
     */
    protected Rhythm $rhythm;

    /**
     * Constructor.
     *
     * @param \Crustum\Rhythm\Rhythm $rhythm Rhythm instance
     */
    public function __construct(Rhythm $rhythm)
    {
        $this->rhythm = $rhythm;
    }

    /**
     * Process the request and response.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request Request instance
     * @param \Psr\Http\Server\RequestHandlerInterface $handler Request handler
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $startTime = microtime(true);
        $request = $request->withAttribute('rhythm', $this->rhythm);
        $response = $handler->handle($request);
        $duration = (int)((microtime(true) - $startTime) * 1000);

        $event = new SlowRequestEvent($request, $response, $duration);
        EventManager::instance()->dispatch($event);
        $this->rhythm->ingest();

        return $response;
    }
}
