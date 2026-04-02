<?php

declare(strict_types=1);

namespace App\DataFixtures\Factory\WatchFile;

use App\Domain\Shared\TranslatedText;
use App\Domain\WatchFile\MonitoringType;
use App\Domain\WatchFile\StrategicQuestion;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<StrategicQuestion>
 */
class StrategicQuestionFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return StrategicQuestion::class;
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
     *
     * @return array{
     *     question: TranslatedText,
     *     context: TranslatedText,
     *     monitoringDimension: MonitoringType,
     *     priority: int,
     *     expectedOutputType: string,
     * }
     */
    protected function defaults(): array
    {
        return [
            'question' => new TranslatedText(self::faker()->sentence(), self::faker()->sentence()),
            'context' => new TranslatedText(self::faker()->paragraph(), self::faker()->paragraph()),
            'monitoringDimension' => MonitoringType::COMPETITIVE,
            'priority' => self::faker()->numberBetween(1, 10),
            'expectedOutputType' => 'analysis',
        ];
    }
}
