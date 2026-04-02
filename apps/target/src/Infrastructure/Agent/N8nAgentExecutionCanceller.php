<?php

declare(strict_types=1);

namespace App\Infrastructure\Agent;

use App\Domain\Agent\AgentExecutionCancellerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class N8nAgentExecutionCanceller implements AgentExecutionCancellerInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire('%env(N8N_API_URL)%')]
        private readonly string $apiUrl,
        #[Autowire('%env(N8N_API_KEY)%')]
        private readonly string $apiKey,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function cancel(string $executionId): void
    {
        if ('' === $this->apiUrl || '' === $this->apiKey) {
            $this->logger?->warning('Agent execution cancellation skipped: missing API URL or API key', [
                'execution_id' => $executionId,
            ]);

            return;
        }

        try {
            $response = $this->httpClient->request(
                'POST',
                $this->apiUrl . '/api/v1/executions/' . $executionId . '/stop',
                [
                    'headers' => [
                        'X-N8N-API-KEY' => $this->apiKey,
                    ],
                ]
            );

            $response->getHeaders();

            $this->logger?->info('Agent execution cancelled successfully', [
                'execution_id' => $executionId,
            ]);
        } catch (ClientExceptionInterface $e) {
            $this->logger?->warning('Client error when cancelling agent execution', [
                'execution_id' => $executionId,
                'status_code' => $e->getResponse()
->getStatusCode(),
                'error' => $e->getMessage(),
            ]);
        } catch (ServerExceptionInterface $e) {
            $this->logger?->error('Server error when cancelling agent execution', [
                'execution_id' => $executionId,
                'status_code' => $e->getResponse()
->getStatusCode(),
                'error' => $e->getMessage(),
            ]);
        } catch (TransportExceptionInterface $e) {
            $this->logger?->error('Transport error when cancelling agent execution', [
                'execution_id' => $executionId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
