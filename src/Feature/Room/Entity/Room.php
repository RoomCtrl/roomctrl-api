<?php

declare(strict_types=1);

namespace App\Feature\Room\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Uid\Uuid;
use App\Feature\Organization\Entity\Organization;

#[ORM\Entity]
#[ORM\Table(name: "rooms")]
class Room
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\Column(type: 'string', length: 100)]
    #[Assert\NotBlank(message: 'Room name cannot be blank.')]
    private string $roomName;

    #[ORM\Column(type: 'integer')]
    #[Assert\NotNull]
    #[Assert\Positive]
    #[Assert\Range(min: 1, max: 200, notInRangeMessage: 'Capacity must be between {{ min }} and {{ max }}.')]
    private int $capacity;

    #[ORM\Column(type: 'float')]
    #[Assert\NotNull]
    #[Assert\Positive]
    private float $size;

    #[ORM\Column(type: 'string', length: 255)]
    #[Assert\NotBlank]
    private string $location;

    #[ORM\Column(type: 'string', length: 50)]
    #[Assert\NotBlank]
    private string $access;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $lighting = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $airConditioning = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $imagePath = null;

    #[ORM\ManyToOne(targetEntity: Organization::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    private ?Organization $organization = null;

    #[ORM\OneToMany(mappedBy: 'room', targetEntity: Equipment::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $equipment;

    #[ORM\OneToOne(mappedBy: 'room', targetEntity: RoomStatus::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private ?RoomStatus $roomStatus = null;

    public function __construct()
    {
        $this->equipment = new ArrayCollection();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getRoomName(): string
    {
        return $this->roomName;
    }

    public function setRoomName(string $roomName): self
    {
        $this->roomName = $roomName;
        return $this;
    }

    public function getCapacity(): int
    {
        return $this->capacity;
    }

    public function setCapacity(int $capacity): self
    {
        $this->capacity = $capacity;
        return $this;
    }

    public function getSize(): float
    {
        return $this->size;
    }

    public function setSize(float $size): self
    {
        $this->size = $size;
        return $this;
    }

    public function getLocation(): string
    {
        return $this->location;
    }

    public function setLocation(string $location): self
    {
        $this->location = $location;
        return $this;
    }

    public function getAccess(): string
    {
        return $this->access;
    }

    public function setAccess(string $access): self
    {
        $this->access = $access;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getLighting(): ?string
    {
        return $this->lighting;
    }

    public function setLighting(?string $lighting): self
    {
        $this->lighting = $lighting;
        return $this;
    }

    public function getAirConditioning(): ?array
    {
        return $this->airConditioning;
    }

    public function setAirConditioning(?array $airConditioning): self
    {
        $this->airConditioning = $airConditioning;
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

    public function getEquipment(): Collection
    {
        return $this->equipment;
    }

    public function addEquipment(Equipment $equipment): self
    {
        if (!$this->equipment->contains($equipment)) {
            $this->equipment[] = $equipment;
            $equipment->setRoom($this);
        }

        return $this;
    }

    public function removeEquipment(Equipment $equipment): self
    {
        if ($this->equipment->removeElement($equipment)) {
            if ($equipment->getRoom() === $this) {
                $equipment->setRoom(null);
            }
        }

        return $this;
    }

    public function getRoomStatus(): ?RoomStatus
    {
        return $this->roomStatus;
    }

    public function setRoomStatus(?RoomStatus $roomStatus): self
    {
        $this->roomStatus = $roomStatus;
        
        if ($roomStatus !== null && $roomStatus->getRoom() !== $this) {
            $roomStatus->setRoom($this);
        }
        
        return $this;
    }

    public function getImagePath(): ?string
    {
        return $this->imagePath;
    }

    public function setImagePath(?string $imagePath): self
    {
        $this->imagePath = $imagePath;
        return $this;
    }
}
