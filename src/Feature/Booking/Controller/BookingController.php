<?php

declare(strict_types=1);

namespace App\Feature\Booking\Controller;

use App\Feature\Booking\DTO\BookingResponseDTO;
use App\Feature\Booking\DTO\CreateBookingDTO;
use App\Feature\Booking\DTO\UpdateBookingDTO;
use App\Feature\Booking\Service\BookingService;
use App\Feature\User\Entity\User;
use App\Common\Utility\ValidationErrorFormatter;
use InvalidArgumentException;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/bookings')]
#[OA\Tag(name: 'Bookings')]
class BookingController extends AbstractController
{
    public function __construct(
        private readonly BookingService $bookingService,
        private readonly ValidatorInterface $validator
    ) {
    }

    #[Route('', name: 'bookings_list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/bookings',
        summary: 'Retrieve list of bookings',
        description: 'Returns a list of all bookings. Bookings include details about the room, user, and participants.',
        security: [['Bearer' => []]],
        tags: ['Bookings'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Successfully retrieved list of bookings',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(
                        type: 'object',
                        properties: [
                            new OA\Property(property: 'id', type: 'string', format: 'uuid', description: 'Unique booking identifier'),
                            new OA\Property(property: 'title', type: 'string', description: 'Booking title or meeting name'),
                            new OA\Property(property: 'startedAt', type: 'string', format: 'date-time', description: 'Booking start time in ISO 8601 format'),
                            new OA\Property(property: 'endedAt', type: 'string', format: 'date-time', description: 'Booking end time in ISO 8601 format'),
                            new OA\Property(property: 'participantsCount', type: 'integer', description: 'Expected number of participants'),
                            new OA\Property(property: 'participants', type: 'array', items: new OA\Items(type: 'object'), description: 'List of booking participants'),
                            new OA\Property(property: 'isPrivate', type: 'boolean', description: 'Whether the booking is private'),
                            new OA\Property(property: 'status', type: 'string', enum: ['active', 'cancelled', 'completed'], description: 'Current booking status'),
                            new OA\Property(
                                property: 'room',
                                type: 'object',
                                description: 'Room information',
                                properties: [
                                    new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                                    new OA\Property(property: 'roomName', type: 'string'),
                                    new OA\Property(property: 'location', type: 'string')
                                ]
                            ),
                            new OA\Property(
                                property: 'user',
                                type: 'object',
                                description: 'User who created the booking',
                                properties: [
                                    new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                                    new OA\Property(property: 'username', type: 'string'),
                                    new OA\Property(property: 'firstName', type: 'string'),
                                    new OA\Property(property: 'lastName', type: 'string')
                                ]
                            ),
                            new OA\Property(property: 'createdAt', type: 'string', format: 'date-time', description: 'Timestamp when booking was created')
                        ]
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
    public function list(): JsonResponse
    {
        $data = $this->bookingService->getBookingsList();

        return new JsonResponse($data);
    }

    #[Route('', name: 'bookings_create', methods: ['POST'])]
    #[OA\Post(
        path: '/api/bookings',
        summary: 'Create a new booking',
        description: 'Creates a new room booking. The start time must be in the future and end time must be after start time. Checks for conflicts with existing bookings.',
        security: [['Bearer' => []]],
        tags: ['Bookings'],
        requestBody: new OA\RequestBody(
            required: true,
            description: 'Booking details',
            content: new OA\JsonContent(
                required: ['title', 'roomId', 'startedAt', 'endedAt', 'participantsCount'],
                properties: [
                    new OA\Property(
                        property: 'title',
                        type: 'string',
                        description: 'Booking title or meeting name',
                        minLength: 3,
                        maxLength: 255,
                        example: 'Team Meeting'
                    ),
                    new OA\Property(
                        property: 'roomId',
                        type: 'string',
                        format: 'uuid',
                        description: 'UUID of the room to book',
                        example: '019afaf8-7edc-7935-9afc-d94a15e0e7ed'
                    ),
                    new OA\Property(
                        property: 'startedAt',
                        type: 'string',
                        format: 'date-time',
                        description: 'Booking start time in ISO 8601 format. Must be in the future.',
                        example: '2025-12-08T13:16:23'
                    ),
                    new OA\Property(
                        property: 'endedAt',
                        type: 'string',
                        format: 'date-time',
                        description: 'Booking end time in ISO 8601 format. Must be after startedAt.',
                        example: '2025-12-08T15:15:23'
                    ),
                    new OA\Property(
                        property: 'participantsCount',
                        type: 'integer',
                        description: 'Expected number of participants',
                        minimum: 1,
                        example: 10
                    ),
                    new OA\Property(
                        property: 'participantIds',
                        type: 'array',
                        items: new OA\Items(type: 'string', format: 'uuid'),
                        nullable: true,
                        description: 'Optional array of user UUIDs who will participate',
                        example: ['019afaf8-8087-793e-83e8-e83bab4145c0']
                    ),
                    new OA\Property(
                        property: 'isPrivate',
                        type: 'boolean',
                        description: 'Whether the booking should be private',
                        example: true
                    )
                ]
            )
        ),
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
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'string', format: 'uuid', description: 'Unique booking identifier'),
                                new OA\Property(property: 'title', type: 'string', description: 'Booking title'),
                                new OA\Property(property: 'startedAt', type: 'string', format: 'date-time', description: 'Start time'),
                                new OA\Property(property: 'endedAt', type: 'string', format: 'date-time', description: 'End time'),
                                new OA\Property(property: 'participantsCount', type: 'integer', description: 'Number of participants'),
                                new OA\Property(
                                    property: 'participants',
                                    type: 'array',
                                    items: new OA\Items(
                                        type: 'object',
                                        properties: [
                                            new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                                            new OA\Property(property: 'username', type: 'string'),
                                            new OA\Property(property: 'firstName', type: 'string'),
                                            new OA\Property(property: 'lastName', type: 'string'),
                                            new OA\Property(property: 'email', type: 'string')
                                        ]
                                    )
                                ),
                                new OA\Property(property: 'isPrivate', type: 'boolean', description: 'Privacy status'),
                                new OA\Property(property: 'status', type: 'string', enum: ['active', 'cancelled', 'completed'], description: 'Booking status'),
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
                                new OA\Property(property: 'createdAt', type: 'string', format: 'date-time', description: 'Creation timestamp')
                            ]
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
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (!is_array($data)) {
            return $this->json([
                'code' => 400,
                'message' => 'Invalid JSON'
            ], 400);
        }
        
        $dto = CreateBookingDTO::fromArray($data);
        
        $violations = $this->validator->validate($dto);
        if (count($violations) > 0) {
            return $this->json(
                ValidationErrorFormatter::format($violations),
                400
            );
        }

        try {
            $user = $this->getUser();
            if (!$user instanceof User) {
                return new JsonResponse([
                    'code' => 401,
                    'message' => 'User not authenticated'
                ], 401);
            }

            $booking = $this->bookingService->handleCreateBooking($dto, $user);

            return new JsonResponse([
                'code' => 201,
                'message' => 'Booking created successfully',
                'data' => (new BookingResponseDTO($booking))->toArray()
            ], 201);
        } catch (InvalidArgumentException $e) {
            if (str_starts_with($e->getMessage(), '{')) {
                $errorData = json_decode($e->getMessage(), true);
                return new JsonResponse($errorData, 409);
            }

            return new JsonResponse([
                'code' => 400,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    #[Route('/{id}', name: 'bookings_update', methods: ['PUT', 'PATCH'])]
    #[OA\Put(
        path: '/api/bookings/{id}',
        summary: 'Update an existing booking',
        description: 'Updates booking details. Only the booking owner or admin can update. Cannot update cancelled or completed bookings. Cannot update to past dates. Validates for conflicts and date constraints.',
        security: [['Bearer' => []]],
        tags: ['Bookings'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'UUID of the booking to update',
                schema: new OA\Schema(type: 'string', format: 'uuid')
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            description: 'Booking data to update',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'title', type: 'string', minLength: 3, maxLength: 255, example: 'Updated Meeting Title'),
                    new OA\Property(property: 'roomId', type: 'string', format: 'uuid', example: '019afaf8-7edc-7935-9afc-d94a15e0e7ed'),
                    new OA\Property(property: 'startedAt', type: 'string', format: 'date-time', example: '2025-12-08T13:16:23', description: 'ISO 8601 format'),
                    new OA\Property(property: 'endedAt', type: 'string', format: 'date-time', example: '2025-12-08T15:15:23', description: 'ISO 8601 format'),
                    new OA\Property(property: 'participantsCount', type: 'integer', minimum: 1, example: 8),
                    new OA\Property(property: 'participantIds', type: 'array', items: new OA\Items(type: 'string', format: 'uuid'), nullable: true, example: ['019afaf8-8087-793e-83e8-e83bab4145c0']),
                    new OA\Property(property: 'isPrivate', type: 'boolean', example: false)
                ]
            )
        ),
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
                                new OA\Property(property: 'room', type: 'object'),
                                new OA\Property(property: 'user', type: 'object'),
                                new OA\Property(property: 'createdAt', type: 'string', format: 'date-time')
                            ]
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
        summary: 'Partially update an existing booking',
        description: 'Partially updates booking details. Only fields provided in the request will be updated. Only the booking owner or admin can update. Cannot update cancelled or completed bookings. Cannot update to past dates. Validates for conflicts and date constraints.',
        security: [['Bearer' => []]],
        tags: ['Bookings'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'UUID of the booking to update',
                schema: new OA\Schema(type: 'string', format: 'uuid')
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            description: 'Fields to update (all optional)',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'title', type: 'string', minLength: 3, maxLength: 255, example: 'Updated Meeting Title'),
                    new OA\Property(property: 'roomId', type: 'string', format: 'uuid', example: '019afaf8-7edc-7935-9afc-d94a15e0e7ed'),
                    new OA\Property(property: 'startedAt', type: 'string', format: 'date-time', example: '2025-12-08T13:16:23', description: 'ISO 8601 format'),
                    new OA\Property(property: 'endedAt', type: 'string', format: 'date-time', example: '2025-12-08T15:15:23', description: 'ISO 8601 format'),
                    new OA\Property(property: 'participantsCount', type: 'integer', minimum: 1, example: 8),
                    new OA\Property(property: 'participantIds', type: 'array', items: new OA\Items(type: 'string', format: 'uuid'), nullable: true, example: ['019afaf8-8087-793e-83e8-e83bab4145c0']),
                    new OA\Property(property: 'isPrivate', type: 'boolean', example: false)
                ]
            )
        ),
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
                                new OA\Property(property: 'room', type: 'object'),
                                new OA\Property(property: 'user', type: 'object'),
                                new OA\Property(property: 'createdAt', type: 'string', format: 'date-time')
                            ]
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
    public function update(string $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (!is_array($data)) {
            return $this->json([
                'code' => 400,
                'message' => 'Invalid JSON'
            ], 400);
        }
        
        $dto = UpdateBookingDTO::fromArray($data);
        
        $violations = $this->validator->validate($dto);
        if (count($violations) > 0) {
            return $this->json(
                ValidationErrorFormatter::format($violations),
                400
            );
        }

        try {
            $booking = $this->bookingService->getBookingById($id);

            $user = $this->getUser();
            if (!$user instanceof User) {
                return new JsonResponse([
                    'code' => 401,
                    'message' => 'User not authenticated'
                ], 401);
            }

            if (!$this->bookingService->canUserEditBooking($booking, $user)) {
                return new JsonResponse([
                    'code' => 403,
                    'message' => 'Not authorized to update this booking'
                ], 403);
            }

            if ($booking->getStatus() === 'cancelled') {
                return new JsonResponse([
                    'code' => 400,
                    'message' => 'Cannot update cancelled booking'
                ], 400);
            }

            if ($booking->getStatus() === 'completed') {
                return new JsonResponse([
                    'code' => 400,
                    'message' => 'Cannot update completed booking'
                ], 400);
            }

            $updatedBooking = $this->bookingService->handleUpdateBooking($booking, $dto);

            return new JsonResponse([
                'code' => 200,
                'message' => 'Booking updated successfully',
                'data' => (new BookingResponseDTO($updatedBooking))->toArray()
            ]);
        } catch (InvalidArgumentException $e) {
            if (str_starts_with($e->getMessage(), '{')) {
                $errorData = json_decode($e->getMessage(), true);
                return new JsonResponse($errorData, 409);
            }

            return new JsonResponse([
                'code' => 400,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    #[Route('/{id}/cancel', name: 'bookings_cancel', methods: ['POST'])]
    #[OA\Post(
        path: '/api/bookings/{id}/cancel',
        summary: 'Cancel a booking',
        description: 'Cancels an existing booking. Only the booking owner or admin can cancel. Already cancelled bookings cannot be cancelled again.',
        security: [['Bearer' => []]],
        tags: ['Bookings'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'UUID of the booking to cancel',
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
                            type: 'object',
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
    public function cancel(string $id): JsonResponse
    {
        try {
            $booking = $this->bookingService->getBookingById($id);

            $user = $this->getUser();
            if (!$user instanceof User) {
                return new JsonResponse([
                    'code' => 401,
                    'message' => 'User not authenticated'
                ], 401);
            }

            if (!$this->bookingService->canUserCancelBooking($booking, $user)) {
                return new JsonResponse([
                    'code' => 403,
                    'message' => 'Not authorized to cancel this booking'
                ], 403);
            }

            $response = $this->bookingService->handleCancelBooking($booking);

            return new JsonResponse($response->toArray());
        } catch (InvalidArgumentException $e) {
            return new JsonResponse([
                'code' => 400,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    #[Route('/count', name: 'bookings_count', methods: ['GET'])]
    #[OA\Get(
        path: '/api/bookings/count',
        summary: 'Get booking statistics for current user',
        description: 'Returns the total count and breakdown by status (active, completed, cancelled) of all bookings for the authenticated user.',
        security: [['Bearer' => []]],
        tags: ['Bookings'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Returns booking count statistics for the current user',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'count', type: 'integer', description: 'Total number of bookings', example: 15),
                        new OA\Property(property: 'active', type: 'integer', description: 'Number of active bookings', example: 3),
                        new OA\Property(property: 'completed', type: 'integer', description: 'Number of completed bookings', example: 10),
                        new OA\Property(property: 'cancelled', type: 'integer', description: 'Number of cancelled bookings', example: 2)
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
    public function getCount(): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return new JsonResponse([
                'code' => 401,
                'message' => 'Unauthorized'
            ], 401);
        }

        $response = $this->bookingService->getBookingCounts($user);

        return new JsonResponse($response->toArray());
    }

    #[Route('/{id}', name: 'bookings_get', methods: ['GET'])]
    #[OA\Get(
        path: '/api/bookings/{id}',
        summary: 'Get booking details by ID',
        description: 'Retrieves detailed information about a specific booking including room, user, and participant information.',
        security: [['Bearer' => []]],
        tags: ['Bookings'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'UUID of the booking to retrieve',
                schema: new OA\Schema(type: 'string', format: 'uuid')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Successfully retrieved booking details',
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
                        new OA\Property(property: 'status', type: 'string', enum: ['active', 'cancelled', 'completed']),
                        new OA\Property(property: 'room', type: 'object'),
                        new OA\Property(property: 'user', type: 'object'),
                        new OA\Property(property: 'createdAt', type: 'string', format: 'date-time')
                    ]
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
    public function get(string $id): JsonResponse
    {
        try {
            $booking = $this->bookingService->getBookingById($id);

            return new JsonResponse((new BookingResponseDTO($booking))->toArray());
        } catch (InvalidArgumentException $e) {
            return new JsonResponse([
                'code' => 404,
                'message' => $e->getMessage()
            ], 404);
        }
    }
}
