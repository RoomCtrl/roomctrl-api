<?php

declare(strict_types=1);

namespace App\Feature\Booking\Controller;

use App\Feature\Booking\Entity\Booking;
use App\Feature\Booking\Repository\BookingRepository;
use App\Feature\Booking\Service\BookingService;
use App\Feature\Booking\Service\BookingSerializer;
use App\Feature\Room\Entity\Room;
use App\Feature\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Uid\Uuid;
use Exception;
use OpenApi\Attributes as OA;

#[Route('/bookings')]
#[OA\Tag(name: 'Bookings')]
class BookingController extends AbstractController
{
    public function __construct(
        private readonly BookingRepository $bookingRepository,
        private readonly BookingService $bookingService,
        private readonly BookingSerializer $bookingSerializer,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    #[Route('', name: 'bookings_list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/bookings',
        summary: 'Get all bookings',
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(
                name: 'roomId',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', format: 'uuid'),
                description: 'Filter by room ID'
            ),
            new OA\Parameter(
                name: 'status',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['active', 'cancelled', 'completed']),
                description: 'Filter by booking status'
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Returns list of bookings',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(
                        type: 'object',
                        properties: [
                            new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                            new OA\Property(property: 'title', type: 'string'),
                            new OA\Property(property: 'startedAt', type: 'string', format: 'date-time'),
                            new OA\Property(property: 'endedAt', type: 'string', format: 'date-time'),
                            new OA\Property(property: 'participants', type: 'integer'),
                            new OA\Property(property: 'isPrivate', type: 'boolean'),
                            new OA\Property(property: 'status', type: 'string', enum: ['active', 'cancelled', 'completed']),
                            new OA\Property(
                                property: 'room',
                                type: 'object',
                                properties: [
                                    new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                                    new OA\Property(property: 'roomName', type: 'string'),
                                    new OA\Property(property: 'location', type: 'string')
                                ]
                            ),
                            new OA\Property(
                                property: 'user',
                                type: 'object',
                                properties: [
                                    new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                                    new OA\Property(property: 'username', type: 'string'),
                                    new OA\Property(property: 'firstName', type: 'string'),
                                    new OA\Property(property: 'lastName', type: 'string')
                                ]
                            ),
                            new OA\Property(property: 'createdAt', type: 'string', format: 'date-time')
                        ]
                    ),
                )
            )
        ]
    )]
    public function list(Request $request): JsonResponse
    {
        $roomId = $request->query->get('roomId');
        $status = $request->query->get('status');

        $criteria = [];
        
        if ($roomId) {
            try {
                $roomUuid = Uuid::fromString($roomId);
                $room = $this->entityManager->getRepository(Room::class)->find($roomUuid);
                if ($room) {
                    $criteria['room'] = $room;
                }
            } catch (Exception $e) {
                return new JsonResponse(['error' => 'Invalid room UUID format'], 400);
            }
        }

        if ($status) {
            $criteria['status'] = $status;
        }

        $bookings = $this->bookingRepository->findByCriteria($criteria, ['startedAt' => 'ASC']);
        $data = $this->bookingSerializer->serializeMany($bookings);

        return new JsonResponse($data);
    }

    #[Route('', name: 'bookings_create', methods: ['POST'])]
    #[OA\Post(
        path: '/api/bookings',
        summary: 'Create a new booking',
        security: [['Bearer' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['title', 'roomId', 'startedAt', 'endedAt', 'participantsCount'],
                properties: [
                    new OA\Property(property: 'title', type: 'string'),
                    new OA\Property(property: 'roomId', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'startedAt', type: 'string', format: 'date-time'),
                    new OA\Property(property: 'endedAt', type: 'string', format: 'date-time'),
                    new OA\Property(property: 'participantsCount', type: 'integer'),
                    new OA\Property(property: 'participantIds', type: 'array', items: new OA\Items(type: 'string', format: 'uuid'), nullable: true),
                    new OA\Property(property: 'isPrivate', type: 'boolean')
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Booking created successfully',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                        new OA\Property(property: 'title', type: 'string'),
                        new OA\Property(property: 'startedAt', type: 'string', format: 'date-time'),
                        new OA\Property(property: 'endedAt', type: 'string', format: 'date-time'),
                        new OA\Property(property: 'participantsCount', type: 'integer'),
                        new OA\Property(property: 'participants', type: 'array', items: new OA\Items(type: 'object')),
                        new OA\Property(property: 'isPrivate', type: 'boolean'),
                        new OA\Property(property: 'status', type: 'string'),
                        new OA\Property(
                            property: 'room',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                                new OA\Property(property: 'roomName', type: 'string'),
                                new OA\Property(property: 'location', type: 'string')
                            ]
                        ),
                        new OA\Property(
                            property: 'user',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                                new OA\Property(property: 'username', type: 'string'),
                                new OA\Property(property: 'firstName', type: 'string'),
                                new OA\Property(property: 'lastName', type: 'string')
                            ]
                        ),
                        new OA\Property(property: 'createdAt', type: 'string', format: 'date-time')
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: 'Invalid input',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'error', type: 'string')
                    ]
                )
            ),
            new OA\Response(
                response: 409,
                description: 'Time slot already booked',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'error', type: 'string'),
                        new OA\Property(
                            property: 'conflictingBooking',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                                new OA\Property(property: 'title', type: 'string'),
                                new OA\Property(property: 'startedAt', type: 'string', format: 'date-time'),
                                new OA\Property(property: 'endedAt', type: 'string', format: 'date-time'),
                                new OA\Property(
                                    property: 'user',
                                    type: 'object',
                                    properties: [
                                        new OA\Property(property: 'username', type: 'string'),
                                        new OA\Property(property: 'firstName', type: 'string'),
                                        new OA\Property(property: 'lastName', type: 'string')
                                    ]
                                )
                            ]
                        )
                    ]
                )
            )
        ]
    )]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['roomId'])) {
            return new JsonResponse(['error' => 'Room ID is required'], 400);
        }

        try {
            $roomUuid = Uuid::fromString($data['roomId']);
        } catch (Exception $e) {
            return new JsonResponse(['error' => 'Invalid room UUID format'], 400);
        }

        $room = $this->entityManager->getRepository(Room::class)->find($roomUuid);
        if (!$room) {
            return new JsonResponse(['error' => 'Room not found'], 404);
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'User not authenticated'], 401);
        }

        try {
            $startedAt = new \DateTimeImmutable($data['startedAt']);
            $endedAt = new \DateTimeImmutable($data['endedAt']);
        } catch (Exception $e) {
            return new JsonResponse(['error' => 'Invalid date format'], 400);
        }

        if ($startedAt >= $endedAt) {
            return new JsonResponse(['error' => 'End time must be after start time'], 400);
        }

        $conflictingBooking = $this->bookingService->findConflictingBooking($room, $startedAt, $endedAt);

        if ($conflictingBooking) {
            return new JsonResponse([
                'error' => 'Time slot already booked',
                'conflictingBooking' => $this->bookingSerializer->serialize($conflictingBooking)
            ], 409);
        }

        $booking = $this->bookingService->createBooking(
            $data['title'],
            $room,
            $user,
            $startedAt,
            $endedAt,
            $data['participantsCount'],
            $data['isPrivate'] ?? false,
            $data['participantIds'] ?? []
        );

        $errors = $this->bookingService->validateBooking($booking);
        if (!empty($errors)) {
            return new JsonResponse(['errors' => $errors], 400);
        }

        return new JsonResponse($this->bookingSerializer->serialize($booking), 201);
    }

    #[Route('/{id}/cancel', name: 'bookings_cancel', methods: ['POST'])]
    #[OA\Post(
        path: '/api/bookings/{id}/cancel',
        summary: 'Cancel a booking',
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Booking cancelled successfully',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'message', type: 'string'),
                        new OA\Property(
                            property: 'booking',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                                new OA\Property(property: 'title', type: 'string'),
                                new OA\Property(property: 'status', type: 'string'),
                                new OA\Property(property: 'startedAt', type: 'string', format: 'date-time'),
                                new OA\Property(property: 'endedAt', type: 'string', format: 'date-time')
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: 'Booking already cancelled',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'error', type: 'string')
                    ]
                )
            ),
            new OA\Response(
                response: 403,
                description: 'Not authorized to cancel this booking',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'error', type: 'string')
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Booking not found',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'error', type: 'string')
                    ]
                )
            )
        ]
    )]
    public function cancel(string $id): JsonResponse
    {
        try {
            $uuid = Uuid::fromString($id);
        } catch (Exception $e) {
            return new JsonResponse(['error' => 'Invalid UUID format'], 400);
        }

        $booking = $this->bookingRepository->findById($uuid);
        if (!$booking) {
            return new JsonResponse(['error' => 'Booking not found'], 404);
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'User not authenticated'], 401);
        }

        if (!$this->bookingService->canUserCancelBooking($booking, $user)) {
            return new JsonResponse(['error' => 'Not authorized to cancel this booking'], 403);
        }

        if ($booking->getStatus() === 'cancelled') {
            return new JsonResponse(['error' => 'Booking already cancelled'], 400);
        }

        $this->bookingService->cancelBooking($booking);

        return new JsonResponse([
            'message' => 'Booking cancelled successfully',
            'booking' => $this->bookingSerializer->serialize($booking)
        ]);
    }

    #[Route('/{id}', name: 'bookings_get', methods: ['GET'])]
    #[OA\Get(
        path: '/api/bookings/{id}',
        summary: 'Get a single booking by ID',
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Returns booking details',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                        new OA\Property(property: 'title', type: 'string'),
                        new OA\Property(property: 'startedAt', type: 'string', format: 'date-time'),
                        new OA\Property(property: 'endedAt', type: 'string', format: 'date-time'),
                        new OA\Property(property: 'participants', type: 'integer'),
                        new OA\Property(property: 'isPrivate', type: 'boolean'),
                        new OA\Property(property: 'status', type: 'string', enum: ['active', 'cancelled', 'completed']),
                        new OA\Property(
                            property: 'room',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                                new OA\Property(property: 'roomName', type: 'string'),
                                new OA\Property(property: 'location', type: 'string')
                            ]
                        ),
                        new OA\Property(
                            property: 'user',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                                new OA\Property(property: 'username', type: 'string'),
                                new OA\Property(property: 'firstName', type: 'string'),
                                new OA\Property(property: 'lastName', type: 'string')
                            ]
                        ),
                        new OA\Property(property: 'createdAt', type: 'string', format: 'date-time')
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Booking not found',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'error', type: 'string')
                    ]
                )
            )
        ]
    )]
    public function get(string $id): JsonResponse
    {
        try {
            $uuid = Uuid::fromString($id);
        } catch (Exception $e) {
            return new JsonResponse(['error' => 'Invalid UUID format'], 400);
        }

        $booking = $this->bookingRepository->findById($uuid);
        if (!$booking) {
            return new JsonResponse(['error' => 'Booking not found'], 404);
        }

        return new JsonResponse($this->bookingSerializer->serialize($booking));
    }
}
