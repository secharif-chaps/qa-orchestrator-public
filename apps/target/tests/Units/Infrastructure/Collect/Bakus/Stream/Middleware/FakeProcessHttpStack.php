<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\Collect\Bakus\Stream\Middleware;

use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\ServerRequestInterface;
use WebSocket\Middleware\ProcessHttpStack;

class FakeProcessHttpStack extends ProcessHttpStack
{
    private ?ServerRequestInterface $requestToReturn = null;

    public function __construct()
    {
        // Don't call parent constructor
    }

    public function setRequestToReturn(ServerRequestInterface $request): void
    {
        $this->requestToReturn = $request;
    }

    public function handleHttpIncoming(): MessageInterface
    {
        if (null === $this->requestToReturn) {
            throw new \RuntimeException('No request configured to return');
        }

        return $this->requestToReturn;
    }
}
