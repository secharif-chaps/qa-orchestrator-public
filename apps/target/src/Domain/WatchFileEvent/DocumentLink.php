<?php

declare(strict_types=1);

namespace App\Domain\WatchFileEvent;

use App\Domain\Document\Document;
use Symfony\Component\Serializer\Annotation\Groups;

class DocumentLink
{
    #[Groups(['watch_file_event:read', 'watch_file_event:save'])]
    private string $id;

    #[Groups(['watch_file_event:read', 'watch_file_event:save'])]
    private ?string $textExtract = null;
    private ?Document $document = null;

    public function __construct(string $id, ?string $textExtract = null)
    {
        $this->id = $id;
        $this->textExtract = $textExtract;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getTextExtract(): ?string
    {
        return $this->textExtract;
    }

    public function setTextExtract(?string $textExtract): self
    {
        $this->textExtract = $textExtract;

        return $this;
    }

    public function getDocument(): ?Document
    {
        return $this->document;
    }

    public function setDocument(?Document $document): self
    {
        $this->document = $document;

        return $this;
    }
}
