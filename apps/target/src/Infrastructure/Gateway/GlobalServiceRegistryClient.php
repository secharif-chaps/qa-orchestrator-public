<?php

declare(strict_types=1);

namespace App\Infrastructure\Gateway;

use App\Domain\Shared\GatewayRegistryClientInterface;
use Firebase\JWT\JWT;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class GlobalServiceRegistryClient implements GatewayRegistryClientInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire('%env(string:GLOBAL_SERVICE_URL)%')]
        private readonly string $globalServiceUrl,
        #[Autowire('%env(string:INTERNAL_JWT_SECRET)%')]
        private readonly string $internalJwtSecret,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function announce(string $openApiSchemaHash): string
    {
        if ('' === $this->globalServiceUrl) {
            $this->logger?->info('GLOBAL_SERVICE_URL not set, skipping registry announce.');

            return 'skipped';
        }

        if ('' === $this->internalJwtSecret) {
            $this->logger?->warning('INTERNAL_JWT_SECRET not set, skipping registry announce.');

            return 'skipped';
        }

        $token = $this->createInternalToken();
        $announceUrl = rtrim($this->globalServiceUrl, '/') . '/internal/registry/announce/target';

        try {
            $response = $this->httpClient->request('POST', $announceUrl, [
                'json' => [
                    'openapi_hash' => $openApiSchemaHash,
                ],
                'headers' => [
                    'Authorization' => 'Internal ' . $token,
                ],
                'timeout' => 5,
            ]);

            $result = $response->toArray();
            $action = $result['action'] ?? 'unknown';

            $this->logger?->info('Registry announce completed.', [
                'action' => $action,
            ]);

            return $action;
        } catch (\Throwable $e) {
            $this->logger?->warning('Registry announce failed (gateway will detect via healthcheck).', [
                'error' => $e->getMessage(),
            ]);

            return 'error';
        }
    }

    private function createInternalToken(): string
    {
        $now = time();

        return JWT::encode([
            'sub' => 'target-service',
            'username' => 'target',
            'org_id' => 'system',
            'org_name' => 'system',
            'roles' => ['service'],
            'iss' => 'global-gateway',
            'iat' => $now,
            'exp' => $now + 60,
        ], $this->internalJwtSecret, 'HS256');
    }
}
