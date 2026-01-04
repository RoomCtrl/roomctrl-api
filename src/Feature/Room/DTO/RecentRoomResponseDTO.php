<?php

declare(strict_types=1);

namespace App\Feature\Room\DTO;

use App\Feature\Booking\Entity\Booking;
use App\Feature\Room\Entity\Room;

class RecentRoomResponseDTO extends RoomResponseDTO
{
    public ?array $lastBooking = null;

    public static function fromEntityWithBooking(Room $room, ?Booking $lastBooking = null): self
    {
        $dto = new self(
            $room->getId()->toRfc4122(),
            $room->getRoomName(),
            $room->getRoomStatus()?->getStatus() ?? 'unknown',
            $room->getCapacity(),
            $room->getSize(),
            $room->getLocation(),
            $room->getAccess(),
            $room->getDescription(),
            $room->getLighting(),
            $room->getAirConditioning(),
            $room->getImagePaths() ?? [],
            []
        );

        foreach ($room->getEquipment() as $item) {
            $dto->equipment[] = [
                'name' => $item->getName(),
                'category' => $item->getCategory(),
                'quantity' => $item->getQuantity()
            ];
        }

        if ($lastBooking) {
            $dto->lastBooking = [
                'id' => $lastBooking->getId()->toRfc4122(),
                'title' => $lastBooking->getTitle(),
                'startedAt' => $lastBooking->getStartedAt()->format('c'),
                'endedAt' => $lastBooking->getEndedAt()->format('c')
            ];
        }

        return $dto;
    }

    public function toArray(): array
    {
        $data = parent::toArray();

        if ($this->lastBooking !== null) {
            $data['lastBooking'] = $this->lastBooking;
        }

        return $data;
    }
}
