<?php

declare(strict_types=1);

namespace App\DataFixtures\Factory\WatchFile;

use App\Domain\Actor\ActorType;
use App\Domain\Shared\TranslatedText;
use App\Domain\WatchFile\WatchFileActor;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<WatchFileActor>
 */
class WatchFileActorFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return WatchFileActor::class;
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
     *
     * @return array{
     *     type: ActorType,
     *     score: float,
     *     explanation: ?TranslatedText,
     * }
     */
    protected function defaults(): array
    {
        /** @var ActorType $type */
        $type = self::faker()->randomElement([
            ActorType::COMPETITOR,
            ActorType::PARTNER,
            ActorType::SUPPLIER,
            ActorType::CUSTOMER,
        ]);

        $explanations = [
            [
                'en' => 'This actor is a major competitor in the market',
                'fr' => 'Cet acteur est un concurrent majeur sur le marché',
            ],
            [
                'en' => 'Strategic partner for business development',
                'fr' => 'Partenaire stratégique pour le développement commercial',
            ],
            [
                'en' => 'Key supplier of essential resources',
                'fr' => 'Fournisseur clé de ressources essentielles',
            ],
            [
                'en' => 'Important customer with high value',
                'fr' => 'Client important avec une forte valeur',
            ],
        ];
        $rawExplanation = self::faker()->optional(0.8)->randomElement($explanations);
        $explanation = (\is_array($rawExplanation)
            && isset($rawExplanation['en'], $rawExplanation['fr'])
            && \is_string($rawExplanation['en'])
            && \is_string($rawExplanation['fr'])
        ) ? new TranslatedText(fr: $rawExplanation['fr'], en: $rawExplanation['en']) : null;

        return [
            'type' => $type,
            'score' => self::faker()->randomFloat(2, 0.1, 1.0),
            'explanation' => $explanation,
        ];
    }

    public function withExplanation(TranslatedText $explanation): self
    {
        return $this->with([
            'explanation' => $explanation,
        ]);
    }
}
