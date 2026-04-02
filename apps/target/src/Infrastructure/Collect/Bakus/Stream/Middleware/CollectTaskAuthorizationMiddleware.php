<?php

declare(strict_types=1);

namespace App\Infrastructure\Collect\Bakus\Stream\Middleware;

use App\Application\Collect\Auth\ValidateCollectTaskTokenAction;
use App\Domain\Collect\CollectTask;
use App\Domain\Collect\Exception\UnknownProviderException;
use App\Domain\Collect\ProviderGatewayInterface;
use App\Domain\Collect\ProviderGatewayLocatorInterface;
use App\Domain\Shared\DomainException;
use Nyholm\Psr7\Response;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;
use WebSocket\Connection;
use WebSocket\Middleware\ProcessHttpIncomingInterface;
use WebSocket\Middleware\ProcessHttpStack;
use WebSocket\Trait\LoggerAwareTrait;
use WebSocket\Trait\StringableTrait;

class CollectTaskAuthorizationMiddleware implements ProcessHttpIncomingInterface
{
    use HandleTrait;
    use LoggerAwareTrait;
    use StringableTrait;

    public function __construct(
        private MessageBusInterface $messageBus,
        private ProviderGatewayInterface $providerGateway,
        private ProviderGatewayLocatorInterface $providerLocator,
        ?LoggerInterface $logger,
    ) {
        $this->initLogger($logger);
    }

    public function processHttpIncoming(ProcessHttpStack $stack, Connection $connection): MessageInterface
    {
        $message = $stack->handleHttpIncoming();
        if (!$message instanceof ServerRequestInterface) {
            return $message;
        }

        $collectTaskId = $connection->getMeta('collect_task_id');
        // If already authenticated (cache hit), skip authorization
        if (null !== $collectTaskId) {
            return $message;
        }

        // If no Authorization header, reject the request
        if (!$message->hasHeader('Authorization')) {
            $this->logger->warning(
                '[collect-task-authorization] Unauthorized access attempt: Missing Authorization header.'
            );

            return $this->rejectWithUnauthorized($connection, $message, 'Missing Authorization header.');
        }

        $authHeader = $message->getHeaderLine('Authorization');
        if (!str_starts_with($authHeader, 'Bearer ')) {
            $this->logger->warning(
                '[collect-task-authorization] Unauthorized access attempt: Invalid Authorization header format.'
            );

            return $this->rejectWithUnauthorized($connection, $message, 'Invalid Authorization header format.');
        }

        try {
            $token = substr($authHeader, 7); // Remove 'Bearer ' prefix

            /** @var CollectTask $collectTask */
            $collectTask = $this->handle(new ValidateCollectTaskTokenAction($token));

            $providerName = $collectTask->getProviderName();
            if ('' === $providerName || !$this->providerLocator->has($providerName)) {
                $this->logger->error(
                    "[collect-task-authorization] Unknown provider \"{$providerName}\" for CollectTask ID {$collectTask->getId()}."
                );

                throw UnknownProviderException::withName($providerName);
            }

            if (!$collectTask->getStatus()->isActive()) {
                $this->logger->error(
                    "[collect-task-authorization] Unauthorized access attempt: CollectTask ID {$collectTask->getId()} is not active."
                );

                // Notify provider to clean up the task
                $providerCollectTaskId = $collectTask->getProviderTaskId();
                if ($providerCollectTaskId) {
                    try {
                        $this->providerGateway->cancelTask($providerCollectTaskId);
                        $this->logger->info(
                            "[collect-task-authorization] Inactive CollectTask ID {$collectTask->getId()} cleaned up from provider."
                        );
                    } catch (\Exception $e) {
                        $this->logger->error(
                            "[collect-task-authorization] Failed to clean up inactive CollectTask ID {$collectTask->getId()} from provider: {$e->getMessage()}"
                        );
                    }
                }

                return $this->rejectWithUnauthorized($connection, $message, 'Collect task is not active.');
            }

            $connection->setMeta('collect_task_id', $collectTask->getId());
            $connection->setMeta('provider_task_id', $collectTask->getProviderTaskId());
            $connection->setMeta('connection_id',
                implode('_', [$collectTask->getId(), microtime(true), bin2hex(random_bytes(4))])
            );

            $this->logger->info(
                "[collect-task-authorization] Authorized connection for CollectTask ID: {$collectTask->getId()}"
            );
        } catch (DomainException $e) {
            $this->logger->warning("[collect-task-authorization] Unauthorized access attempt: {$e->getMessage()}");

            return $this->rejectWithUnauthorized($connection, $message, $e->getMessage());
        }

        return $message;
    }

    private function rejectWithUnauthorized(
        Connection $connection,
        ServerRequestInterface $request,
        string $reason,
    ): ServerRequestInterface {
        $response = new Response(
            401,
            [
                'Content-Type' => 'text/plain',
            ],
            'Unauthorized: ' . $reason
        );

        $connection->setHandshakeResponse($response);

        return $request;
    }
}
