<?php

declare(strict_types=1);

namespace App\Feature\Booking\Entity;

use App\Feature\Booking\Repository\BookingRepository;
use App\Feature\Room\Entity\Room;
use App\Feature\User\Entity\User;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: BookingRepository::class)]
#[ORM\Table(name: "bookings")]
class Booking
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    #[Assert\NotBlank(message: 'Title cannot be blank.')]
    private string $title;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Assert\NotNull]
    private DateTimeImmutable $startedAt;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Assert\NotNull]
    private DateTimeImmutable $endedAt;

    #[ORM\Column(type: 'integer')]
    #[Assert\NotNull]
    #[Assert\Positive]
    private int $participantsCount;

    #[ORM\Column(type: 'boolean')]
    private bool $isPrivate = false;

    #[ORM\Column(type: 'string', length: 20)]
    #[Assert\Choice(choices: ['active', 'cancelled', 'completed'], message: 'Invalid status.')]
    private string $status = 'active';

    #[ORM\ManyToOne(targetEntity: Room::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Room $room = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $user = null;

    #[ORM\ManyToMany(targetEntity: User::class)]
    #[ORM\JoinTable(name: 'booking_participants')]
    private Collection $participants;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
        $this->participants = new ArrayCollection();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function getStartedAt(): DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function setStartedAt(DateTimeImmutable $startedAt): self
    {
        $this->startedAt = $startedAt;
        return $this;
    }

    public function getEndedAt(): DateTimeImmutable
    {
        return $this->endedAt;
    }

    public function setEndedAt(DateTimeImmutable $endedAt): self
    {
        $this->endedAt = $endedAt;
        return $this;
    }

    public function getParticipantsCount(): int
    {
        return $this->participantsCount;
    }

    public function setParticipantsCount(int $participantsCount): self
    {
        $this->participantsCount = $participantsCount;
        return $this;
    }

    public function isPrivate(): bool
    {
        return $this->isPrivate;
    }

    public function setIsPrivate(bool $isPrivate): self
    {
        $this->isPrivate = $isPrivate;
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

    public function getRoom(): ?Room
    {
        return $this->room;
    }

    public function setRoom(?Room $room): self
    {
        $this->room = $room;
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

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getParticipants(): Collection
    {
        return $this->participants;
    }

    public function addParticipant(User $participant): self
    {
        if (!$this->participants->contains($participant)) {
            $this->participants->add($participant);
        }

        return $this;
    }

    public function removeParticipant(User $participant): self
    {
        $this->participants->removeElement($participant);

        return $this;
    }
}
