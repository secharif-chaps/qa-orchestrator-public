<?php

declare(strict_types=1);

namespace App\Domain\Collect;

readonly class ApifyInputTemplate
{
    /**
     * @param string                     $apifyActorId        Apify actor ID (e.g., 'apify/website-content-crawler')
     * @param array<string, mixed>       $defaults            Default input fields for the actor
     * @param array<string>              $requiredFields      Field names that must exist in the final input
     * @param array<string, string>|null $variableDefinitions Description of {{variable}} placeholders
     */
    public function __construct(
        public string $apifyActorId,
        public array $defaults,
        private array $requiredFields,
        public ?array $variableDefinitions = null,
    ) {
    }

    /**
     * @return string[]
     */
    public function getRequiredFields(): array
    {
        return $this->requiredFields;
    }
}
