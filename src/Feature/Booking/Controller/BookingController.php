<?php

declare(strict_types=1);

namespace App\Feature\Booking\Controller;

use App\Feature\Booking\DTO\BookingResponseDTO;
use App\Feature\Booking\DTO\CreateBookingDTO;
use App\Feature\Booking\DTO\CreateRecurringBookingDTO;
use App\Feature\Booking\DTO\UpdateBookingDTO;
use App\Feature\Booking\Service\BookingServiceInterface;
use App\Feature\Room\Entity\Room;
use App\Feature\User\Entity\User;
use App\Common\Utility\ValidationErrorFormatter;
use DateMalformedStringException;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/bookings')]
#[OA\Tag(name: 'Bookings')]
class BookingController extends AbstractController
{
    public function __construct(
        private readonly BookingServiceInterface $bookingService,
        private readonly ValidatorInterface $validator,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    #[Route('', name: 'bookings_list', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[OA\Get(
        path: '/api/bookings',
        description: 'Returns a list of all bookings. Bookings include details about the room, user, and participants.',
        summary: 'Retrieve list of bookings',
        security: [['Bearer' => []]],
        tags: ['Bookings'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Successfully retrieved list of bookings',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'id', description: 'Unique booking identifier', type: 'string', format: 'uuid'),
                            new OA\Property(property: 'title', description: 'Booking title or meeting name', type: 'string'),
                            new OA\Property(property: 'startedAt', description: 'Booking start time in ISO 8601 format', type: 'string', format: 'date-time'),
                            new OA\Property(property: 'endedAt', description: 'Booking end time in ISO 8601 format', type: 'string', format: 'date-time'),
                            new OA\Property(property: 'participantsCount', description: 'Expected number of participants', type: 'integer'),
                            new OA\Property(property: 'participants', description: 'List of booking participants', type: 'array', items: new OA\Items(type: 'object')),
                            new OA\Property(property: 'isPrivate', description: 'Whether the booking is private', type: 'boolean'),
                            new OA\Property(property: 'status', description: 'Current booking status', type: 'string', enum: ['active', 'cancelled', 'completed']),
                            new OA\Property(
                                property: 'room',
                                description: 'Room information',
                                properties: [
                                    new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                                    new OA\Property(property: 'roomName', type: 'string'),
                                    new OA\Property(property: 'location', type: 'string')
                                ],
                                type: 'object'
                            ),
                            new OA\Property(
                                property: 'user',
                                description: 'User who created the booking',
                                properties: [
                                    new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                                    new OA\Property(property: 'username', type: 'string'),
                                    new OA\Property(property: 'firstName', type: 'string'),
                                    new OA\Property(property: 'lastName', type: 'string')
                                ],
                                type: 'object'
                            ),
                            new OA\Property(property: 'createdAt', description: 'Timestamp when booking was created', type: 'string', format: 'date-time')
                        ],
                        type: 'object'
                    )
                )
            ),
            new OA\Response(
                response: 400,
                description: 'Bad request - invalid room UUID format',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 400),
                        new OA\Property(property: 'message', type: 'string', example: 'Invalid room UUID format')
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthorized - missing or invalid JWT token',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 401),
                        new OA\Property(property: 'message', type: 'string', example: 'JWT Token not found')
                    ]
                )
            )
        ]
    )]
    public function list(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $data = $this->bookingService->getBookingsList($user);

        return $this->json($data, Response::HTTP_OK);
    }

    #[Route('', name: 'bookings_create', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[OA\Post(
        path: '/api/bookings',
        description: 'Creates a new room booking. The start time must be in the future and end time must be after start time. Checks for conflicts with existing bookings.',
        summary: 'Create a new booking',
        security: [['Bearer' => []]],
        requestBody: new OA\RequestBody(
            description: 'Booking details',
            required: true,
            content: new OA\JsonContent(
                required: ['title', 'roomId', 'startedAt', 'endedAt', 'participantsCount'],
                properties: [
                    new OA\Property(
                        property: 'title',
                        description: 'Booking title or meeting name',
                        type: 'string',
                        maxLength: 255,
                        minLength: 3,
                        example: 'Team Meeting'
                    ),
                    new OA\Property(
                        property: 'roomId',
                        description: 'UUID of the room to book',
                        type: 'string',
                        format: 'uuid',
                        example: '019afaf8-7edc-7935-9afc-d94a15e0e7ed'
                    ),
                    new OA\Property(
                        property: 'startedAt',
                        description: 'Booking start time in ISO 8601 format. Must be in the future.',
                        type: 'string',
                        format: 'date-time',
                        example: '2025-12-08T13:16:23'
                    ),
                    new OA\Property(
                        property: 'endedAt',
                        description: 'Booking end time in ISO 8601 format. Must be after startedAt.',
                        type: 'string',
                        format: 'date-time',
                        example: '2025-12-08T15:15:23'
                    ),
                    new OA\Property(
                        property: 'participantsCount',
                        description: 'Expected number of participants',
                        type: 'integer',
                        minimum: 1,
                        example: 10
                    ),
                    new OA\Property(
                        property: 'participantIds',
                        description: 'Optional array of user UUIDs who will participate',
                        type: 'array',
                        items: new OA\Items(type: 'string', format: 'uuid'),
                        example: ['019afaf8-8087-793e-83e8-e83bab4145c0'],
                        nullable: true
                    ),
                    new OA\Property(
                        property: 'isPrivate',
                        description: 'Whether the booking should be private',
                        type: 'boolean',
                        example: true
                    )
                ]
            )
        ),
        tags: ['Bookings'],
        responses: [
            new OA\Response(
                response: 201,
                description: 'Booking created successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 201),
                        new OA\Property(property: 'message', type: 'string', example: 'Booking created successfully'),
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(property: 'id', description: 'Unique booking identifier', type: 'string', format: 'uuid'),
                                new OA\Property(property: 'title', description: 'Booking title', type: 'string'),
                                new OA\Property(property: 'startedAt', description: 'Start time', type: 'string', format: 'date-time'),
                                new OA\Property(property: 'endedAt', description: 'End time', type: 'string', format: 'date-time'),
                                new OA\Property(property: 'participantsCount', description: 'Number of participants', type: 'integer'),
                                new OA\Property(
                                    property: 'participants',
                                    type: 'array',
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                                            new OA\Property(property: 'username', type: 'string'),
                                            new OA\Property(property: 'firstName', type: 'string'),
                                            new OA\Property(property: 'lastName', type: 'string'),
                                            new OA\Property(property: 'email', type: 'string')
                                        ],
                                        type: 'object'
                                    )
                                ),
                                new OA\Property(property: 'isPrivate', description: 'Privacy status', type: 'boolean'),
                                new OA\Property(property: 'status', description: 'Booking status', type: 'string', enum: ['active', 'cancelled', 'completed']),
                                new OA\Property(
                                    property: 'room',
                                    properties: [
                                        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                                        new OA\Property(property: 'roomName', type: 'string'),
                                        new OA\Property(property: 'location', type: 'string')
                                    ],
                                    type: 'object'
                                ),
                                new OA\Property(
                                    property: 'user',
                                    properties: [
                                        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                                        new OA\Property(property: 'username', type: 'string'),
                                        new OA\Property(property: 'firstName', type: 'string'),
                                        new OA\Property(property: 'lastName', type: 'string')
                                    ],
                                    type: 'object'
                                ),
                                new OA\Property(property: 'createdAt', description: 'Creation timestamp', type: 'string', format: 'date-time')
                            ],
                            type: 'object'
                        )
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: 'Bad request - validation failed or invalid date format',
                content: new OA\JsonContent(
                    oneOf: [
                        new OA\Schema(
                            properties: [
                                new OA\Property(property: 'code', type: 'integer', example: 400),
                                new OA\Property(property: 'message', type: 'string', example: 'Validation failed'),
                                new OA\Property(
                                    property: 'violations',
                                    type: 'array',
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(property: 'field', type: 'string', example: 'title'),
                                            new OA\Property(property: 'message', type: 'string', example: 'Title is required')
                                        ]
                                    )
                                )
                            ]
                        ),
                        new OA\Schema(
                            properties: [
                                new OA\Property(property: 'code', type: 'integer', example: 400),
                                new OA\Property(property: 'message', type: 'string', example: 'Cannot create booking in the past')
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthorized - user not authenticated',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 401),
                        new OA\Property(property: 'message', type: 'string', example: 'User not authenticated')
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Room not found',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 404),
                        new OA\Property(property: 'message', type: 'string', example: 'Room not found')
                    ]
                )
            ),
            new OA\Response(
                response: 409,
                description: 'Conflict - time slot already booked',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 409),
                        new OA\Property(property: 'message', type: 'string', example: 'Time slot already booked')
                    ]
                )
            )
        ]
    )]
    public function create(Request $request): Response
    {
        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            return $this->json([
                'code' => Response::HTTP_BAD_REQUEST,
                'message' => 'Invalid JSON'
            ], Response::HTTP_BAD_REQUEST);
        }

        $dto = CreateBookingDTO::fromArray($data);

        $violations = $this->validator->validate($dto);
        if (count($violations) > 0) {
            return $this->json(
                ValidationErrorFormatter::format($violations),
                Response::HTTP_BAD_REQUEST
            );
        }

        try {
            $user = $this->getUser();
            if (!$user instanceof User) {
                return $this->json([
                    'code' => Response::HTTP_UNAUTHORIZED,
                    'message' => 'User not authenticated'
                ], Response::HTTP_UNAUTHORIZED);
            }

            $booking = $this->bookingService->handleCreateBooking($dto, $user);

            return $this->json([
                'code' => Response::HTTP_CREATED,
                'message' => 'Booking created successfully',
                'data' => new BookingResponseDTO($booking)->toArray()
            ], Response::HTTP_CREATED);
        } catch (InvalidArgumentException $e) {
            if (str_starts_with($e->getMessage(), '{')) {
                $errorData = json_decode($e->getMessage(), true);
                return $this->json($errorData, Response::HTTP_CONFLICT);
            }

            return $this->json([
                'code' => Response::HTTP_BAD_REQUEST,
                'message' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/recurring', name: 'bookings_create_recurring', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    #[OA\Post(
        path: '/api/bookings/recurring',
        description: 'Creates multiple recurring bookings (e.g., cleaning, maintenance) for specified days of the week over a period of weeks.',
        summary: 'Create recurring bookings for cleaning or maintenance',
        security: [['Bearer' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['roomId', 'type', 'startTime', 'endTime', 'daysOfWeek'],
                properties: [
                    new OA\Property(property: 'roomId', description: 'Room ID', type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000'),
                    new OA\Property(property: 'type', description: 'Type of recurring booking', type: 'string', enum: ['cleaning', 'maintenance'], example: 'cleaning'),
                    new OA\Property(property: 'startTime', description: 'Start time in HH:MM format', type: 'string', example: '08:00'),
                    new OA\Property(property: 'endTime', description: 'End time in HH:MM format', type: 'string', example: '09:00'),
                    new OA\Property(
                        property: 'daysOfWeek',
                        description: 'Days of week (1=Monday, 7=Sunday)',
                        type: 'array',
                        items: new OA\Items(type: 'integer', maximum: 7, minimum: 1),
                        example: [1, 3, 5]
                    ),
                    new OA\Property(property: 'weeksAhead', description: 'Number of weeks ahead to create bookings (default: 12)', type: 'integer', example: 12)
                ]
            )
        ),
        tags: ['Bookings'],
        responses: [
            new OA\Response(
                response: 201,
                description: 'Recurring bookings created successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'createdCount', description: 'Number of bookings created', type: 'integer', example: 36),
                        new OA\Property(
                            property: 'bookingIds',
                            description: 'Array of created booking IDs',
                            type: 'array',
                            items: new OA\Items(type: 'string', format: 'uuid')
                        ),
                        new OA\Property(property: 'message', type: 'string', example: 'Successfully created 36 recurring bookings')
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: 'Validation error',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'errors', type: 'object')
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Room not found',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 404),
                        new OA\Property(property: 'message', type: 'string', example: 'Room not found')
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthorized',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 401),
                        new OA\Property(property: 'message', type: 'string', example: 'JWT Token not found')
                    ]
                )
            ),
            new OA\Response(
                response: 403,
                description: 'Forbidden - Requires ROLE_ADMIN',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 403),
                        new OA\Property(property: 'message', type: 'string', example: 'Access denied. You do not have sufficient permissions to access this resource.')
                    ]
                )
            )
        ]
    )]
    public function createRecurringBooking(Request $request): Response
    {
        $data = json_decode($request->getContent(), true);

        $dto = new CreateRecurringBookingDTO();
        $dto->roomId = $data['roomId'] ?? '';
        $dto->type = $data['type'] ?? '';
        $dto->startTime = $data['startTime'] ?? '';
        $dto->endTime = $data['endTime'] ?? '';
        $dto->daysOfWeek = $data['daysOfWeek'] ?? [];
        $dto->weeksAhead = $data['weeksAhead'] ?? 12;

        $errors = $this->validator->validate($dto);
        if (count($errors) > 0) {
            return $this->json(
                ValidationErrorFormatter::format($errors),
                Response::HTTP_BAD_REQUEST
            );
        }

        /** @var User $user */
        $user = $this->getUser();

        try {
            $roomUuid = Uuid::fromString($dto->roomId);
            $room = $this->entityManager->getRepository(Room::class)->find($roomUuid);

            if (!$room) {
                return $this->json(['code' => Response::HTTP_NOT_FOUND, 'message' => 'Room not found'], Response::HTTP_NOT_FOUND);
            }

            $result = $this->bookingService->createRecurringBooking(
                $room,
                $user,
                $dto->type,
                $dto->startTime,
                $dto->endTime,
                $dto->daysOfWeek,
                $dto->weeksAhead
            );

            return $this->json($result->toArray(), Response::HTTP_CREATED);
        } catch (InvalidArgumentException $e) {
            return $this->json(['code' => Response::HTTP_BAD_REQUEST, 'message' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (DateMalformedStringException $e) {
            return $this->json(['code' => Response::HTTP_BAD_REQUEST, 'message' => $e->getMessage()]);
        }
    }

    #[Route('/{id}', name: 'bookings_update', methods: ['PUT', 'PATCH'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[OA\Put(
        path: '/api/bookings/{id}',
        description: 'Updates booking details. Only the booking owner or admin can update. Cannot update cancelled or completed bookings. Cannot update to past dates. Validates for conflicts and date constraints.',
        summary: 'Update an existing booking',
        security: [['Bearer' => []]],
        requestBody: new OA\RequestBody(
            description: 'Booking data to update',
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'title', type: 'string', maxLength: 255, minLength: 3, example: 'Updated Meeting Title'),
                    new OA\Property(property: 'roomId', type: 'string', format: 'uuid', example: '019afaf8-7edc-7935-9afc-d94a15e0e7ed'),
                    new OA\Property(property: 'startedAt', description: 'ISO 8601 format', type: 'string', format: 'date-time', example: '2025-12-08T13:16:23'),
                    new OA\Property(property: 'endedAt', description: 'ISO 8601 format', type: 'string', format: 'date-time', example: '2025-12-08T15:15:23'),
                    new OA\Property(property: 'participantsCount', type: 'integer', minimum: 1, example: 8),
                    new OA\Property(property: 'participantIds', type: 'array', items: new OA\Items(type: 'string', format: 'uuid'), example: ['019afaf8-8087-793e-83e8-e83bab4145c0'], nullable: true),
                    new OA\Property(property: 'isPrivate', type: 'boolean', example: false)
                ]
            )
        ),
        tags: ['Bookings'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'UUID of the booking to update',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Booking updated successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 200),
                        new OA\Property(property: 'message', type: 'string', example: 'Booking updated successfully'),
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                                new OA\Property(property: 'title', type: 'string'),
                                new OA\Property(property: 'startedAt', type: 'string', format: 'date-time'),
                                new OA\Property(property: 'endedAt', type: 'string', format: 'date-time'),
                                new OA\Property(property: 'participantsCount', type: 'integer'),
                                new OA\Property(property: 'participants', type: 'array', items: new OA\Items(type: 'object')),
                                new OA\Property(property: 'isPrivate', type: 'boolean'),
                                new OA\Property(property: 'status', type: 'string'),
                                new OA\Property(property: 'room', type: 'object'),
                                new OA\Property(property: 'user', type: 'object'),
                                new OA\Property(property: 'createdAt', type: 'string', format: 'date-time')
                            ],
                            type: 'object'
                        )
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: 'Bad request - Validation error, Invalid JSON, or Date constraints',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 400),
                        new OA\Property(property: 'message', type: 'string', example: 'Validation failed')
                    ]
                )
            ),
            new OA\Response(
                response: 403,
                description: 'Forbidden - Cannot update cancelled/completed booking or not authorized',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 403),
                        new OA\Property(property: 'message', type: 'string', example: 'You are not authorized to update this booking')
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Not found - Booking or Room not found',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 404),
                        new OA\Property(property: 'message', type: 'string', example: 'Booking not found')
                    ]
                )
            ),
            new OA\Response(
                response: 409,
                description: 'Conflict - Time slot already booked',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 409),
                        new OA\Property(property: 'message', type: 'string', example: 'The room is already booked for this time slot')
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthorized',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 401),
                        new OA\Property(property: 'message', type: 'string', example: 'Unauthorized')
                    ]
                )
            )
        ]
    )]
    #[OA\Patch(
        path: '/api/bookings/{id}',
        description: 'Partially updates booking details. Only fields provided in the request will be updated. Only the booking owner or admin can update. Cannot update cancelled or completed bookings. Cannot update to past dates. Validates for conflicts and date constraints.',
        summary: 'Partially update an existing booking',
        security: [['Bearer' => []]],
        requestBody: new OA\RequestBody(
            description: 'Fields to update (all optional)',
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'title', type: 'string', maxLength: 255, minLength: 3, example: 'Updated Meeting Title'),
                    new OA\Property(property: 'roomId', type: 'string', format: 'uuid', example: '019afaf8-7edc-7935-9afc-d94a15e0e7ed'),
                    new OA\Property(property: 'startedAt', description: 'ISO 8601 format', type: 'string', format: 'date-time', example: '2025-12-08T13:16:23'),
                    new OA\Property(property: 'endedAt', description: 'ISO 8601 format', type: 'string', format: 'date-time', example: '2025-12-08T15:15:23'),
                    new OA\Property(property: 'participantsCount', type: 'integer', minimum: 1, example: 8),
                    new OA\Property(property: 'participantIds', type: 'array', items: new OA\Items(type: 'string', format: 'uuid'), example: ['019afaf8-8087-793e-83e8-e83bab4145c0'], nullable: true),
                    new OA\Property(property: 'isPrivate', type: 'boolean', example: false)
                ]
            )
        ),
        tags: ['Bookings'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'UUID of the booking to update',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Booking updated successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 200),
                        new OA\Property(property: 'message', type: 'string', example: 'Booking updated successfully'),
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                                new OA\Property(property: 'title', type: 'string'),
                                new OA\Property(property: 'startedAt', type: 'string', format: 'date-time'),
                                new OA\Property(property: 'endedAt', type: 'string', format: 'date-time'),
                                new OA\Property(property: 'participantsCount', type: 'integer'),
                                new OA\Property(property: 'participants', type: 'array', items: new OA\Items(type: 'object')),
                                new OA\Property(property: 'isPrivate', type: 'boolean'),
                                new OA\Property(property: 'status', type: 'string'),
                                new OA\Property(property: 'room', type: 'object'),
                                new OA\Property(property: 'user', type: 'object'),
                                new OA\Property(property: 'createdAt', type: 'string', format: 'date-time')
                            ],
                            type: 'object'
                        )
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: 'Bad request - validation failed or cannot update cancelled/completed booking or past date',
                content: new OA\JsonContent(
                    oneOf: [
                        new OA\Schema(
                            properties: [
                                new OA\Property(property: 'code', type: 'integer', example: 400),
                                new OA\Property(property: 'message', type: 'string', example: 'Validation failed'),
                                new OA\Property(
                                    property: 'violations',
                                    type: 'array',
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(property: 'field', type: 'string', example: 'title'),
                                            new OA\Property(property: 'message', type: 'string', example: 'Title must be at least 3 characters long')
                                        ]
                                    )
                                )
                            ]
                        ),
                        new OA\Schema(
                            properties: [
                                new OA\Property(property: 'code', type: 'integer', example: 400),
                                new OA\Property(property: 'message', type: 'string', example: 'Cannot update cancelled booking')
                            ]
                        ),
                        new OA\Schema(
                            properties: [
                                new OA\Property(property: 'code', type: 'integer', example: 400),
                                new OA\Property(property: 'message', type: 'string', example: 'Cannot update completed booking')
                            ]
                        ),
                        new OA\Schema(
                            properties: [
                                new OA\Property(property: 'code', type: 'integer', example: 400),
                                new OA\Property(property: 'message', type: 'string', example: 'Cannot update booking to past date')
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthorized - user not authenticated',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 401),
                        new OA\Property(property: 'message', type: 'string', example: 'User not authenticated')
                    ]
                )
            ),
            new OA\Response(
                response: 403,
                description: 'Forbidden - not authorized to update this booking',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 403),
                        new OA\Property(property: 'message', type: 'string', example: 'Not authorized to update this booking')
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Booking or room not found',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 404),
                        new OA\Property(property: 'message', type: 'string', example: 'Booking not found')
                    ]
                )
            ),
            new OA\Response(
                response: 409,
                description: 'Conflict - time slot already booked',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 409),
                        new OA\Property(property: 'message', type: 'string', example: 'Time slot already booked')
                    ]
                )
            )
        ]
    )]
    public function update(string $id, Request $request): Response
    {
        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            return $this->json([
                'code' => Response::HTTP_BAD_REQUEST,
                'message' => 'Invalid JSON'
            ], Response::HTTP_BAD_REQUEST);
        }

        $dto = UpdateBookingDTO::fromArray($data);

        $violations = $this->validator->validate($dto);
        if (count($violations) > 0) {
            return $this->json(
                ValidationErrorFormatter::format($violations),
                Response::HTTP_BAD_REQUEST
            );
        }

        try {
            $booking = $this->bookingService->getBookingById($id);

            $user = $this->getUser();
            if (!$user instanceof User) {
                return $this->json([
                    'code' => Response::HTTP_UNAUTHORIZED,
                    'message' => 'User not authenticated'
                ], Response::HTTP_UNAUTHORIZED);
            }

            if (!$this->bookingService->canUserEditBooking($booking, $user)) {
                return $this->json([
                    'code' => Response::HTTP_FORBIDDEN,
                    'message' => 'Not authorized to update this booking'
                ], Response::HTTP_FORBIDDEN);
            }

            if ($booking->getStatus() === 'cancelled') {
                return $this->json([
                    'code' => Response::HTTP_BAD_REQUEST,
                    'message' => 'Cannot update cancelled booking'
                ], Response::HTTP_BAD_REQUEST);
            }

            if ($booking->getStatus() === 'completed') {
                return $this->json([
                    'code' => Response::HTTP_BAD_REQUEST,
                    'message' => 'Cannot update completed booking'
                ], Response::HTTP_BAD_REQUEST);
            }

            $updatedBooking = $this->bookingService->handleUpdateBooking($booking, $dto);

            return $this->json([
                'code' => Response::HTTP_OK,
                'message' => 'Booking updated successfully',
                'data' => new BookingResponseDTO($updatedBooking)->toArray()
            ], Response::HTTP_OK);
        } catch (InvalidArgumentException $e) {
            if (str_starts_with($e->getMessage(), '{')) {
                $errorData = json_decode($e->getMessage(), true);
                return $this->json($errorData, Response::HTTP_CONFLICT);
            }

            return $this->json([
                'code' => Response::HTTP_BAD_REQUEST,
                'message' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/{id}/cancel', name: 'bookings_cancel', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[OA\Post(
        path: '/api/bookings/{id}/cancel',
        description: 'Cancels an existing booking. Only the booking owner or admin can cancel. Already cancelled bookings cannot be cancelled again.',
        summary: 'Cancel a booking',
        security: [['Bearer' => []]],
        tags: ['Bookings'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'UUID of the booking to cancel',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Booking cancelled successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 200),
                        new OA\Property(property: 'message', type: 'string', example: 'Booking cancelled successfully'),
                        new OA\Property(
                            property: 'booking',
                            properties: [
                                new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                                new OA\Property(property: 'title', type: 'string'),
                                new OA\Property(property: 'startedAt', type: 'string', format: 'date-time'),
                                new OA\Property(property: 'endedAt', type: 'string', format: 'date-time'),
                                new OA\Property(property: 'participantsCount', type: 'integer'),
                                new OA\Property(
                                    property: 'participants',
                                    type: 'array',
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                                            new OA\Property(property: 'username', type: 'string'),
                                            new OA\Property(property: 'firstName', type: 'string'),
                                            new OA\Property(property: 'lastName', type: 'string'),
                                            new OA\Property(property: 'email', type: 'string', format: 'email')
                                        ]
                                    )
                                ),
                                new OA\Property(property: 'isPrivate', type: 'boolean'),
                                new OA\Property(property: 'status', type: 'string', example: 'cancelled'),
                                new OA\Property(
                                    property: 'room',
                                    properties: [
                                        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                                        new OA\Property(property: 'roomName', type: 'string'),
                                        new OA\Property(property: 'location', type: 'string')
                                    ],
                                    type: 'object'
                                ),
                                new OA\Property(
                                    property: 'user',
                                    properties: [
                                        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                                        new OA\Property(property: 'username', type: 'string'),
                                        new OA\Property(property: 'firstName', type: 'string'),
                                        new OA\Property(property: 'lastName', type: 'string')
                                    ],
                                    type: 'object'
                                ),
                                new OA\Property(property: 'createdAt', type: 'string', format: 'date-time')
                            ],
                            type: 'object'
                        )
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: 'Bad request - booking already cancelled or invalid UUID',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 400),
                        new OA\Property(property: 'message', type: 'string', example: 'Booking already cancelled')
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthorized - user not authenticated',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 401),
                        new OA\Property(property: 'message', type: 'string', example: 'User not authenticated')
                    ]
                )
            ),
            new OA\Response(
                response: 403,
                description: 'Forbidden - not authorized to cancel this booking',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 403),
                        new OA\Property(property: 'message', type: 'string', example: 'Not authorized to cancel this booking')
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Booking not found',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 404),
                        new OA\Property(property: 'message', type: 'string', example: 'Booking not found')
                    ]
                )
            )
        ]
    )]
    public function cancel(string $id): Response
    {
        try {
            $booking = $this->bookingService->getBookingById($id);

            $user = $this->getUser();
            if (!$user instanceof User) {
                return $this->json([
                    'code' => Response::HTTP_UNAUTHORIZED,
                    'message' => 'User not authenticated'
                ], Response::HTTP_UNAUTHORIZED);
            }

            if (!$this->bookingService->canUserCancelBooking($booking, $user)) {
                return $this->json([
                    'code' => Response::HTTP_FORBIDDEN,
                    'message' => 'Not authorized to cancel this booking'
                ], Response::HTTP_FORBIDDEN);
            }

            $response = $this->bookingService->handleCancelBooking($booking);

            return $this->json($response->toArray(), Response::HTTP_OK);
        } catch (InvalidArgumentException $e) {
            return $this->json([
                'code' => Response::HTTP_BAD_REQUEST,
                'message' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/count', name: 'bookings_count', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[OA\Get(
        path: '/api/bookings/count',
        description: 'Returns the total count and breakdown by status (active, completed, cancelled) of all bookings for the authenticated user.',
        summary: 'Get booking statistics for current user',
        security: [['Bearer' => []]],
        tags: ['Bookings'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Returns booking count statistics for the current user',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'count', description: 'Total number of bookings', type: 'integer', example: 15),
                        new OA\Property(property: 'active', description: 'Number of active bookings', type: 'integer', example: 3),
                        new OA\Property(property: 'completed', description: 'Number of completed bookings', type: 'integer', example: 10),
                        new OA\Property(property: 'cancelled', description: 'Number of cancelled bookings', type: 'integer', example: 2)
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthorized - user not authenticated',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 401),
                        new OA\Property(property: 'message', type: 'string', example: 'Unauthorized')
                    ]
                )
            )
        ]
    )]
    public function getCount(): Response
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json([
                'code' => Response::HTTP_UNAUTHORIZED,
                'message' => 'Unauthorized'
            ], Response::HTTP_UNAUTHORIZED);
        }

        $response = $this->bookingService->getBookingCounts($user);

        return $this->json($response->toArray(), Response::HTTP_OK);
    }

    #[Route('/organization/count', name: 'bookings_organization_count', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    #[OA\Get(
        path: '/api/bookings/organization/count',
        description: 'Returns the total count and breakdown by status (active, completed, cancelled) of all bookings within the authenticated user\'s organization.',
        summary: 'Get booking statistics for user\'s organization',
        security: [['Bearer' => []]],
        tags: ['Bookings'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Returns booking count statistics for the organization',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'count', description: 'Total number of organization bookings', type: 'integer', example: 150),
                        new OA\Property(property: 'active', description: 'Number of active bookings', type: 'integer', example: 50),
                        new OA\Property(property: 'completed', description: 'Number of completed bookings', type: 'integer', example: 80),
                        new OA\Property(property: 'cancelled', description: 'Number of cancelled bookings', type: 'integer', example: 20)
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthorized - user not authenticated',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 401),
                        new OA\Property(property: 'message', type: 'string', example: 'Unauthorized')
                    ]
                )
            ),
            new OA\Response(
                response: 403,
                description: 'Forbidden - Requires ROLE_ADMIN',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 403),
                        new OA\Property(property: 'message', type: 'string', example: 'Access denied. You do not have sufficient permissions to access this resource.')
                    ]
                )
            )
        ]
    )]
    public function getOrganizationCount(): Response
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json([
                'code' => Response::HTTP_UNAUTHORIZED,
                'message' => 'Unauthorized'
            ], Response::HTTP_UNAUTHORIZED);
        }

        $response = $this->bookingService->getBookingCountsByOrganization($user->getOrganization());

        return $this->json($response->toArray(), Response::HTTP_OK);
    }

    #[Route('/statistics/total', name: 'bookings_statistics_total', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    #[OA\Get(
        path: '/api/bookings/statistics/total',
        description: 'Returns the total number of bookings for the organization with breakdown by time periods: this month, this week, and today.',
        summary: 'Get total booking statistics with time breakdown',
        security: [['Bearer' => []]],
        tags: ['Bookings'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Total booking statistics',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'total', description: 'Total number of all bookings', type: 'integer', example: 847),
                        new OA\Property(property: 'thisMonth', description: 'Bookings created this month', type: 'integer', example: 42),
                        new OA\Property(property: 'thisWeek', description: 'Bookings created this week', type: 'integer', example: 12),
                        new OA\Property(property: 'today', description: 'Bookings created today', type: 'integer', example: 3)
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthorized - user not authenticated',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 401),
                        new OA\Property(property: 'message', type: 'string', example: 'Unauthorized')
                    ]
                )
            ),
            new OA\Response(
                response: 403,
                description: 'Forbidden - Requires ROLE_ADMIN',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 403),
                        new OA\Property(property: 'message', type: 'string', example: 'Access denied. You do not have sufficient permissions to access this resource.')
                    ]
                )
            )
        ]
    )]
    public function getTotalStats(): Response
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json([
                'code' => Response::HTTP_UNAUTHORIZED,
                'message' => 'Unauthorized'
            ], Response::HTTP_UNAUTHORIZED);
        }

        $response = $this->bookingService->getTotalBookingStats($user->getOrganization());

        return $this->json($response->toArray(), Response::HTTP_OK);
    }

    #[Route('/{id}', name: 'bookings_get', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[OA\Get(
        path: '/api/bookings/{id}',
        description: 'Retrieves detailed information about a specific booking including room, user, and participant information.',
        summary: 'Get booking details by ID',
        security: [['Bearer' => []]],
        tags: ['Bookings'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'UUID of the booking to retrieve',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Successfully retrieved booking details',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                        new OA\Property(property: 'title', type: 'string'),
                        new OA\Property(property: 'startedAt', type: 'string', format: 'date-time'),
                        new OA\Property(property: 'endedAt', type: 'string', format: 'date-time'),
                        new OA\Property(property: 'participantsCount', type: 'integer'),
                        new OA\Property(property: 'participants', type: 'array', items: new OA\Items(type: 'object')),
                        new OA\Property(property: 'isPrivate', type: 'boolean'),
                        new OA\Property(property: 'status', type: 'string', enum: ['active', 'cancelled', 'completed']),
                        new OA\Property(property: 'room', type: 'object'),
                        new OA\Property(property: 'user', type: 'object'),
                        new OA\Property(property: 'createdAt', type: 'string', format: 'date-time')
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(
                response: 400,
                description: 'Bad request - invalid UUID format',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 400),
                        new OA\Property(property: 'message', type: 'string', example: 'Invalid UUID format')
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Booking not found',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 404),
                        new OA\Property(property: 'message', type: 'string', example: 'Booking not found')
                    ]
                )
            )
        ]
    )]
    public function get(string $id): Response
    {
        try {
            $booking = $this->bookingService->getBookingById($id);

            return $this->json(new BookingResponseDTO($booking)->toArray(), Response::HTTP_OK);
        } catch (InvalidArgumentException $e) {
            return $this->json([
                'code' => Response::HTTP_NOT_FOUND,
                'message' => $e->getMessage()
            ], Response::HTTP_NOT_FOUND);
        }
    }

    #[Route('/statistics/occupancy_rate', name: 'bookings_occupancy_rate', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    #[OA\Get(
        path: '/api/bookings/statistics/occupancy_rate',
        description: 'Returns the occupancy rate (percentage of booked time vs available time) for each day of the week for the authenticated user\'s organization. Assumes 12 working hours per day per room.',
        summary: 'Get occupancy rate by day of week',
        security: [['Bearer' => []]],
        tags: ['Bookings'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Occupancy rate statistics by day of week',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(
                                property: 'dayOfWeek',
                                description: 'Day of the week',
                                type: 'string',
                                enum: ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
                                example: 'monday'
                            ),
                            new OA\Property(
                                property: 'occupancyRate',
                                description: 'Occupancy rate as percentage (0-100)',
                                type: 'number',
                                format: 'float',
                                example: 85.5
                            )
                        ],
                        type: 'object'
                    ),
                    example: [
                        ['dayOfWeek' => 'monday', 'occupancyRate' => 85.5],
                        ['dayOfWeek' => 'tuesday', 'occupancyRate' => 87.2],
                        ['dayOfWeek' => 'wednesday', 'occupancyRate' => 92.1],
                        ['dayOfWeek' => 'thursday', 'occupancyRate' => 88.3],
                        ['dayOfWeek' => 'friday', 'occupancyRate' => 95.0],
                        ['dayOfWeek' => 'saturday', 'occupancyRate' => 45.2],
                        ['dayOfWeek' => 'sunday', 'occupancyRate' => 30.1]
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthorized - missing or invalid authentication token',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 401),
                        new OA\Property(property: 'message', type: 'string', example: 'JWT Token not found')
                    ]
                )
            ),
            new OA\Response(
                response: 403,
                description: 'Forbidden - Requires ROLE_ADMIN',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 403),
                        new OA\Property(property: 'message', type: 'string', example: 'Access denied. You do not have sufficient permissions to access this resource.')
                    ]
                )
            )
        ]
    )]
    public function getOccupancyRate(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $organization = $user->getOrganization();

        $occupancyData = $this->bookingService->getOccupancyRateByDayOfWeek($organization);

        $result = array_map(fn($dto) => $dto->toArray(), $occupancyData);

        return $this->json($result, Response::HTTP_OK);
    }

    #[Route('/statistics/trend', name: 'bookings_trend', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    #[OA\Get(
        path: '/api/bookings/statistics/trend',
        description: 'Returns booking trends showing confirmed (active + completed), pending (future active), and cancelled bookings grouped by day of week',
        summary: 'Retrieve booking trend statistics by day of week',
        security: [['Bearer' => []]],
        tags: ['Bookings'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Successfully retrieved booking trends',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'confirmed',
                            description: 'Confirmed bookings (active + completed) by day',
                            properties: [
                                new OA\Property(property: 'Pon', type: 'integer'),
                                new OA\Property(property: 'Wt', type: 'integer'),
                                new OA\Property(property: 'Śr', type: 'integer'),
                                new OA\Property(property: 'Czw', type: 'integer'),
                                new OA\Property(property: 'Pt', type: 'integer'),
                                new OA\Property(property: 'Sob', type: 'integer'),
                                new OA\Property(property: 'Nie', type: 'integer')
                            ],
                            type: 'object'
                        ),
                        new OA\Property(
                            property: 'pending',
                            description: 'Pending bookings (future active) by day',
                            properties: [
                                new OA\Property(property: 'Pon', type: 'integer'),
                                new OA\Property(property: 'Wt', type: 'integer'),
                                new OA\Property(property: 'Śr', type: 'integer'),
                                new OA\Property(property: 'Czw', type: 'integer'),
                                new OA\Property(property: 'Pt', type: 'integer'),
                                new OA\Property(property: 'Sob', type: 'integer'),
                                new OA\Property(property: 'Nie', type: 'integer')
                            ],
                            type: 'object'
                        ),
                        new OA\Property(
                            property: 'cancelled',
                            description: 'Cancelled bookings by day',
                            properties: [
                                new OA\Property(property: 'Pon', type: 'integer'),
                                new OA\Property(property: 'Wt', type: 'integer'),
                                new OA\Property(property: 'Śr', type: 'integer'),
                                new OA\Property(property: 'Czw', type: 'integer'),
                                new OA\Property(property: 'Pt', type: 'integer'),
                                new OA\Property(property: 'Sob', type: 'integer'),
                                new OA\Property(property: 'Nie', type: 'integer')
                            ],
                            type: 'object'
                        )
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthorized - missing or invalid authentication token',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 401),
                        new OA\Property(property: 'message', type: 'string', example: 'JWT Token not found')
                    ]
                )
            ),
            new OA\Response(
                response: 403,
                description: 'Forbidden - Requires ROLE_ADMIN',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 403),
                        new OA\Property(property: 'message', type: 'string', example: 'Access denied. You do not have sufficient permissions to access this resource.')
                    ]
                )
            )
        ]
    )]
    public function getBookingTrend(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $organization = $user->getOrganization();

        $trendData = $this->bookingService->getBookingTrend($organization);

        return $this->json([
            'confirmed' => $trendData->confirmed,
            'pending' => $trendData->pending,
            'cancelled' => $trendData->cancelled
        ], Response::HTTP_OK);
    }
}
