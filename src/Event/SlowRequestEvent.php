<?php
declare(strict_types=1);

namespace Crustum\Rhythm\Event;

use Cake\Event\Event;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Event class for slow HTTP requests.
 *
 * @extends \Cake\Event\Event<\Crustum\Rhythm\Rhythm>
 */
class SlowRequestEvent extends Event
{
    /**
     * The request instance.
     *
     * @var \Psr\Http\Message\ServerRequestInterface
     */
    protected ServerRequestInterface $request;

    /**
     * The response instance.
     *
     * @var \Psr\Http\Message\ResponseInterface
     */
    protected ResponseInterface $response;

    /**
     * The duration of the request in milliseconds.
     *
     * @var int
     */
    protected int $duration;

    /**
     * Constructor.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request The request instance.
     * @param \Psr\Http\Message\ResponseInterface $response The response instance.
     * @param int $duration The duration of the request in milliseconds.
     */
    public function __construct(ServerRequestInterface $request, ResponseInterface $response, int $duration)
    {
        parent::__construct('Rhythm.slowRequest');
        $this->request = $request;
        $this->response = $response;
        $this->duration = $duration;
    }

    /**
     * Get the request instance.
     *
     * @return \Psr\Http\Message\ServerRequestInterface
     */
    public function getRequest(): ServerRequestInterface
    {
        return $this->request;
    }

    /**
     * Get the response instance.
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function getResponse(): ResponseInterface
    {
        return $this->response;
    }

    /**
     * Get the duration of the request.
     *
     * @return int
     */
    public function getDuration(): int
    {
        return $this->duration;
    }
}
