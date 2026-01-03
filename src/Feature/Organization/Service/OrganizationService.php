<?php

declare(strict_types=1);

namespace App\Feature\Organization\Service;

use App\Feature\Organization\DTO\CreateOrganizationDTO;
use App\Feature\Organization\DTO\OrganizationDeleteResultDTO;
use App\Feature\Organization\DTO\OrganizationResponseDTO;
use App\Feature\Organization\DTO\UpdateOrganizationDTO;
use App\Feature\Organization\Entity\Organization;
use App\Feature\Organization\Repository\OrganizationRepository;
use App\Feature\Booking\Repository\BookingRepository;
use App\Feature\Room\Repository\RoomRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;
use Exception;

readonly class OrganizationService implements OrganizationServiceInterface
{
    public function __construct(
        private OrganizationRepository $organizationRepository,
        private RoomRepository $roomRepository,
        private BookingRepository $bookingRepository
    ) {
    }

    public function getAllOrganizations(): array
    {
        return $this->organizationRepository->findAll();
    }

    public function getOrganizationById(Uuid $id): ?Organization
    {
        return $this->organizationRepository->findById($id);
    }

    public function createOrganization(CreateOrganizationDTO $dto): Organization
    {
        $organization = new Organization();
        $organization->setRegon($dto->regon);
        $organization->setName($dto->name);
        $organization->setEmail($dto->email);

        $this->organizationRepository->save($organization, true);

        return $organization;
    }

    public function updateOrganization(Organization $organization, UpdateOrganizationDTO $dto): void
    {
        if ($dto->regon !== null) {
            $organization->setRegon($dto->regon);
        }

        if ($dto->name !== null) {
            $organization->setName($dto->name);
        }

        if ($dto->email !== null) {
            $organization->setEmail($dto->email);
        }

        $this->organizationRepository->flush();
    }

    public function deleteOrganization(Organization $organization): OrganizationDeleteResultDTO
    {
        try {
            $usersCount = $organization->getUsers()->count();
            if ($usersCount > 0) {
                return OrganizationDeleteResultDTO::conflict(
                    'Cannot delete organization with assigned users. Please remove or reassign users first.'
                );
            }

            $rooms = $this->roomRepository->findBy(['organization' => $organization]);

            foreach ($rooms as $room) {
                $bookings = $this->bookingRepository->findBy(['room' => $room]);

                foreach ($bookings as $booking) {
                    if ($booking->getStatus() !== 'cancelled') {
                        $booking->setStatus('cancelled');
                        $this->bookingRepository->save($booking, false);
                    }
                }
            }

            $this->bookingRepository->flush();

            $this->organizationRepository->remove($organization, true);

            return OrganizationDeleteResultDTO::success();
        } catch (Exception $e) {
            return OrganizationDeleteResultDTO::error('Failed to delete organization: ' . $e->getMessage());
        }
    }

    public function getOrganizationResponse(Organization $organization, bool $withUsers = false): OrganizationResponseDTO
    {
        return OrganizationResponseDTO::fromEntity($organization, $withUsers);
    }
}
