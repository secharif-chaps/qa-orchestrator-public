<?php

declare(strict_types=1);

namespace App\Infrastructure\Collect\Apify;

use App\Domain\Collect\Exception\ApifyConfigurationException;
use App\Domain\Source\Source;

readonly class ApifyInputInterpolator
{
    /**
     * Interpolate variables in template array.
     *
     * Supports:
     * - {{source.url}} - Source URL
     * - {{source.query}} - Source query/search term
     * - {{config.fieldName}} - Override from Source.parameters
     * - {{config.fieldName|default:value}} - With default fallback
     *
     * @param array<string, mixed>      $template     Template with {{var}} placeholders
     * @param Source                    $source       Source with url, query, parameters
     * @param array<string, mixed>|null $sourceConfig Source.parameters (overrides)
     *
     * @return array<string, mixed> Interpolated array ready for Apify
     */
    public function interpolate(array $template, Source $source, ?array $sourceConfig = null): array
    {
        /** @var array<string, mixed> $result */
        $result = $this->interpolateValue($template, $source, $sourceConfig ?? []);

        return $result;
    }

    /**
     * @param array<string, mixed> $sourceConfig
     */
    private function interpolateValue(mixed $value, Source $source, array $sourceConfig): mixed
    {
        if (\is_string($value)) {
            return $this->interpolateString($value, $source, $sourceConfig);
        }

        if (\is_array($value)) {
            $result = [];
            foreach ($value as $k => $v) {
                $result[$k] = $this->interpolateValue($v, $source, $sourceConfig);
            }

            return $result;
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $sourceConfig
     */
    private function interpolateString(string $value, Source $source, array $sourceConfig): string
    {
        /** @var string $result */
        $result = preg_replace_callback(
            '/\{\{([^}]+)\}\}/',
            function (array $matches) use ($source, $sourceConfig): string {
                return $this->resolveVariable(trim($matches[1]), $source, $sourceConfig);
            },
            $value,
        );

        return $result;
    }

    /**
     * Resolve a single variable, handling defaults.
     *
     * Format: "source.url", "config.maxPages|default:50"
     *
     * @param string               $variable     Variable reference with optional default
     * @param array<string, mixed> $sourceConfig
     */
    private function resolveVariable(string $variable, Source $source, array $sourceConfig): string
    {
        if (str_contains($variable, '|default:')) {
            [$varName, $defaultValue] = explode('|default:', $variable, 2);
        } else {
            $varName = $variable;
            $defaultValue = null;
        }

        $varName = trim($varName);

        if (str_starts_with($varName, 'source.')) {
            $field = substr($varName, \strlen('source.'));

            $value = match ($field) {
                'url' => $source->getUrl(),
                'query' => $source->getQuery(),
                'name' => $source->getName(),
                default => throw ApifyConfigurationException::missingVariable($varName),
            };

            return $value ?? $defaultValue ?? throw ApifyConfigurationException::missingVariable($varName);
        }

        if (str_starts_with($varName, 'config.')) {
            $field = substr($varName, \strlen('config.'));

            if (isset($sourceConfig[$field])) {
                $value = $sourceConfig[$field];

                if (\is_scalar($value)) {
                    return (string) $value;
                }

                $encoded = json_encode($value);
                if (false === $encoded) {
                    throw ApifyConfigurationException::encodingFailed($varName);
                }

                return $encoded;
            }

            return $defaultValue ?? throw ApifyConfigurationException::missingVariable($varName);
        }

        throw ApifyConfigurationException::missingVariable($varName);
    }
}
