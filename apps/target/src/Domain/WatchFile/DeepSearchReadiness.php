<?php

declare(strict_types=1);

namespace App\Domain\WatchFile;

use App\Domain\Shared\TranslatedText;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Embeddable]
class DeepSearchReadiness
{
    #[ORM\Column(type: 'boolean')]
    #[Groups(['watch_file:read', 'watch_file:llm'])]
    private bool $ready;

    #[ORM\Column(type: 'translated_text')]
    #[Groups(['watch_file:read', 'watch_file:llm'])]
    private TranslatedText $reason;

    /**
     * @var list<string>
     */
    #[ORM\Column(type: 'json')]
    #[Groups(['watch_file:read', 'watch_file:llm'])]
    private array $suggestedSearchQueries;

    /**
     * @param list<string> $suggestedSearchQueries
     */
    public function __construct(bool $ready, TranslatedText $reason, array $suggestedSearchQueries)
    {
        $this->ready = $ready;
        $this->reason = $reason;
        $this->suggestedSearchQueries = $suggestedSearchQueries;
    }

    /**
     * @param array{ready: bool, reason: array{en: string, fr: string}, suggestedSearchQueries: list<string>} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            ready: $data['ready'],
            reason: TranslatedText::fromArray($data['reason']),
            suggestedSearchQueries: $data['suggestedSearchQueries'],
        );
    }

    /**
     * @return array{ready: bool, reason: array{en: string, fr: string}, suggestedSearchQueries: list<string>}
     */
    public function toArray(): array
    {
        return [
            'ready' => $this->ready,
            'reason' => $this->reason->toArray(),
            'suggestedSearchQueries' => $this->suggestedSearchQueries,
        ];
    }

    public function isReady(): bool
    {
        return $this->ready;
    }

    public function getReason(): TranslatedText
    {
        return $this->reason;
    }

    /**
     * @return list<string>
     */
    public function getSuggestedSearchQueries(): array
    {
        return $this->suggestedSearchQueries;
    }
}
