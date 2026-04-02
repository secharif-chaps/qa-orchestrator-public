<?php

namespace App\Infrastructure\Collect\Bakus\Stream;

use Psr\Log\LoggerInterface;
use WebSocket\Client;
use WebSocket\Middleware\MiddlewareInterface;

class WebSocketClientFactory
{
    /** @var int<0, max>|float */
    private int|float $timeout = 60;
    private ?LoggerInterface $logger = null;

    /** @var array<string, Client> */
    private array $clients = [];

    /** @var array<string, string> */
    private array $headers = [];

    /** @var array<class-string<MiddlewareInterface>, MiddlewareInterface> */
    private array $middlewares = [];

    public function setTimeout(int|float $timeout): self
    {
        if ($timeout < 0) {
            throw new \InvalidArgumentException("Invalid timeout '{$timeout}' provided");
        }
        $this->timeout = $timeout;

        return $this;
    }

    public function setLogger(LoggerInterface $logger): self
    {
        $this->logger = $logger;

        return $this;
    }

    public function addHeader(string $name, string $content): self
    {
        $this->headers[$name] = $content;

        return $this;
    }

    /**
     * @param iterable<MiddlewareInterface> $middlewares
     */
    public function addMiddlewares(iterable $middlewares): self
    {
        foreach ($middlewares as $middleware) {
            $this->middlewares[$middleware::class] = $middleware;
        }

        return $this;
    }

    public function __invoke(string $uri): Client
    {
        if (!isset($this->clients[$uri])) {
            $this->clients[$uri] = $this->build($uri);
        }

        return $this->clients[$uri];
    }

    private function build(string $uri): Client
    {
        $client = new Client($uri);
        if (null !== $this->logger) {
            $client->setLogger($this->logger);
        }
        $client->setTimeout($this->timeout);

        foreach ($this->headers as $name => $content) {
            $client->addHeader($name, $content);
        }

        foreach ($this->middlewares as $middleware) {
            $client->addMiddleware($middleware);
        }

        return $client;
    }
}
