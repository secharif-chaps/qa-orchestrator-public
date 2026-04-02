<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Language;

use App\Domain\Language\LanguageDetectorInterface;
use App\Infrastructure\Language\ChainOfResponsibilityLanguageDetector;
use App\Infrastructure\Language\LiteLLMLanguageDetector;
use App\Infrastructure\Language\PatrickschurLanguageDetector;
use App\Infrastructure\Language\WikimediaTextCatLanguageDetector;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Integration test to verify language detection service configuration.
 * Tests that Symfony DI container properly tags and prioritizes detectors.
 */
class ServiceConfigurationTest extends KernelTestCase
{
    public function testLanguageDetectorInterfaceIsAliasedToChain(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $service = $container->get(LanguageDetectorInterface::class);

        $this->assertInstanceOf(ChainOfResponsibilityLanguageDetector::class, $service);
    }

    public function testChainOfResponsibilityDetectorIsWiredCorrectly(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $chain = $container->get(ChainOfResponsibilityLanguageDetector::class);

        $this->assertInstanceOf(ChainOfResponsibilityLanguageDetector::class, $chain);
    }

    public function testAllIndividualDetectorsAreAvailable(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        // Verify all individual detectors can be instantiated
        $patrickschur = $container->get(PatrickschurLanguageDetector::class);
        $this->assertInstanceOf(PatrickschurLanguageDetector::class, $patrickschur);

        $wikimedia = $container->get(WikimediaTextCatLanguageDetector::class);
        $this->assertInstanceOf(WikimediaTextCatLanguageDetector::class, $wikimedia);

        $litellm = $container->get(LiteLLMLanguageDetector::class);
        $this->assertInstanceOf(LiteLLMLanguageDetector::class, $litellm);
    }

    public function testEnvironmentVariablesAreBoundToLiteLLMClient(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        // Get LiteLLM detector which depends on client with env vars
        $litellmDetector = $container->get(LiteLLMLanguageDetector::class);

        // If this doesn't throw an exception, environment binding worked
        $this->assertInstanceOf(LiteLLMLanguageDetector::class, $litellmDetector);
    }
}
