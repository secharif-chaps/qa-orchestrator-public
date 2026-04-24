<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\Collect\Apify;

use App\Domain\Collect\ApifyInputTemplate;
use App\Domain\Collect\Exception\ApifyConfigurationException;
use App\Domain\Source\SourceType;
use App\Infrastructure\Collect\Apify\ApifyInputTemplateProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ApifyInputTemplateProvider::class)]
class ApifyInputTemplateProviderTest extends TestCase
{
    private ApifyInputTemplateProvider $provider;

    protected function setUp(): void
    {
        $config = [
            'apify/website-content-crawler' => [
                'name' => 'Website Crawler',
                'source_types' => ['website', 'blog'],
                'defaults' => [
                    'startUrls' => ['{{source.url}}'],
                    'maxCrawlPages' => '50',
                ],
                'required_fields' => ['startUrls'],
            ],
            'lhotanova/google-news-scraper' => [
                'name' => 'Google News',
                'source_types' => ['news:newsapi'],
                'defaults' => [
                    'keywords' => ['{{source.query}}'],
                    'maxArticles' => '50',
                ],
                'required_fields' => ['keywords'],
            ],
        ];

        $this->provider = new ApifyInputTemplateProvider($config);
    }

    public function testGetsTemplateForActorId(): void
    {
        $template = $this->provider->getTemplateForActor('apify/website-content-crawler');

        $this->assertNotNull($template);
        $this->assertInstanceOf(ApifyInputTemplate::class, $template);
        $this->assertArrayHasKey('startUrls', $template->defaults);
    }

    public function testReturnsNullForUnknownActor(): void
    {
        $template = $this->provider->getTemplateForActor('unknown/actor');

        $this->assertNull($template);
    }

    public function testGetsTemplateForSourceType(): void
    {
        $template = $this->provider->getTemplateForSourceType(SourceType::WEBSITE);

        $this->assertNotNull($template);
        $this->assertInstanceOf(ApifyInputTemplate::class, $template);
    }

    public function testReturnsNullForUnknownSourceType(): void
    {
        $template = $this->provider->getTemplateForSourceType(SourceType::DOCUMENT_ESPACENET_ADVANCED_SEARCH);

        $this->assertNull($template);
    }

    public function testTemplateHasRequiredFields(): void
    {
        $template = $this->provider->getTemplateForActor('apify/website-content-crawler');

        $this->assertNotNull($template);
        $this->assertContains('startUrls', $template->getRequiredFields());
    }

    public function testTemplateDefaults(): void
    {
        $template = $this->provider->getTemplateForActor('apify/website-content-crawler');

        $this->assertNotNull($template);
        $this->assertEquals('50', $template->defaults['maxCrawlPages']);
    }

    public function testThrowsOnInvalidTemplateDefaults(): void
    {
        $this->expectException(ApifyConfigurationException::class);

        $config = [
            'bad/actor' => [
                'defaults' => 'not-an-array',  // Invalid!
                'required_fields' => [],
            ],
        ];

        $provider = new ApifyInputTemplateProvider($config);
        $provider->getTemplateForActor('bad/actor');
    }

    public function testMultipleSourceTypesForSameActor(): void
    {
        // Website template supports both 'website' and 'blog'
        $websiteTemplate = $this->provider->getTemplateForSourceType(SourceType::WEBSITE);
        $blogTemplate = $this->provider->getTemplateForSourceType(SourceType::BLOG);

        // Both should get the same template
        $this->assertNotNull($websiteTemplate);
        $this->assertNotNull($blogTemplate);
        $this->assertEquals($websiteTemplate->apifyActorId, $blogTemplate->apifyActorId);
    }
}
