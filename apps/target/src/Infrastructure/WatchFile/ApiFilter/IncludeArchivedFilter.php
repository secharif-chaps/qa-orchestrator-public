<?php

declare(strict_types=1);

namespace App\Infrastructure\WatchFile\ApiFilter;

use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Domain\WatchFile\WatchFile;
use App\Domain\WatchFile\WatchFileStatus;
use App\Infrastructure\Shared\NormalizeBoolTrait;
use Doctrine\ORM\QueryBuilder;

/**
 * Filter that controls whether archived WatchFiles are included in results.
 *
 * Usage: ?includeArchived=true|false
 *
 * By default (includeArchived not provided or false), archived WatchFiles are excluded.
 * When includeArchived=true, archived WatchFiles are included in the results.
 */
class IncludeArchivedFilter extends AbstractFilter
{
    use NormalizeBoolTrait;

    /**
     * @param array<string, mixed> $context
     */
    public function apply(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = [],
    ): void {
        if (WatchFile::class !== $resourceClass) {
            return;
        }

        // Check if includeArchived parameter is explicitly provided
        $includeArchived = $this->getIncludeArchivedValue($context);

        $alias = $queryBuilder->getRootAliases()[0];

        if (!$includeArchived) {
            // Exclude archived WatchFiles (default behavior)
            $queryBuilder
                ->andWhere(\sprintf('%s.status != :archived_status', $alias))
                ->setParameter('archived_status', WatchFileStatus::ARCHIVED->value);
        }
        // If includeArchived is true, we don't add any filtering (show all statuses)
    }

    /**
     * @param array<string, mixed> $context
     */
    protected function filterProperty(
        string $property,
        mixed $value,
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = [],
    ): void {
        // This filter works globally in the apply() method
        // Individual property filtering is not used
    }

    public function getDescription(string $resourceClass): array
    {
        if (WatchFile::class !== $resourceClass) {
            return [];
        }

        return [
            'includeArchived' => [
                'property' => 'includeArchived',
                'type' => 'boolean',
                'required' => false,
                'description' => 'Include archived WatchFiles in the results. By default, archived WatchFiles are excluded.',
                'schema' => [
                    'type' => 'boolean',
                    'default' => false,
                ],
            ],
        ];
    }

    /**
     * Extracts the includeArchived value from the context.
     * Returns false by default if not provided or invalid.
     *
     * @param array<string, mixed> $context
     */
    private function getIncludeArchivedValue(array $context): bool
    {
        if (!isset($context['filters']) || !\is_array($context['filters'])) {
            return false; // Default behavior: exclude archived
        }

        if (!isset($context['filters']['includeArchived'])) {
            return false; // Default behavior: exclude archived
        }

        $value = $context['filters']['includeArchived'];
        $normalizedValue = $this->normalizeBooleanValue($value);

        return $normalizedValue ?? false; // Default to false if invalid value
    }
}
