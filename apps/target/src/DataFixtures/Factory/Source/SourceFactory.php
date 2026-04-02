<?php

declare(strict_types=1);

namespace App\DataFixtures\Factory\Source;

use App\DataFixtures\Factory\Actor\ActorFactory;
use App\DataFixtures\Factory\WatchFile\WatchFileFactory;
use App\Domain\Actor\Actor;
use App\Domain\Shared\TranslatedText;
use App\Domain\Source\Source;
use App\Domain\Source\SourceStatus;
use App\Domain\Source\SourceType;
use App\Domain\WatchFile\WatchFile;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Source>
 */
class SourceFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Source::class;
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
     *
     * @return array{
     *     name: string,
     *     description: TranslatedText,
     *     type: SourceType,
     *     url: string,
     *     primaryDomain: string,
     *     query: string|null,
     *     relevance: TranslatedText,
     *     parameters: array<string, mixed>|null,
     *     watchFile: WatchFileFactory,
     *     actor: ActorFactory,
     *     addedByMessage: null,
     * }
     */
    protected function defaults(): array
    {
        /** @var SourceType $randomType */
        $randomType = self::faker()->randomElement(
            array_filter(SourceType::cases(), static fn (SourceType $type): bool => !$type->isInternal()),
        );

        return [
            'name' => self::faker()->company(),
            'description' => new TranslatedText(fr: self::faker()->sentence(), en: self::faker()->sentence()),
            'type' => $randomType,
            'url' => self::faker()->url(),
            'primaryDomain' => self::faker()->domainName(),
            'query' => null,
            'relevance' => new TranslatedText(fr: self::faker()->sentence(), en: self::faker()->sentence()),
            'parameters' => null,
            'watchFile' => WatchFileFactory::new(),
            'actor' => ActorFactory::new(),
            'addedByMessage' => null,
        ];
    }

    public function withWatchFile(WatchFile $watchFile): self
    {
        return $this->with([
            'watchFile' => $watchFile,
        ]);
    }

    public function withActor(Actor $actor): self
    {
        return $this->with([
            'actor' => $actor,
        ]);
    }

    public function withStatus(SourceStatus $status): self
    {
        return $this->afterInstantiate(function (Source $source) use ($status) {
            $source->setStatus($status);
        });
    }
}
