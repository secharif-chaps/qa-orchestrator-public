<?php

declare(strict_types=1);

namespace App\Domain\Collect;

interface ApifyClientInterface
{
    /**
     * @param array<string, mixed> $options
     *
     * @return array<mixed>|string|null
     */
    public function request(string $method, string $path, array $options = [], bool $toArray = true): mixed;
}
