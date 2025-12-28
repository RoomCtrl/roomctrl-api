<?php

declare(strict_types=1);

namespace App\Feature\Issue\Entity;

use App\Feature\User\Entity\User;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: "issue_history")]
class IssueHistory
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: RoomIssue::class, inversedBy: 'history')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'Issue cannot be null.')]
    private ?RoomIssue $issue = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'User cannot be null.')]
    private ?User $user = null;

    #[ORM\Column(type: 'string', length: 50)]
    #[Assert\NotBlank(message: 'Action cannot be blank.')]
    #[Assert\Choice(choices: ['created', 'status_changed', 'priority_changed', 'note_added', 'closed'], message: 'Invalid action.')]
    private string $action;

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank(message: 'Description cannot be blank.')]
    private string $description;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getIssue(): ?RoomIssue
    {
        return $this->issue;
    }

    public function setIssue(?RoomIssue $issue): self
    {
        $this->issue = $issue;
        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function setAction(string $action): self
    {
        $this->action = $action;
        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }
}
