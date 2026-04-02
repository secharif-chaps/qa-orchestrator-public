<?php

declare(strict_types=1);

namespace App\UserInterface\Http;

use Doctrine\DBAL\Connection;
use OpenSearch\Client as OpenSearchClient;
use Predis\Client as PredisClient;
use Predis\Response\Status;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class HealthCheckController
{
    /** @var list<string> */
    private readonly array $allowedIps;

    public function __construct(
        private readonly Connection $connection,
        private readonly OpenSearchClient $openSearch,
        private readonly RateLimiterFactoryInterface $healthcheckApiLimiter,
        #[Autowire(env: 'VALKEY_DSN')]
        private readonly string $valkeyDsn,
        #[Autowire(env: 'MESSENGER_TRANSPORT_DSN')]
        private readonly string $messengerDsn,
        #[Autowire(env: 'HEALTHCHECK_ALLOWED_IPS')]
        string $allowedIpsString = '',
    ) {
        $this->allowedIps = array_values(array_filter(array_map('trim', explode(',', $allowedIpsString))));
    }

    #[Route('/api/healthcheck', methods: ['GET'])]
    public function __invoke(Request $request): JsonResponse
    {
        $clientIp = $request->getClientIp();
        $limiter = $this->healthcheckApiLimiter->create($clientIp ?? 'unknown');
        $limit = $limiter->consume();

        $headers = [
            'X-RateLimit-Remaining' => (string) $limit->getRemainingTokens(),
            'X-RateLimit-Retry-After' => (string) ($limit->getRetryAfter()->getTimestamp() - time()),
            'X-RateLimit-Limit' => (string) $limit->getLimit(),
        ];

        if (!$limit->isAccepted()) {
            throw new TooManyRequestsHttpException(
                retryAfter: (int) $headers['X-RateLimit-Retry-After'],
                headers: $headers,
            );
        }

        $showDetails = $this->isIpAllowed($clientIp);

        $checks = [
            'database' => $this->checkDatabase($showDetails),
            'document_store' => $this->checkDocumentStore($showDetails),
            'cache' => $this->checkCache($showDetails),
            'message_broker' => $this->checkMessageBroker($showDetails),
        ];

        $allHealthy = [] === array_filter($checks, static fn (array $check): bool => false === $check['healthy']);

        $response = new JsonResponse([
            'status' => $allHealthy ? 'ok' : 'degraded',
            'timestamp' => date('c'),
            'checks' => $checks,
        ], $allHealthy ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE);

        $response->headers->add($headers);

        return $response;
    }

    /**
     * @return array{healthy: bool, message?: string}
     */
    private function checkDatabase(bool $showDetails): array
    {
        try {
            $this->connection->executeQuery('SELECT 1');

            return [
                'healthy' => true,
            ];
        } catch (\Throwable $e) {
            return $showDetails
                ? [
                    'healthy' => false,
                    'message' => $e->getMessage(),
                ]
                : [
                    'healthy' => false,
                ];
        }
    }

    /**
     * @return array{healthy: bool, message?: string}
     */
    private function checkDocumentStore(bool $showDetails): array
    {
        try {
            $ping = $this->openSearch->ping();

            if ($ping) {
                return [
                    'healthy' => true,
                ];
            }

            return $showDetails
                ? [
                    'healthy' => false,
                    'message' => 'Ping failed',
                ]
                : [
                    'healthy' => false,
                ];
        } catch (\Throwable $e) {
            return $showDetails
                ? [
                    'healthy' => false,
                    'message' => $e->getMessage(),
                ]
                : [
                    'healthy' => false,
                ];
        }
    }

    /**
     * @return array{healthy: bool, message?: string}
     */
    private function checkCache(bool $showDetails): array
    {
        try {
            $client = new PredisClient($this->valkeyDsn);
            $response = $client->ping();
            $responseStr = $response instanceof Status ? $response->getPayload() : null;

            if ('PONG' === $responseStr) {
                return [
                    'healthy' => true,
                ];
            }

            return $showDetails
                ? [
                    'healthy' => false,
                    'message' => 'Unexpected response: ' . ($responseStr ?? 'unknown'),
                ]
                : [
                    'healthy' => false,
                ];
        } catch (\Throwable $e) {
            return $showDetails
                ? [
                    'healthy' => false,
                    'message' => $e->getMessage(),
                ]
                : [
                    'healthy' => false,
                ];
        }
    }

    /**
     * @return array{healthy: bool, message?: string}
     */
    private function checkMessageBroker(bool $showDetails): array
    {
        try {
            $parsedUrl = parse_url($this->messengerDsn);
            if (false === $parsedUrl || !isset($parsedUrl['host'])) {
                return $showDetails
                    ? [
                        'healthy' => false,
                        'message' => 'Invalid DSN',
                    ]
                    : [
                        'healthy' => false,
                    ];
            }

            $host = $parsedUrl['host'];
            $port = $parsedUrl['port'] ?? 5672;

            $socket = @fsockopen($host, $port, $errno, $errstr, 5);
            if (false === $socket) {
                return $showDetails
                    ? [
                        'healthy' => false,
                        'message' => "Connection failed: $errstr ($errno)",
                    ]
                    : [
                        'healthy' => false,
                    ];
            }

            fclose($socket);

            return [
                'healthy' => true,
            ];
        } catch (\Throwable $e) {
            return $showDetails
                ? [
                    'healthy' => false,
                    'message' => $e->getMessage(),
                ]
                : [
                    'healthy' => false,
                ];
        }
    }

    /**
     * Check if the given IP address is allowed to see detailed health info.
     */
    private function isIpAllowed(?string $clientIp): bool
    {
        if (null === $clientIp) {
            return false;
        }

        foreach ($this->allowedIps as $allowedIp) {
            if (str_contains($allowedIp, '/')) {
                if ($this->ipMatchesCidr($clientIp, $allowedIp)) {
                    return true;
                }
            } elseif ($clientIp === $allowedIp) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if an IP address matches a CIDR range.
     */
    private function ipMatchesCidr(string $ip, string $cidr): bool
    {
        [$subnet, $mask] = explode('/', $cidr);

        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);

        if (false === $ipLong || false === $subnetLong) {
            return false;
        }

        $maskLong = -1 << (32 - (int) $mask);

        return ($ipLong & $maskLong) === ($subnetLong & $maskLong);
    }
}
