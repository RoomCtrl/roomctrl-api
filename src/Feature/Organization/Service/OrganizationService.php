<?php

declare(strict_types=1);

namespace App\Feature\Organization\Service;

use App\Feature\Organization\Entity\Organization;
use App\Feature\Organization\Repository\OrganizationRepository;
use App\Feature\Room\Service\RoomService;
use App\Feature\Booking\Service\BookingService;
use App\Feature\Room\Entity\Room;
use App\Feature\Booking\Entity\Booking;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;
use Exception;

class OrganizationService
{
    public function __construct(
        private readonly OrganizationRepository $organizationRepository,
        private readonly RoomService $roomService,
        private readonly BookingService $bookingService,
        private readonly EntityManagerInterface $entityManager
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

    public function createOrganization(array $data): array
    {
        try {
            $organization = new Organization();
            $organization->setRegon($data['regon']);
            $organization->setName($data['name']);
            $organization->setEmail($data['email']);

            $this->organizationRepository->save($organization, true);

            return [
                'code' => 201,
                'message' => 'Organization created successfully',
                'id' => $organization->getId()->toRfc4122()
            ];
        } catch (Exception $e) {
            throw new Exception('This REGON or email is already registered.');
        }
    }

    public function updateOrganization(Organization $organization, array $data): array
    {
        try {
            if (isset($data['regon'])) {
                $organization->setRegon($data['regon']);
            }

            if (isset($data['name'])) {
                $organization->setName($data['name']);
            }

            if (isset($data['email'])) {
                $organization->setEmail($data['email']);
            }

            $this->organizationRepository->flush();

            return [
                'code' => 200,
                'message' => 'Organization updated successfully'
            ];
        } catch (Exception $e) {
            throw new Exception('This REGON or email is already registered.');
        }
    }

    public function deleteOrganization(Organization $organization): array
    {
        try {
            // Sprawdź czy organizacja ma przypisanych userów
            $usersCount = $organization->getUsers()->count();
            if ($usersCount > 0) {
                return [
                    'success' => false,
                    'code' => 409,
                    'message' => 'Cannot delete organization with assigned users. Please remove or reassign users first.'
                ];
            }

            // Pobierz wszystkie pokoje organizacji
            $rooms = $this->entityManager->getRepository(Room::class)
                ->findBy(['organization' => $organization]);
            
            // Dla każdego pokoju anuluj wszystkie rezerwacje
            foreach ($rooms as $room) {
                $bookings = $this->entityManager->getRepository(Booking::class)
                    ->findBy(['room' => $room]);
                
                foreach ($bookings as $booking) {
                    // Anuluj rezerwację tylko jeśli nie jest już anulowana
                    if ($booking->getStatus() !== 'cancelled') {
                        $booking->setStatus('cancelled');
                        $this->entityManager->persist($booking);
                    }
                }
            }

            // Flush aby zapisać zmienione rezerwacje
            $this->entityManager->flush();

            // Usuń organizację
            $this->organizationRepository->remove($organization, true);

            return [
                'success' => true,
                'code' => 204
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'code' => 500,
                'message' => 'Failed to delete organization: ' . $e->getMessage()
            ];
        }
    }

    public function serializeOrganization(Organization $organization, bool $withUsers = false): array
    {
        $data = [
            'id' => $organization->getId()->toRfc4122(),
            'regon' => $organization->getRegon(),
            'name' => $organization->getName(),
            'email' => $organization->getEmail()
        ];

        if ($withUsers) {
            $data['usersCount'] = $organization->getUsers()->count();
        }

        return $data;
    }
}
