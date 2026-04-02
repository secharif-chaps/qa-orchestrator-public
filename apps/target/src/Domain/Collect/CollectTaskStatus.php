<?php

declare(strict_types=1);

namespace App\Domain\Collect;

enum CollectTaskStatus: string
{
    case CREATED = 'created';
    case QUEUED = 'queued';
    case RUNNING = 'running';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';
    private const array TRANSITION = [
        'created' => [self::QUEUED, self::CANCELLED, self::FAILED],
        'queued' => [self::RUNNING, self::CANCELLED, self::FAILED],
        'running' => [self::COMPLETED, self::FAILED, self::CANCELLED],
        'completed' => [],
        'failed' => [],
        'cancelled' => [],
    ];

    /**
     * @var CollectTaskStatus[]
     */
    public const array ACTIVE_STATUSES = [self::CREATED, self::QUEUED, self::RUNNING];

    public function canTransitionTo(self $targetStatus): bool
    {
        if ($this->value === $targetStatus->value) {
            return true;
        }

        return \in_array($targetStatus, self::TRANSITION[$this->value], true);
    }

    public function throwIfInvalidTransition(self $targetStatus): void
    {
        if (!$this->canTransitionTo($targetStatus)) {
            throw new \InvalidArgumentException(\sprintf(
                'Cannot transition from %s to %s',
                $this->value,
                $targetStatus->value,
            ));
        }
    }

    public function isTerminal(): bool
    {
        return empty(self::TRANSITION[$this->value]);
    }

    public function isActive(): bool
    {
        return \in_array($this, self::ACTIVE_STATUSES, true);
    }

    public function isCompleted(): bool
    {
        return self::COMPLETED === $this;
    }

    public function isFailed(): bool
    {
        return self::FAILED === $this;
    }

    public function isCancelled(): bool
    {
        return self::CANCELLED === $this;
    }

    public function isFinished(): bool
    {
        return $this->isCompleted() || $this->isFailed() || $this->isCancelled();
    }
}
