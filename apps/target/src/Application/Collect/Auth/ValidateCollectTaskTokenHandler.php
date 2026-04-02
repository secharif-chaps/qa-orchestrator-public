<?php

declare(strict_types=1);

namespace App\Application\Collect\Auth;

use App\Domain\Collect\CollectTask;
use App\Domain\Collect\CollectTaskGatewayInterface;
use App\Domain\Collect\Exception\CollectTaskTokenClaimsInvalidException;
use App\Domain\Collect\Exception\CollectTaskTokenClaimsMissingException;
use App\Domain\Collect\Exception\CollectTaskTokenMismatchException;
use App\Domain\Collect\Exception\InvalidCollectTaskTokenException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class ValidateCollectTaskTokenHandler
{
    public function __construct(
        #[Autowire('%app.collect_task.jwt_secret%')]
        private string $collectTaskJWTSecret,
        private CollectTaskGatewayInterface $collectTaskGateway,
    ) {
    }

    public function __invoke(ValidateCollectTaskTokenAction $action): CollectTask
    {
        try {
            $payload = JWT::decode($action->collectTaskToken, new Key($this->collectTaskJWTSecret, 'HS256'));
        } catch (\UnexpectedValueException $e) {
            throw InvalidCollectTaskTokenException::fromDecodingError($e);
        }

        if (!isset($payload->source_id, $payload->watch_file_id, $payload->collect_task_id)) {
            throw CollectTaskTokenClaimsMissingException::create();
        }

        $collectTask = $this->collectTaskGateway->get($payload->collect_task_id);
        $this->validateTokenClaims($collectTask, $payload);

        return $collectTask;
    }

    private function validateTokenClaims(CollectTask $collectTask, object $payload): void
    {
        if (!isset($payload->source_id, $payload->watch_file_id, $payload->collect_task_id)) {
            throw CollectTaskTokenClaimsMissingException::create();
        }

        if (!\is_string($payload->source_id) || !\is_string($payload->watch_file_id) || !\is_string(
            $payload->collect_task_id
        )) {
            throw CollectTaskTokenClaimsInvalidException::invalidType();
        }

        if ($collectTask->getSource()->getId() !== $payload->source_id) {
            throw CollectTaskTokenMismatchException::invalidSourceId();
        }

        if ($collectTask->getWatchFile()->getId() !== $payload->watch_file_id) {
            throw CollectTaskTokenMismatchException::invalidWatchFileId();
        }
    }
}
