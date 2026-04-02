<?php

declare(strict_types=1);

namespace App\Domain\Chat\Content;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
class FunctionResponseContent extends MessageContent
{
    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Groups(['message:read', 'conversation:read'])]
    private string $functionName;

    #[ORM\Column(type: 'json')]
    #[Assert\NotBlank]
    #[Groups(['message:read', 'conversation:read'])]
    private array $result;

    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['message:read', 'conversation:read'])]
    private ?array $metadata = null;

    public function __construct(string $functionName, array $result, ?array $metadata = null)
    {
        parent::__construct();
        $this->functionName = $functionName;
        $this->result = $result;
        $this->metadata = $metadata;
    }

    public function getFunctionName(): string
    {
        return $this->functionName;
    }

    public function setFunctionName(string $functionName): self
    {
        $this->functionName = $functionName;

        return $this;
    }

    public function getResult(): array
    {
        return $this->result;
    }

    public function setResult(array $result): self
    {
        $this->result = $result;

        return $this;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function setMetadata(?array $metadata): self
    {
        $this->metadata = $metadata;

        return $this;
    }

    public function getType(): string
    {
        return 'function_response';
    }
}
