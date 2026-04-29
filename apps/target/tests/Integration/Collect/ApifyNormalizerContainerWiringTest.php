<?php

declare(strict_types=1);

namespace App\Tests\Integration\Collect;

use App\Domain\Actor\Actor;
use App\Domain\Collect\ApifyDocumentNormalizerInterface;
use App\Domain\Collect\ApifyNormalizerResolverInterface;
use App\Domain\Collect\CollectTask;
use App\Domain\Collect\CollectTaskStatus;
use App\Domain\Organisation\Organisation;
use App\Domain\Shared\TranslatedText;
use App\Domain\Source\Source;
use App\Domain\Source\SourceType;
use App\Domain\WatchFile\WatchFile;
use App\Infrastructure\Collect\Apify\Normalizer\ApifyNormalizerResolver;
use App\Infrastructure\Collect\Apify\Normalizer\GenericApifyNormalizer;
use App\Infrastructure\Collect\Apify\Normalizer\GoogleNewsNormalizer;
use App\Infrastructure\Collect\Apify\Normalizer\LinkedInProfileNormalizer;
use App\Infrastructure\Collect\Apify\Normalizer\WebsiteCrawlerNormalizer;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Verifies that the Apify normalizers are correctly wired in the DI container
 * and that the resolver dispatches to the right normalizer based on the actor ID
 * embedded in the CollectTask's providerTaskId.
 */
#[CoversClass(ApifyNormalizerResolver::class)]
class ApifyNormalizerContainerWiringTest extends KernelTestCase
{
    public function testNormalizerResolverIsWiredCorrectly(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $resolver = $container->get(ApifyNormalizerResolverInterface::class);

        $this->assertInstanceOf(ApifyNormalizerResolver::class, $resolver);
    }

    public function testWebsiteCrawlerNormalizerIsTagged(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        /** @var ApifyNormalizerResolverInterface $resolver */
        $resolver = $container->get(ApifyNormalizerResolverInterface::class);

        // providerTaskId format: "apifyActorId:runId"
        $collectTask = $this->buildCollectTask('apify/website-content-crawler:run123');
        $normalizer = $resolver->resolveFor($collectTask);

        $this->assertInstanceOf(
            ApifyDocumentNormalizerInterface::class,
            $normalizer,
            'Resolver must return an ApifyDocumentNormalizerInterface',
        );
        $this->assertInstanceOf(
            WebsiteCrawlerNormalizer::class,
            $normalizer,
            'Actor "apify/website-content-crawler" must resolve to WebsiteCrawlerNormalizer',
        );
    }

    public function testGoogleNewsNormalizerIsTagged(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        /** @var ApifyNormalizerResolverInterface $resolver */
        $resolver = $container->get(ApifyNormalizerResolverInterface::class);

        $collectTask = $this->buildCollectTask('lhotanova/google-news-scraper:run123');
        $normalizer = $resolver->resolveFor($collectTask);

        $this->assertInstanceOf(
            GoogleNewsNormalizer::class,
            $normalizer,
            'Actor "lhotanova/google-news-scraper" must resolve to GoogleNewsNormalizer',
        );
    }

    public function testLinkedInNormalizerIsTagged(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        /** @var ApifyNormalizerResolverInterface $resolver */
        $resolver = $container->get(ApifyNormalizerResolverInterface::class);

        $collectTask = $this->buildCollectTask('curious_coder/linkedin-profile-scraper:run123');
        $normalizer = $resolver->resolveFor($collectTask);

        $this->assertInstanceOf(
            LinkedInProfileNormalizer::class,
            $normalizer,
            'Actor "curious_coder/linkedin-profile-scraper" must resolve to LinkedInProfileNormalizer',
        );
    }

    public function testFallbackNormalizerUsedForUnknownActor(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        /** @var ApifyNormalizerResolverInterface $resolver */
        $resolver = $container->get(ApifyNormalizerResolverInterface::class);

        $collectTask = $this->buildCollectTask('unknown/actor:run123');
        $normalizer = $resolver->resolveFor($collectTask);

        $this->assertInstanceOf(
            GenericApifyNormalizer::class,
            $normalizer,
            'Unknown actor must fall back to GenericApifyNormalizer',
        );
    }

    /**
     * Builds a minimal CollectTask with the given providerTaskId for resolver testing.
     * Domain objects are constructed in-memory without persisting to the database.
     */
    private function buildCollectTask(string $providerTaskId): CollectTask
    {
        $organisation = new Organisation('Test Org', 'test-org-keycloak-id');
        $actor = new Actor('Test Actor', $organisation);
        $watchFile = new WatchFile('Test WatchFile', 'Test objective', $organisation);

        $source = new Source(
            name: 'Test Source',
            description: TranslatedText::fromArray([
                'fr' => 'desc',
                'en' => 'desc',
            ]),
            type: SourceType::WEBSITE,
            url: 'https://example.com',
            primaryDomain: 'example.com',
            relevance: TranslatedText::fromArray([
                'fr' => 'rel',
                'en' => 'rel',
            ]),
            actor: $actor,
            watchFile: $watchFile,
        );

        return new CollectTask(
            source: $source,
            watchFile: $watchFile,
            providerName: 'apify',
            configuration: [],
            providerTaskId: $providerTaskId,
            status: CollectTaskStatus::QUEUED,
        );
    }
}
