<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\PHPUnit\Set\PHPUnitSetList;

return static function (RectorConfig $rectorConfig): void {
    // Analyse project source and tests
    $rectorConfig->paths([__DIR__ . '/tests']);

    // Base PHPUnit 12 migration rules
    $rectorConfig->sets([PHPUnitSetList::PHPUNIT_100, PHPUnitSetList::PHPUNIT_110, PHPUnitSetList::PHPUNIT_120]);
};
