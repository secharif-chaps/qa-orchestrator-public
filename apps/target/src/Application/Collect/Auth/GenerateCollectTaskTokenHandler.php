<?php

declare(strict_types=1);

namespace App\Application\Collect\Auth;

use App\Domain\Collect\CollectTaskGatewayInterface;
use Firebase\JWT\JWT;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class GenerateCollectTaskTokenHandler
{
    private const int DEFAULT_EXPIRATION = 31_536_000; // 1 year in seconds
    private const string JWT_ALGO = 'HS256';

    public function __construct(
        #[Autowire('%app.collect_task.jwt_secret%')]
        private string $collectTaskJWTSecret,
        private CollectTaskGatewayInterface $collectTaskGateway,
    ) {
    }

    public function __invoke(GenerateCollectTaskTokenAction $action): string
    {
        $collectTask = $this->collectTaskGateway->get($action->collectTaskId);

        $sourceId = $collectTask
            ->getSource()
            ->getId();

        $watchFileId = $collectTask
            ->getWatchFile()
            ->getId();

        $collectTaskId = $collectTask->getId();

        $now = time();

        $claims = [
            'source_id' => $sourceId,
            'watch_file_id' => $watchFileId,
            'collect_task_id' => $collectTaskId,
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + self::DEFAULT_EXPIRATION,
        ];

        return JWT::encode($claims, $this->collectTaskJWTSecret, self::JWT_ALGO);
    }
}
