<?php

declare(strict_types=1);

namespace App\Tests\Integration\Collect;

use App\Domain\Actor\Actor;
use App\Domain\Collect\ProviderGatewayLocatorInterface;
use App\Domain\Collect\ProviderResolverInterface;
use App\Domain\Organisation\Organisation;
use App\Domain\Shared\TranslatedText;
use App\Domain\Source\Source;
use App\Domain\Source\SourceType;
use App\Infrastructure\Collect\SourceTypeProviderResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * CI test ensuring every SourceType enum value resolves to a registered provider.
 *
 * Prevents adding a new SourceType without a valid provider mapping.
 */
#[CoversClass(SourceTypeProviderResolver::class)]
class SourceTypeProviderMappingTest extends KernelTestCase
{
    /**
     * @return array<string, array{SourceType}>
     */
    public static function allSourceTypesProvider(): array
    {
        $cases = [];
        foreach (SourceType::cases() as $case) {
            $cases[$case->value] = [$case];
        }

        return $cases;
    }

    #[DataProvider('allSourceTypesProvider')]
    public function testSourceTypeResolvesToRegisteredProvider(SourceType $sourceType): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $resolver = $container->get(ProviderResolverInterface::class);
        $locator = $container->get(ProviderGatewayLocatorInterface::class);

        $source = new Source(
            'Test Source',
            TranslatedText::fromArray([
                'fr' => 'desc',
                'en' => 'desc',
            ]),
            $sourceType,
            'https://example.com',
            'example.com',
            TranslatedText::fromArray([
                'fr' => 'rel',
                'en' => 'rel',
            ]),
            new Actor('Test Actor', new Organisation('Test Org', 'test-org-id')),
        );

        $providerName = $resolver->resolve($source);

        $this->assertNotEmpty($providerName, \sprintf(
            'SourceType "%s" resolved to an empty provider name',
            $sourceType->value
        ));

        $this->assertTrue(
            $locator->has($providerName),
            \sprintf(
                'SourceType "%s" resolved to provider "%s" which is not registered in the ProviderGatewayLocator',
                $sourceType->value,
                $providerName
            )
        );
    }

    /**
     * Ensures that SourceTypes explicitly routed to Apify in the configuration
     * do resolve to the 'apify' provider name — not a fallback or wrong provider.
     */
    public function testApifySourceTypesAreRoutedToApify(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $resolver = $container->get(ProviderResolverInterface::class);

        /** @var array<string, string> $routing */
        $routing = $container->getParameter('app.collect.provider_routing.source_types');

        $apifySourceTypeValues = array_keys(array_filter(
            $routing,
            static fn (string $provider) => 'apify' === $provider,
        ));

        $this->assertNotEmpty(
            $apifySourceTypeValues,
            'At least one SourceType must be configured to route to the apify provider',
        );

        $organisation = new Organisation('Test Org', 'test-org-id');
        $actor = new Actor('Test Actor', $organisation);

        foreach ($apifySourceTypeValues as $sourceTypeValue) {
            $sourceType = SourceType::from($sourceTypeValue);

            $source = new Source(
                name: 'Test Source',
                description: TranslatedText::fromArray([
                    'fr' => 'desc',
                    'en' => 'desc',
                ]),
                type: $sourceType,
                url: 'https://example.com',
                primaryDomain: 'example.com',
                relevance: TranslatedText::fromArray([
                    'fr' => 'rel',
                    'en' => 'rel',
                ]),
                actor: $actor,
            );

            $providerName = $resolver->resolve($source);

            $this->assertSame(
                'apify',
                $providerName,
                \sprintf(
                    'SourceType "%s" is configured to route to apify but resolver returned "%s"',
                    $sourceTypeValue,
                    $providerName,
                ),
            );
        }
    }
}
