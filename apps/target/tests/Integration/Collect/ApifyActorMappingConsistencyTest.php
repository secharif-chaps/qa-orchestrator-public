<?php

declare(strict_types=1);

namespace App\Tests\Integration\Collect;

use App\Infrastructure\Collect\SourceTypeProviderResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * CI guard ensuring every SourceType routed to Apify has a configured actor.
 */
#[CoversClass(SourceTypeProviderResolver::class)]
class ApifyActorMappingConsistencyTest extends KernelTestCase
{
    public function testEveryApifyRoutedSourceTypeHasAnActorMapping(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        /** @var array<string, string> $routing */
        $routing = $container->getParameter('app.collect.provider_routing.source_types');

        /** @var array<string, string> $actorMapping */
        $actorMapping = $container->getParameter('app.apify.actor_mapping');

        $apifyRoutedSourceTypes = array_keys(array_filter(
            $routing,
            static fn (string $provider) => 'apify' === $provider,
        ));

        $missingActors = array_filter(
            $apifyRoutedSourceTypes,
            static fn (string $sourceType) => !isset($actorMapping[$sourceType]),
        );

        $this->assertEmpty(
            $missingActors,
            \sprintf(
                'The following SourceTypes are routed to Apify but have no actor configured in app.apify.actor_mapping: %s. '
                . 'Add the missing actor(s) before routing them.',
                implode(', ', $missingActors),
            ),
        );
    }

    public function testEveryActorMappingEntryIsRoutedToApify(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        /** @var array<string, string> $routing */
        $routing = $container->getParameter('app.collect.provider_routing.source_types');

        /** @var array<string, string> $actorMapping */
        $actorMapping = $container->getParameter('app.apify.actor_mapping');

        $unmappedActors = array_filter(
            array_keys($actorMapping),
            static fn (string $sourceType) => !isset($routing[$sourceType]) || 'apify' !== $routing[$sourceType],
        );

        $this->assertEmpty(
            $unmappedActors,
            \sprintf(
                'The following SourceTypes have an Apify actor configured but are not routed to Apify: %s. '
                . 'Either add a routing entry or remove the unused actor mapping.',
                implode(', ', $unmappedActors),
            ),
        );
    }
}
