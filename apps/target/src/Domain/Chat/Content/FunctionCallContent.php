<?php

declare(strict_types=1);

namespace App\Domain\Chat\Content;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
class FunctionCallContent extends MessageContent
{
    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Groups(['message:read', 'conversation:read'])]
    private string $functionName;

    #[ORM\Column(type: 'json')]
    #[Assert\NotBlank]
    #[Groups(['message:read', 'conversation:read'])]
    private array $parameters;

    public function __construct(string $functionName, array $parameters)
    {
        parent::__construct();
        $this->functionName = $functionName;
        $this->parameters = $parameters;
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

    public function getParameters(): array
    {
        return $this->parameters;
    }

    public function setParameters(array $parameters): self
    {
        $this->parameters = $parameters;

        return $this;
    }

    public function getType(): string
    {
        return 'function_call';
    }
}
