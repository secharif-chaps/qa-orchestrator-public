<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\Collect\Apify\Normalizer;

use App\Domain\Collect\NormalizerContext;
use App\Domain\Document\HtmlMetadata;
use App\Infrastructure\Collect\Apify\Normalizer\LinkedInProfileNormalizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(LinkedInProfileNormalizer::class)]
class LinkedInProfileNormalizerTest extends TestCase
{
    private LinkedInProfileNormalizer $normalizer;
    private NormalizerContext $context;

    protected function setUp(): void
    {
        $this->normalizer = new LinkedInProfileNormalizer();
        $this->context = new NormalizerContext('curious_coder/linkedin-profile-scraper', 'dataset-789', 0);
    }

    public function testNormalizeReturnsNullWhenUrlMissing(): void
    {
        // Arrange — no profileUrl, url, or linkedInUrl
        $item = [
            'name' => 'John Doe',
            'headline' => 'Software Engineer',
            'about' => 'This is a sufficiently long about section for the test.',
        ];

        // Act
        $result = $this->normalizer->normalize($item, $this->context);

        // Assert
        $this->assertNull($result);
    }

    public function testNormalizeReturnsNullWhenExcerptTooShort(): void
    {
        // Arrange — about section is too short (less than 10 chars)
        $item = [
            'url' => 'https://www.linkedin.com/in/johndoe',
            'name' => 'John Doe',
            'about' => 'Short', // less than 10 chars
        ];

        // Act
        $result = $this->normalizer->normalize($item, $this->context);

        // Assert
        $this->assertNull($result);
    }

    public function testNormalizeBuildsFullTitleFromNameAndHeadline(): void
    {
        // Arrange
        $item = [
            'url' => 'https://www.linkedin.com/in/janedoe',
            'name' => 'Jane Doe',
            'headline' => 'Senior Product Manager',
            'about' => 'Experienced product manager with over 10 years in the industry.',
        ];

        // Act
        $result = $this->normalizer->normalize($item, $this->context);

        // Assert
        $this->assertNotNull($result);
        $this->assertSame('Jane Doe - Senior Product Manager', $result->getTitle());
    }

    public function testNormalizeUsesNameAloneWhenHeadlineMissing(): void
    {
        // Arrange
        $item = [
            'url' => 'https://www.linkedin.com/in/johndoe',
            'name' => 'John Doe',
            'about' => 'Experienced software developer building modern web applications.',
        ];

        // Act
        $result = $this->normalizer->normalize($item, $this->context);

        // Assert
        $this->assertNotNull($result);
        $this->assertSame('John Doe', $result->getTitle());
    }

    public function testNormalizeUsesHeadlineAloneWhenNameMissing(): void
    {
        // Arrange
        $item = [
            'url' => 'https://www.linkedin.com/in/profile',
            'headline' => 'Lead Software Architect',
            'about' => 'Architecting scalable systems and mentoring engineering teams.',
        ];

        // Act
        $result = $this->normalizer->normalize($item, $this->context);

        // Assert
        $this->assertNotNull($result);
        $this->assertSame('Lead Software Architect', $result->getTitle());
    }

    public function testNormalizeCreatesDocumentFromFullItem(): void
    {
        // Arrange
        $item = [
            'url' => 'https://www.linkedin.com/in/fullprofile',
            'name' => 'Alice Martin',
            'headline' => 'Data Scientist',
            'about' => 'Passionate about machine learning and data-driven decision making.',
            'experience' => [
                [
                    'title' => 'Data Scientist',
                    'company' => 'TechCorp',
                ],
            ],
        ];

        // Act
        $result = $this->normalizer->normalize($item, $this->context);

        // Assert
        $this->assertNotNull($result);
        $this->assertSame('https://www.linkedin.com/in/fullprofile', $result->getUrl());
        $this->assertSame('Alice Martin - Data Scientist', $result->getTitle());
        $this->assertStringContainsString('Passionate about machine learning', $result->getContent());
    }

    public function testNormalizeAppendsExperienceToContent(): void
    {
        // Arrange
        $item = [
            'url' => 'https://www.linkedin.com/in/engineer',
            'name' => 'Bob Smith',
            'about' => 'Experienced backend engineer specializing in distributed systems.',
            'experience' => [
                [
                    'title' => 'Backend Engineer',
                    'company' => 'StartupXYZ',
                ],
                [
                    'title' => 'Junior Developer',
                    'company' => 'OldCorp',
                ],
            ],
        ];

        // Act
        $result = $this->normalizer->normalize($item, $this->context);

        // Assert
        $this->assertNotNull($result);
        $this->assertStringContainsString('Experience:', $result->getContent());
        $this->assertStringContainsString('Backend Engineer @ StartupXYZ', $result->getContent());
        $this->assertStringContainsString('Junior Developer @ OldCorp', $result->getContent());
    }

    public function testNormalizeUsesProfileUrlFallback(): void
    {
        // Arrange — uses profileUrl instead of url
        $item = [
            'profileUrl' => 'https://www.linkedin.com/in/profileurl-user',
            'name' => 'Carol Johnson',
            'about' => 'Marketing professional with extensive B2B experience.',
        ];

        // Act
        $result = $this->normalizer->normalize($item, $this->context);

        // Assert
        $this->assertNotNull($result);
        $this->assertSame('https://www.linkedin.com/in/profileurl-user', $result->getUrl());
    }

    public function testNormalizeSetsCorrectProviderId(): void
    {
        // Arrange
        $url = 'https://www.linkedin.com/in/provider-id-test';
        $item = [
            'url' => $url,
            'name' => 'Test User',
            'about' => 'Professional with a long and detailed about section for testing.',
        ];

        // Act
        $result = $this->normalizer->normalize($item, $this->context);

        // Assert
        $this->assertNotNull($result);
        $this->assertSame('apify:curious_coder/linkedin-profile-scraper:' . $url, $result->getProviderId());
    }

    public function testNormalizeSetsTitleToUntitledWhenNeitherNameNorHeadline(): void
    {
        // Arrange — no name and no headline; title should fall back to HtmlMetadata::UNTITLED
        // but about must also be absent or empty for the excerpt to be checked first.
        // Here about is long enough but name/headline are absent.
        $item = [
            'url' => 'https://www.linkedin.com/in/anonymous',
            'about' => 'This user has no name or headline set on their profile.',
        ];

        // Act
        $result = $this->normalizer->normalize($item, $this->context);

        // Assert
        $this->assertNotNull($result);
        $this->assertSame(HtmlMetadata::UNTITLED, $result->getTitle());
    }

    public function testSupportsReturnsTrueForLinkedInActor(): void
    {
        // Act & Assert
        $this->assertTrue($this->normalizer->supports('curious_coder/linkedin-profile-scraper'));
    }

    public function testSupportsReturnsFalseForOtherActor(): void
    {
        // Act & Assert
        $this->assertFalse($this->normalizer->supports('apify/website-content-crawler'));
    }
}
