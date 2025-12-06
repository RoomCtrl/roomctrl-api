<?php

declare(strict_types=1);

namespace App\Feature\Booking\Controller;

use App\Feature\Booking\Entity\Booking;
use App\Feature\Room\Entity\Room;
use App\Feature\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Uid\Uuid;
use OpenApi\Attributes as OA;

#[Route('/bookings')]
#[OA\Tag(name: 'Bookings')]
class BookingController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private ValidatorInterface $validator;

    public function __construct(
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator
    ) {
        $this->entityManager = $entityManager;
        $this->validator = $validator;
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
            } catch (\Exception $e) {
                return new JsonResponse(['error' => 'Invalid room UUID format'], 400);
            }
        }

        if ($status) {
            $criteria['status'] = $status;
        }

        $bookings = $this->entityManager->getRepository(Booking::class)->findBy(
            $criteria,
            ['startedAt' => 'ASC']
        );

        $data = array_map(fn(Booking $b) => $this->serializeBooking($b), $bookings);

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
                required: ['title', 'roomId', 'startedAt', 'endedAt', 'participants'],
                properties: [
                    new OA\Property(property: 'title', type: 'string'),
                    new OA\Property(property: 'roomId', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'startedAt', type: 'string', format: 'date-time'),
                    new OA\Property(property: 'endedAt', type: 'string', format: 'date-time'),
                    new OA\Property(property: 'participants', type: 'integer'),
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
                        new OA\Property(property: 'participants', type: 'integer'),
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
        } catch (\Exception $e) {
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
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Invalid date format'], 400);
        }

        if ($startedAt >= $endedAt) {
            return new JsonResponse(['error' => 'End time must be after start time'], 400);
        }

        $conflictingBooking = $this->entityManager->getRepository(Booking::class)
            ->createQueryBuilder('b')
            ->where('b.room = :room')
            ->andWhere('b.status = :status')
            ->andWhere('(b.startedAt < :endedAt AND b.endedAt > :startedAt)')
            ->setParameter('room', $room)
            ->setParameter('status', 'active')
            ->setParameter('startedAt', $startedAt)
            ->setParameter('endedAt', $endedAt)
            ->getQuery()
            ->getOneOrNullResult();

        if ($conflictingBooking) {
            return new JsonResponse([
                'error' => 'Time slot already booked',
                'conflictingBooking' => $this->serializeBooking($conflictingBooking)
            ], 409);
        }

        $booking = new Booking();
        $booking->setTitle($data['title']);
        $booking->setRoom($room);
        $booking->setUser($user);
        $booking->setStartedAt($startedAt);
        $booking->setEndedAt($endedAt);
        $booking->setParticipants($data['participants']);
        $booking->setIsPrivate($data['isPrivate'] ?? false);

        $errors = $this->validator->validate($booking);
        if (count($errors) > 0) {
            return new JsonResponse(['errors' => (string) $errors], 400);
        }

        $this->entityManager->persist($booking);
        $this->entityManager->flush();

        return new JsonResponse($this->serializeBooking($booking), 201);
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
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Invalid UUID format'], 400);
        }

        $booking = $this->entityManager->getRepository(Booking::class)->find($uuid);
        if (!$booking) {
            return new JsonResponse(['error' => 'Booking not found'], 404);
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'User not authenticated'], 401);
        }

        if ($booking->getUser()->getId() !== $user->getId() && !in_array('ROLE_ADMIN', $user->getRoles())) {
            return new JsonResponse(['error' => 'Not authorized to cancel this booking'], 403);
        }

        if ($booking->getStatus() === 'cancelled') {
            return new JsonResponse(['error' => 'Booking already cancelled'], 400);
        }

        $booking->setStatus('cancelled');
        $this->entityManager->flush();

        return new JsonResponse([
            'message' => 'Booking cancelled successfully',
            'booking' => $this->serializeBooking($booking)
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
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Invalid UUID format'], 400);
        }

        $booking = $this->entityManager->getRepository(Booking::class)->find($uuid);
        if (!$booking) {
            return new JsonResponse(['error' => 'Booking not found'], 404);
        }

        return new JsonResponse($this->serializeBooking($booking));
    }

    private function serializeBooking(Booking $booking): array
    {
        $room = $booking->getRoom();
        $roomData = null;
        
        if ($room) {
            $roomData = [
                'id' => $room->getId()->toRfc4122(),
                'roomName' => $room->getRoomName(),
                'location' => $room->getLocation()
            ];
        }

        return [
            'id' => $booking->getId()->toRfc4122(),
            'title' => $booking->getTitle(),
            'startedAt' => $booking->getStartedAt()->format('c'),
            'endedAt' => $booking->getEndedAt()->format('c'),
            'participants' => $booking->getParticipants(),
            'isPrivate' => $booking->isPrivate(),
            'status' => $booking->getStatus(),
            'room' => $roomData,
            'user' => [
                'id' => $booking->getUser()->getId()->toRfc4122(),
                'username' => $booking->getUser()->getUsername(),
                'firstName' => $booking->getUser()->getFirstName(),
                'lastName' => $booking->getUser()->getLastName()
            ],
            'createdAt' => $booking->getCreatedAt()->format('c')
        ];
    }
}
