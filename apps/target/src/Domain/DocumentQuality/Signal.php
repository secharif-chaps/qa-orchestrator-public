<?php

declare(strict_types=1);

namespace App\Domain\DocumentQuality;

use App\Domain\Shared\TranslatedText;

readonly class Signal
{
    public function __construct(
        public float $value,
        public float $weight,
        public SignalCategory $category,
        public ?TranslatedText $reason = null,
    ) {
    }

    public function contribution(): float
    {
        return $this->value * $this->weight;
    }
}
