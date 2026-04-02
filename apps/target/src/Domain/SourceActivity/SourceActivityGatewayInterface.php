<?php

declare(strict_types=1);

namespace App\Domain\SourceActivity;

use App\Domain\Source\Source;

interface SourceActivityGatewayInterface
{
    public function save(SourceActivity $sourceActivity): void;

    /**
     * Get activities for a source grouped by day, paginated.
     *
     * @return array<string, array<SourceActivity>>
     */
    public function getBySourceGroupedByDay(Source $source, int $page = 1, int $itemsPerPage = 20): array;
}
