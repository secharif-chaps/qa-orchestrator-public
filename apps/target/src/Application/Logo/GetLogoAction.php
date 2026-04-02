<?php

declare(strict_types=1);

namespace App\Application\Logo;

use App\Application\SyncActionInterface;
use Webmozart\Assert\Assert;

readonly class GetLogoAction implements SyncActionInterface
{
    public string $domain;

    public function __construct(string $domain)
    {
        $this->domain = $this->validateAndCleanDomain($domain);
    }

    private function validateAndCleanDomain(string $domain): string
    {
        Assert::notEmpty($domain, 'Domain cannot be empty');
        Assert::maxLength($domain, 253, 'Domain too long (max 253 characters)');
        Assert::minLength($domain, 3, 'Domain too short (min 3 characters)');

        // Remove protocol if present
        $cleanDomain = preg_replace('/^https?:\/\//', '', $domain);
        Assert::string($cleanDomain, 'Failed to clean domain');

        $cleanDomain = preg_replace('/\/.*$/', '', $cleanDomain);
        Assert::string($cleanDomain, 'Failed to clean domain');

        // Validate domain format
        Assert::regex($cleanDomain, '/^[a-zA-Z0-9]([a-zA-Z0-9\-\.]*[a-zA-Z0-9])?$/', 'Invalid domain format');

        // Check for valid TLD
        Assert::true(
            str_contains($cleanDomain, '.') && !str_ends_with($cleanDomain, '.'),
            'Domain must have a valid TLD'
        );

        // Prevent localhost, private IPs, and suspicious domains
        Assert::false(
            \in_array($cleanDomain, ['localhost', '127.0.0.1', '0.0.0.0'])
            || str_starts_with($cleanDomain, '192.168.')
            || str_starts_with($cleanDomain, '10.')
            || str_starts_with($cleanDomain, '172.'),
            'Private/local domains not allowed'
        );

        return $cleanDomain;
    }
}
