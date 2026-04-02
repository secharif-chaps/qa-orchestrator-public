<?php

declare(strict_types=1);

namespace App\Domain\WatchFileEvent;

use Symfony\Component\Serializer\Annotation\Groups;

class EventActor
{
    #[Groups(['watch_file_event:read', 'watch_file_event:save'])]
    private string $id;

    #[Groups(['watch_file_event:read', 'watch_file_event:save'])]
    private string $name;

    #[Groups(['watch_file_event:read', 'watch_file_event:save'])]
    private string $role;

    public function __construct(string $id, string $name, string $role)
    {
        $this->id = $id;
        $this->name = $name;
        $this->role = $role;
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

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function setRole(string $role): self
    {
        $this->role = $role;

        return $this;
    }
}
