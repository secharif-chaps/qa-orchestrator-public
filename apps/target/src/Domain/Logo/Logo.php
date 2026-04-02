<?php

declare(strict_types=1);

namespace App\Domain\Logo;

readonly class Logo
{
    public function __construct(
        public string $content,
        public string $mimeType,
        public string $domain,
    ) {
        if (empty($content)) {
            throw new \InvalidArgumentException('Logo content cannot be empty');
        }

        if (empty($mimeType)) {
            throw new \InvalidArgumentException('Logo mime type cannot be empty');
        }

        if (empty($domain)) {
            throw new \InvalidArgumentException('Logo domain cannot be empty');
        }
    }

    public function getSize(): int
    {
        return \strlen($this->content);
    }

    public function isSvg(): bool
    {
        return 'image/svg+xml' === $this->mimeType;
    }

    public function isPng(): bool
    {
        return 'image/png' === $this->mimeType;
    }
}
