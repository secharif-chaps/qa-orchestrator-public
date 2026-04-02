<?php

declare(strict_types=1);

namespace App\Domain\Chat\Content;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
class TextContent extends MessageContent
{
    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank]
    #[Groups(['message:read', 'conversation:read', 'message:write', 'conversation:llm'])]
    private string $content;

    public function __construct(string $content)
    {
        parent::__construct();
        $this->content = $content;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): self
    {
        $this->content = $content;

        return $this;
    }

    public function getType(): string
    {
        return 'text';
    }
}
