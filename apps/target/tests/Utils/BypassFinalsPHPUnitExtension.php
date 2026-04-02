<?php

namespace App\Tests\Utils;

use DG\BypassFinals;
use PHPUnit\Runner\Extension\Extension;
use PHPUnit\Runner\Extension\Facade;
use PHPUnit\Runner\Extension\ParameterCollection;
use PHPUnit\TextUI\Configuration\Configuration;

class BypassFinalsPHPUnitExtension implements Extension
{
    public function bootstrap(
        Configuration $configuration,
        Facade $facade,
        ParameterCollection $parameters,
    ): void {
        BypassFinals::denyPaths(['*/vendor/phpunit/*']);

        if ($parameters->has('allowPaths')) {
            $paths = array_map('trim', explode(',', $parameters->get('allowPaths')));
            BypassFinals::allowPaths($paths);
        }

        $bypassReadOnly = !$parameters->has('bypassReadOnly') || $this->parseBoolean(
            $parameters->get('bypassReadOnly')
        );
        $bypassFinal = !$parameters->has('bypassFinal') || $this->parseBoolean($parameters->get('bypassFinal'));
        BypassFinals::enable($bypassReadOnly, $bypassFinal);
    }

    private function parseBoolean(string $value): bool
    {
        $value = strtolower($value);
        if (\in_array($value, ['1', 'true', 'yes'], true)) {
            return true;
        }

        if (\in_array($value, ['0', 'false', 'no'], true)) {
            return false;
        }

        throw new \InvalidArgumentException("Invalid boolean-like value: $value");
    }
}
