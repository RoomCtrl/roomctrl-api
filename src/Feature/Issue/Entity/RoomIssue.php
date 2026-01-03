<?php

declare(strict_types=1);

namespace App\Feature\Issue\Entity;

use App\Feature\Issue\Repository\RoomIssueRepository;
use App\Feature\Organization\Entity\Organization;
use App\Feature\Room\Entity\Room;
use App\Feature\User\Entity\User;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: RoomIssueRepository::class)]
#[ORM\Table(name: "room_issues")]
class RoomIssue
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: Room::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Room $room = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $reporter = null;

    #[ORM\ManyToOne(targetEntity: Organization::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Organization $organization = null;

    #[ORM\Column(type: 'string', length: 50)]
    private string $category;

    #[ORM\Column(type: 'text')]
    private string $description;

    #[ORM\Column(type: 'string', length: 20)]
    private string $status = 'pending';

    #[ORM\Column(type: 'string', length: 20)]
    private string $priority = 'medium';

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $reportedAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $closedAt = null;

    #[ORM\OneToMany(targetEntity: IssueNote::class, mappedBy: 'issue', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $notes;

    #[ORM\OneToMany(targetEntity: IssueHistory::class, mappedBy: 'issue', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'DESC'])]
    private Collection $history;

    public function __construct()
    {
        $this->reportedAt = new DateTimeImmutable();
        $this->notes = new ArrayCollection();
        $this->history = new ArrayCollection();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getRoom(): ?Room
    {
        return $this->room;
    }

    public function setRoom(?Room $room): self
    {
        $this->room = $room;
        return $this;
    }

    public function getReporter(): ?User
    {
        return $this->reporter;
    }

    public function setReporter(?User $reporter): self
    {
        $this->reporter = $reporter;
        return $this;
    }

    public function getOrganization(): ?Organization
    {
        return $this->organization;
    }

    public function setOrganization(?Organization $organization): self
    {
        $this->organization = $organization;
        return $this;
    }

    public function getCategory(): string
    {
        return $this->category;
    }

    public function setCategory(string $category): self
    {
        $this->category = $category;
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

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getPriority(): string
    {
        return $this->priority;
    }

    public function setPriority(string $priority): self
    {
        $this->priority = $priority;
        return $this;
    }

    public function getReportedAt(): DateTimeImmutable
    {
        return $this->reportedAt;
    }

    public function setReportedAt(DateTimeImmutable $reportedAt): self
    {
        $this->reportedAt = $reportedAt;
        return $this;
    }

    public function getClosedAt(): ?DateTimeImmutable
    {
        return $this->closedAt;
    }

    public function setClosedAt(?DateTimeImmutable $closedAt): self
    {
        $this->closedAt = $closedAt;
        return $this;
    }

    /**
     * @return Collection<int, IssueNote>
     */
    public function getNotes(): Collection
    {
        return $this->notes;
    }

    public function addNote(IssueNote $note): self
    {
        if (!$this->notes->contains($note)) {
            $this->notes->add($note);
            $note->setIssue($this);
        }

        return $this;
    }

    public function removeNote(IssueNote $note): self
    {
        if ($this->notes->removeElement($note)) {
            if ($note->getIssue() === $this) {
                $note->setIssue(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, IssueHistory>
     */
    public function getHistory(): Collection
    {
        return $this->history;
    }

    public function addHistory(IssueHistory $history): self
    {
        if (!$this->history->contains($history)) {
            $this->history->add($history);
            $history->setIssue($this);
        }

        return $this;
    }

    public function removeHistory(IssueHistory $history): self
    {
        if ($this->history->removeElement($history)) {
            if ($history->getIssue() === $this) {
                $history->setIssue(null);
            }
        }

        return $this;
    }
}
