<?php

declare(strict_types=1);

namespace App\Domain\Chat\Content;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
class FileContent extends MessageContent
{
    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Groups(['message:read', 'conversation:read'])]
    private string $filename;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Groups(['message:read', 'conversation:read'])]
    private string $mimeType;

    #[ORM\Column]
    #[Assert\NotBlank]
    #[Groups(['message:read', 'conversation:read'])]
    private int $size;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Groups(['message:read', 'conversation:read'])]
    private string $path;

    public function __construct(string $filename, string $mimeType, int $size, string $path)
    {
        parent::__construct();
        $this->filename = $filename;
        $this->mimeType = $mimeType;
        $this->size = $size;
        $this->path = $path;
    }

    public function getFilename(): string
    {
        return $this->filename;
    }

    public function setFilename(string $filename): self
    {
        $this->filename = $filename;

        return $this;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function setMimeType(string $mimeType): self
    {
        $this->mimeType = $mimeType;

        return $this;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function setSize(int $size): self
    {
        $this->size = $size;

        return $this;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function setPath(string $path): self
    {
        $this->path = $path;

        return $this;
    }

    public function getType(): string
    {
        return 'file';
    }
}
