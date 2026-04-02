<?php

declare(strict_types=1);

namespace App\DataFixtures\Factory\Collect;

use App\DataFixtures\Factory\Source\SourceFactory;
use App\DataFixtures\Factory\WatchFile\WatchFileFactory;
use App\Domain\Collect\CollectTask;
use App\Domain\Collect\CollectTaskStatus;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<CollectTask>
 */
class CollectTaskFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return CollectTask::class;
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
     *
     * @return array{
     *     source: SourceFactory,
     *     watchFile: WatchFileFactory,
     *     providerName: string,
     *     configuration: array<string, mixed>,
     *     providerTaskId: string|null,
     *     status: CollectTaskStatus,
     * }
     */
    protected function defaults(): array
    {
        /** @var CollectTaskStatus $randomStatus */
        $randomStatus = self::faker()->randomElement(CollectTaskStatus::cases());

        return [
            'source' => SourceFactory::new(),
            'watchFile' => WatchFileFactory::new(),
            'providerName' => 'bakus',
            'configuration' => [
                'query' => self::faker()->sentence(),
                'max_results' => self::faker()->numberBetween(10, 100),
            ],
            'providerTaskId' => self::faker()->optional()->uuid(),
            'status' => $randomStatus,
        ];
    }

    public function withStatus(CollectTaskStatus $status): self
    {
        return $this->with([
            'status' => $status,
        ]);
    }

    public function created(): self
    {
        return $this->withStatus(CollectTaskStatus::CREATED);
    }

    public function queued(): self
    {
        return $this->with([
            'status' => CollectTaskStatus::QUEUED,
            'providerTaskId' => self::faker()->uuid(),
        ]);
    }

    public function running(): self
    {
        return $this->with([
            'status' => CollectTaskStatus::RUNNING,
            'providerTaskId' => self::faker()->uuid(),
        ]);
    }

    public function completed(): self
    {
        return $this->with([
            'status' => CollectTaskStatus::COMPLETED,
            'providerTaskId' => self::faker()->uuid(),
        ]);
    }

    public function failed(): self
    {
        return $this->with([
            'status' => CollectTaskStatus::FAILED,
            'providerTaskId' => self::faker()->uuid(),
        ]);
    }

    public function cancelled(): self
    {
        return $this->with([
            'status' => CollectTaskStatus::CANCELLED,
        ]);
    }
}
