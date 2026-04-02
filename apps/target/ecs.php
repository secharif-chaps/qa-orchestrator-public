<?php

use PhpCsFixer\Fixer\ArrayNotation\ArraySyntaxFixer;
use PhpCsFixer\Fixer\ClassNotation\ClassAttributesSeparationFixer;
use PhpCsFixer\Fixer\Import\NoUnusedImportsFixer;
use PhpCsFixer\Fixer\ListNotation\ListSyntaxFixer;
use Symplify\EasyCodingStandard\Config\ECSConfig;

return ECSConfig::configure()
    ->withCache(__DIR__ . '/var/cache/ecs')
    ->withPaths([__DIR__ . '/src', __DIR__ . '/tests'])
    ->withRules([
        ListSyntaxFixer::class,
        NoUnusedImportsFixer::class,
    ])
    ->withPreparedSets(
        psr12: true,
        symplify: true,
        cleanCode: true,
    )
    ->withPhpCsFixerSets(
        php84Migration: true,
        phpunit100MigrationRisky: true,
        psr12: true,
        psr12Risky: true,
        symfony: true,
        symfonyRisky: true,
    )
    ->withConfiguredRule(ArraySyntaxFixer::class, [
        'syntax' => 'short',
    ])
    ->withConfiguredRule(ClassAttributesSeparationFixer::class, [
        'elements' => [
            'const' => 'only_if_meta',
            'method' => 'one',
            'property' => 'only_if_meta',
            'trait_import' => 'none',
            'case' => 'none',
        ],
    ]);
