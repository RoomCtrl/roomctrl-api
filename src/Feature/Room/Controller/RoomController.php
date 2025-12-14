<?php

declare(strict_types=1);

namespace App\Feature\Room\Controller;

use App\Feature\Room\Entity\Room;
use App\Feature\Room\DTO\CreateRoomRequest;
use App\Feature\Room\DTO\UpdateRoomRequest;
use App\Feature\Room\Service\RoomService;
use App\Feature\Organization\Entity\Organization;
use App\Feature\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Uid\Uuid;
use OpenApi\Attributes as OA;
use Exception;

#[Route('/rooms')]
#[OA\Tag(name: 'Rooms')]
class RoomController extends AbstractController
{
    public function __construct(
        private readonly RoomService $roomService,
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator
    ) {
    }

    #[Route('', name: 'rooms_list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/rooms',
        summary: 'Get all rooms',
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(
                name: 'status',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['available', 'occupied', 'maintenance']),
                description: 'Filter by room status'
            ),
            new OA\Parameter(
                name: 'withBookings',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'boolean'),
                description: 'Include current and next bookings'
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Returns list of rooms',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(
                        type: 'object',
                        properties: [
                            new OA\Property(property: 'roomId', type: 'string', format: 'uuid'),
                            new OA\Property(property: 'roomName', type: 'string'),
                            new OA\Property(property: 'status', type: 'string', enum: ['available', 'occupied', 'maintenance']),
                            new OA\Property(property: 'capacity', type: 'integer'),
                            new OA\Property(property: 'size', type: 'number', format: 'float', description: 'Size in square meters'),
                            new OA\Property(property: 'location', type: 'string'),
                            new OA\Property(property: 'access', type: 'string'),
                            new OA\Property(property: 'description', type: 'string'),
                            new OA\Property(property: 'lighting', type: 'string'),
                            new OA\Property(
                                property: 'airConditioning',
                                type: 'object',
                                nullable: true,
                                properties: [
                                    new OA\Property(property: 'min', type: 'number'),
                                    new OA\Property(property: 'max', type: 'number')
                                ]
                            ),
                            new OA\Property(property: 'imagePath', type: 'string', nullable: true, description: 'Path to uploaded image or PDF file'),
                            new OA\Property(
                                property: 'equipment',
                                type: 'array',
                                items: new OA\Items(
                                    type: 'object',
                                    properties: [
                                        new OA\Property(property: 'name', type: 'string'),
                                        new OA\Property(property: 'category', type: 'string', enum: ['video', 'audio', 'computer', 'accessory', 'furniture']),
                                        new OA\Property(property: 'quantity', type: 'integer')
                                    ]
                                )
                            ),
                            new OA\Property(
                                property: 'currentBooking',
                                type: 'object',
                                nullable: true,
                                description: 'Present only when withBookings=true',
                                properties: [
                                    new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                                    new OA\Property(property: 'title', type: 'string'),
                                    new OA\Property(property: 'startedAt', type: 'string', format: 'date-time'),
                                    new OA\Property(property: 'endedAt', type: 'string', format: 'date-time'),
                                    new OA\Property(property: 'participants', type: 'integer'),
                                    new OA\Property(property: 'isPrivate', type: 'boolean')
                                ]
                            ),
                            new OA\Property(
                                property: 'nextBookings',
                                type: 'array',
                                description: 'Present only when withBookings=true',
                                items: new OA\Items(
                                    type: 'object',
                                    properties: [
                                        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                                        new OA\Property(property: 'title', type: 'string'),
                                        new OA\Property(property: 'startedAt', type: 'string', format: 'date-time'),
                                        new OA\Property(property: 'endedAt', type: 'string', format: 'date-time'),
                                        new OA\Property(property: 'participants', type: 'integer'),
                                        new OA\Property(property: 'isPrivate', type: 'boolean')
                                    ]
                                )
                            )
                        ]
                    ),
                    example: [
                        [
                            'roomId' => '01234567-89ab-cdef-0123-456789abcdef',
                            'roomName' => 'Sala Konferencyjna 201',
                            'status' => 'available',
                            'capacity' => 12,
                            'size' => 45.5,
                            'location' => 'Piętro 2, Skrzydło A',
                            'access' => 'Karta magnetyczna',
                            'description' => 'Przestronna sala konferencyjna z naturalnym oświetleniem',
                            'lighting' => 'natural',
                            'airConditioning' => ['min' => 18, 'max' => 24],
                            'equipment' => [
                                ['name' => 'Projektor', 'category' => 'video', 'quantity' => 1],
                                ['name' => 'Tablica', 'category' => 'furniture', 'quantity' => 1]
                            ]
                        ]
                    ]
                )
            )
        ]
    )]
    public function list(Request $request): JsonResponse
    {
        $status = $request->query->get('status');
        $withBookings = $request->query->getBoolean('withBookings', false);

        $rooms = $this->roomService->getAllRooms($status);
        $data = $this->roomService->serializeRooms($rooms, $withBookings);

        return new JsonResponse(array_values($data));
    }

    #[Route('/favorites', name: 'rooms_favorites_list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/rooms/favorites',
        summary: 'Get favorite rooms for the current user',
        security: [['Bearer' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Returns list of favorite rooms',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(
                        type: 'object',
                        properties: [
                            new OA\Property(property: 'roomId', type: 'string', format: 'uuid'),
                            new OA\Property(property: 'roomName', type: 'string'),
                            new OA\Property(property: 'status', type: 'string', enum: ['available', 'occupied', 'maintenance']),
                            new OA\Property(property: 'capacity', type: 'integer'),
                            new OA\Property(property: 'size', type: 'number', format: 'float'),
                            new OA\Property(property: 'location', type: 'string'),
                            new OA\Property(property: 'access', type: 'string'),
                            new OA\Property(property: 'description', type: 'string'),
                            new OA\Property(property: 'lighting', type: 'string'),
                            new OA\Property(
                                property: 'airConditioning',
                                type: 'object',
                                nullable: true,
                                properties: [
                                    new OA\Property(property: 'min', type: 'number'),
                                    new OA\Property(property: 'max', type: 'number')
                                ]
                            ),
                            new OA\Property(property: 'imagePath', type: 'string', nullable: true),
                            new OA\Property(
                                property: 'equipment',
                                type: 'array',
                                items: new OA\Items(
                                    type: 'object',
                                    properties: [
                                        new OA\Property(property: 'name', type: 'string'),
                                        new OA\Property(property: 'category', type: 'string'),
                                        new OA\Property(property: 'quantity', type: 'integer')
                                    ]
                                )
                            )
                        ]
                    )
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthorized'
            )
        ]
    )]
    public function getFavorites(): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();
        
        if (!$user) {
            return new JsonResponse([
                'code' => 401,
                'message' => 'Unauthorized'
            ], 401);
        }

        $favoriteRooms = $this->roomService->getFavoriteRooms($user);
        $data = $this->roomService->serializeRooms($favoriteRooms, false);

        return new JsonResponse(array_values($data));
    }

    #[Route('/recent', name: 'rooms_recent', methods: ['GET'])]
    #[OA\Get(
        path: '/api/rooms/recent',
        summary: 'Get last 3 rooms booked by the current user',
        security: [['Bearer' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Returns list of recently booked rooms',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(
                        type: 'object',
                        properties: [
                            new OA\Property(property: 'roomId', type: 'string', format: 'uuid'),
                            new OA\Property(property: 'roomName', type: 'string'),
                            new OA\Property(property: 'status', type: 'string', enum: ['available', 'occupied', 'maintenance']),
                            new OA\Property(property: 'capacity', type: 'integer'),
                            new OA\Property(property: 'size', type: 'number', format: 'float'),
                            new OA\Property(property: 'location', type: 'string'),
                            new OA\Property(property: 'access', type: 'string'),
                            new OA\Property(property: 'description', type: 'string'),
                            new OA\Property(property: 'lighting', type: 'string'),
                            new OA\Property(
                                property: 'airConditioning',
                                type: 'object',
                                nullable: true,
                                properties: [
                                    new OA\Property(property: 'min', type: 'number'),
                                    new OA\Property(property: 'max', type: 'number')
                                ]
                            ),
                            new OA\Property(property: 'imagePath', type: 'string', nullable: true),
                            new OA\Property(
                                property: 'equipment',
                                type: 'array',
                                items: new OA\Items(
                                    type: 'object',
                                    properties: [
                                        new OA\Property(property: 'name', type: 'string'),
                                        new OA\Property(property: 'category', type: 'string'),
                                        new OA\Property(property: 'quantity', type: 'integer')
                                    ]
                                )
                            ),
                            new OA\Property(
                                property: 'lastBooking',
                                type: 'object',
                                nullable: true,
                                properties: [
                                    new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                                    new OA\Property(property: 'title', type: 'string'),
                                    new OA\Property(property: 'startedAt', type: 'string', format: 'date-time'),
                                    new OA\Property(property: 'endedAt', type: 'string', format: 'date-time')
                                ]
                            )
                        ]
                    )
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthorized'
            )
        ]
    )]
    public function getRecentRooms(): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();
        
        if (!$user) {
            return new JsonResponse([
                'code' => 401,
                'message' => 'Unauthorized'
            ], 401);
        }

        $recentRooms = $this->roomService->getRecentlyBookedRooms($user, 3);
        
        $data = array_map(function ($item) {
            return $this->roomService->serializeRoom($item['room'], false) + [
                'lastBooking' => $item['lastBooking'] ? [
                    'id' => $item['lastBooking']->getId()->toRfc4122(),
                    'title' => $item['lastBooking']->getTitle(),
                    'startedAt' => $item['lastBooking']->getStartedAt()->format('c'),
                    'endedAt' => $item['lastBooking']->getEndedAt()->format('c')
                ] : null
            ];
        }, $recentRooms);

        return new JsonResponse($data);
    }

    #[Route('/{id}', name: 'rooms_get', methods: ['GET'])]
    #[OA\Get(
        path: '/api/rooms/{id}',
        summary: 'Get a single room by ID',
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Returns room details',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'roomId', type: 'string', format: 'uuid'),
                        new OA\Property(property: 'roomName', type: 'string'),
                        new OA\Property(property: 'status', type: 'string', enum: ['available', 'occupied', 'maintenance']),
                        new OA\Property(property: 'capacity', type: 'integer'),
                        new OA\Property(property: 'size', type: 'number', format: 'float'),
                        new OA\Property(property: 'location', type: 'string'),
                        new OA\Property(property: 'access', type: 'string'),
                        new OA\Property(property: 'description', type: 'string'),
                        new OA\Property(property: 'lighting', type: 'string'),
                        new OA\Property(
                            property: 'airConditioning',
                            type: 'object',
                            nullable: true,
                            properties: [
                                new OA\Property(property: 'min', type: 'number'),
                                new OA\Property(property: 'max', type: 'number')
                            ]
                        ),
                        new OA\Property(property: 'imagePath', type: 'string', nullable: true, description: 'Path to uploaded image or PDF file'),
                        new OA\Property(
                            property: 'equipment',
                            type: 'array',
                            items: new OA\Items(
                                type: 'object',
                                properties: [
                                    new OA\Property(property: 'name', type: 'string'),
                                    new OA\Property(property: 'category', type: 'string'),
                                    new OA\Property(property: 'quantity', type: 'integer')
                                ]
                            )
                        ),
                        new OA\Property(
                            property: 'currentBooking',
                            type: 'object',
                            nullable: true,
                            properties: [
                                new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                                new OA\Property(property: 'title', type: 'string'),
                                new OA\Property(property: 'startedAt', type: 'string', format: 'date-time'),
                                new OA\Property(property: 'endedAt', type: 'string', format: 'date-time'),
                                new OA\Property(property: 'participants', type: 'integer'),
                                new OA\Property(property: 'isPrivate', type: 'boolean')
                            ]
                        ),
                        new OA\Property(
                            property: 'nextBookings',
                            type: 'array',
                            items: new OA\Items(
                                type: 'object',
                                properties: [
                                    new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                                    new OA\Property(property: 'title', type: 'string'),
                                    new OA\Property(property: 'startedAt', type: 'string', format: 'date-time'),
                                    new OA\Property(property: 'endedAt', type: 'string', format: 'date-time'),
                                    new OA\Property(property: 'participants', type: 'integer'),
                                    new OA\Property(property: 'isPrivate', type: 'boolean')
                                ]
                            )
                        )
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Room not found')
        ]
    )]
    public function get(string $id): JsonResponse
    {
        try {
            $uuid = Uuid::fromString($id);
        } catch (Exception $e) {
            return new JsonResponse([
                'code' => 400,
                'message' => 'Invalid UUID format'
            ], 400);
        }

        $room = $this->roomService->getRoomById($uuid);

        if (!$room) {
            return new JsonResponse([
                'code' => 404,
                'message' => 'Room not found'
            ], 404);
        }

        return new JsonResponse($this->roomService->serializeRoom($room, true));
    }

    #[Route('', name: 'rooms_create', methods: ['POST'])]
    #[OA\Post(
        path: '/api/rooms',
        summary: 'Create a new room',
        security: [['Bearer' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['roomName', 'capacity', 'size', 'location', 'access', 'organizationId'],
                properties: [
                    new OA\Property(property: 'roomName', type: 'string'),
                    new OA\Property(property: 'status', type: 'string', enum: ['available', 'occupied', 'maintenance']),
                    new OA\Property(property: 'capacity', type: 'integer', minimum: 1, maximum: 200),
                    new OA\Property(property: 'size', type: 'number', format: 'float', description: 'Size in square meters'),
                    new OA\Property(property: 'location', type: 'string'),
                    new OA\Property(property: 'access', type: 'string', description: 'Access method: magnetic card, pin, key, biometric, etc.'),
                    new OA\Property(property: 'description', type: 'string'),
                    new OA\Property(property: 'lighting', type: 'string'),
                    new OA\Property(
                        property: 'airConditioning',
                        type: 'object',
                        properties: [
                            new OA\Property(property: 'min', type: 'number'),
                            new OA\Property(property: 'max', type: 'number')
                        ],
                        example: ['min' => 18, 'max' => 24],
                        description: 'Air conditioning temperature range'
                    ),
                    new OA\Property(property: 'organizationId', type: 'string', format: 'uuid'),
                    new OA\Property(
                        property: 'equipment',
                        type: 'array',
                        items: new OA\Items(
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'name', type: 'string'),
                                new OA\Property(property: 'category', type: 'string', enum: ['video', 'audio', 'computer', 'accessory', 'furniture']),
                                new OA\Property(property: 'quantity', type: 'integer')
                            ]
                        )
                    )
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Room created successfully',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'roomId', type: 'string', format: 'uuid'),
                        new OA\Property(property: 'roomName', type: 'string'),
                        new OA\Property(property: 'status', type: 'string'),
                        new OA\Property(property: 'capacity', type: 'integer'),
                        new OA\Property(property: 'size', type: 'number', format: 'float'),
                        new OA\Property(property: 'location', type: 'string'),
                        new OA\Property(property: 'access', type: 'string'),
                        new OA\Property(property: 'description', type: 'string'),
                        new OA\Property(property: 'lighting', type: 'string'),
                        new OA\Property(
                            property: 'airConditioning',
                            type: 'object',
                            nullable: true,
                            properties: [
                                new OA\Property(property: 'min', type: 'number'),
                                new OA\Property(property: 'max', type: 'number')
                            ]
                        ),
                        new OA\Property(property: 'imagePath', type: 'string', nullable: true),
                        new OA\Property(
                            property: 'equipment',
                            type: 'array',
                            items: new OA\Items(
                                type: 'object',
                                properties: [
                                    new OA\Property(property: 'name', type: 'string'),
                                    new OA\Property(property: 'category', type: 'string'),
                                    new OA\Property(property: 'quantity', type: 'integer')
                                ]
                            )
                        )
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
            )
        ]
    )]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $createRequest = CreateRoomRequest::fromArray($data);
        
        $errors = $this->validator->validate($createRequest);
        if (count($errors) > 0) {
            return new JsonResponse([
                'code' => 400,
                'message' => 'Validation failed',
                'errors' => (string) $errors
            ], 400);
        }

        try {
            $orgUuid = Uuid::fromString($createRequest->organizationId);
        } catch (Exception $e) {
            return new JsonResponse([
                'code' => 400,
                'message' => 'Invalid organization UUID format'
            ], 400);
        }

        $organization = $this->entityManager->getRepository(Organization::class)->find($orgUuid);
        if (!$organization) {
            return new JsonResponse([
                'code' => 404,
                'message' => 'Organization not found'
            ], 404);
        }

        $room = $this->roomService->createRoom(
            roomName: $createRequest->roomName,
            capacity: $createRequest->capacity,
            size: $createRequest->size,
            location: $createRequest->location,
            access: $createRequest->access,
            organization: $organization,
            status: $createRequest->status,
            description: $createRequest->description,
            lighting: $createRequest->lighting,
            airConditioning: $createRequest->airConditioning,
            equipment: $createRequest->equipment
        );

        return new JsonResponse([
            'code' => 201,
            'message' => 'Room created successfully',
            'data' => $this->roomService->serializeRoom($room, false)
        ], 201);
    }

    #[Route('/{id}', name: 'rooms_update', methods: ['PUT'])]
    #[OA\Put(
        path: '/api/rooms/{id}',
        summary: 'Update a room',
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(property: 'roomName', type: 'string'),
                    new OA\Property(property: 'status', type: 'string', enum: ['available', 'occupied', 'maintenance']),
                    new OA\Property(property: 'capacity', type: 'integer'),
                    new OA\Property(property: 'size', type: 'number', format: 'float'),
                    new OA\Property(property: 'location', type: 'string'),
                    new OA\Property(property: 'access', type: 'string'),
                    new OA\Property(property: 'description', type: 'string'),
                    new OA\Property(property: 'lighting', type: 'string'),
                    new OA\Property(
                        property: 'airConditioning',
                        type: 'object',
                        nullable: true,
                        properties: [
                            new OA\Property(property: 'min', type: 'number'),
                            new OA\Property(property: 'max', type: 'number')
                        ]
                    )
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Room updated successfully',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'roomId', type: 'string', format: 'uuid'),
                        new OA\Property(property: 'roomName', type: 'string'),
                        new OA\Property(property: 'status', type: 'string'),
                        new OA\Property(property: 'capacity', type: 'integer'),
                        new OA\Property(property: 'size', type: 'number', format: 'float'),
                        new OA\Property(property: 'location', type: 'string'),
                        new OA\Property(property: 'access', type: 'string'),
                        new OA\Property(property: 'description', type: 'string'),
                        new OA\Property(property: 'lighting', type: 'string'),
                        new OA\Property(
                            property: 'airConditioning',
                            type: 'object',
                            nullable: true,
                            properties: [
                                new OA\Property(property: 'min', type: 'number'),
                                new OA\Property(property: 'max', type: 'number')
                            ]
                        ),
                        new OA\Property(property: 'imagePath', type: 'string', nullable: true),
                        new OA\Property(
                            property: 'equipment',
                            type: 'array',
                            items: new OA\Items(
                                type: 'object',
                                properties: [
                                    new OA\Property(property: 'name', type: 'string'),
                                    new OA\Property(property: 'category', type: 'string'),
                                    new OA\Property(property: 'quantity', type: 'integer')
                                ]
                            )
                        )
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Room not found',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'error', type: 'string')
                    ]
                )
            )
        ]
    )]
    public function update(string $id, Request $request): JsonResponse
    {
        try {
            $uuid = Uuid::fromString($id);
        } catch (Exception $e) {
            return new JsonResponse([
                'code' => 400,
                'message' => 'Invalid UUID format'
            ], 400);
        }

        $room = $this->roomService->getRoomById($uuid);
        if (!$room) {
            return new JsonResponse([
                'code' => 404,
                'message' => 'Room not found'
            ], 404);
        }

        $data = json_decode($request->getContent(), true);
        
        $updateRequest = UpdateRoomRequest::fromArray($data);
        
        $errors = $this->validator->validate($updateRequest);
        if (count($errors) > 0) {
            return new JsonResponse([
                'code' => 400,
                'message' => 'Validation failed',
                'errors' => (string) $errors
            ], 400);
        }

        $room = $this->roomService->updateRoom(
            room: $room,
            roomName: $updateRequest->roomName,
            capacity: $updateRequest->capacity,
            size: $updateRequest->size,
            location: $updateRequest->location,
            access: $updateRequest->access,
            status: $updateRequest->status,
            description: $updateRequest->description,
            lighting: $updateRequest->lighting,
            airConditioning: $updateRequest->airConditioning
        );

        return new JsonResponse([
            'code' => 200,
            'message' => 'Room updated successfully',
            'data' => $this->roomService->serializeRoom($room, false)
        ]);
    }

    #[Route('/{id}/upload', name: 'rooms_upload_image', methods: ['POST'])]
    #[OA\Post(
        path: '/api/rooms/{id}/upload',
        summary: 'Upload image or PDF for a room',
        security: [['Bearer' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['file'],
                    properties: [
                        new OA\Property(
                            property: 'file',
                            type: 'string',
                            format: 'binary',
                            description: 'Image file (JPG, PNG) or PDF document'
                        )
                    ]
                )
            )
        ),
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'File uploaded successfully',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'message', type: 'string'),
                        new OA\Property(property: 'imagePath', type: 'string')
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: 'Invalid file type or no file uploaded',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'error', type: 'string')
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Room not found',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'error', type: 'string')
                    ]
                )
            )
        ]
    )]
    public function uploadImage(string $id, Request $request): JsonResponse
    {
        try {
            $uuid = Uuid::fromString($id);
        } catch (Exception $e) {
            return new JsonResponse([
                'code' => 400,
                'message' => 'Invalid UUID format'
            ], 400);
        }

        $room = $this->entityManager->getRepository(Room::class)->find($uuid);
        if (!$room) {
            return new JsonResponse([
                'code' => 404,
                'message' => 'Room not found'
            ], 404);
        }

        $file = $request->files->get('file');
        if (!$file) {
            return new JsonResponse([
                'code' => 400,
                'message' => 'No file uploaded'
            ], 400);
        }
        
        $allowedMimeTypes = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'pdf'];
        
        $mimeType = $file->getMimeType();
        $extension = $file->getClientOriginalExtension();

        if (!in_array($mimeType, $allowedMimeTypes) || !in_array(strtolower($extension), $allowedExtensions)) {
            return new JsonResponse([
                'code' => 400,
                'message' => 'Invalid file type. Only JPG, PNG, and PDF files are allowed.'
            ], 400);
        }
        
        if ($room->getImagePath()) {
            $oldFilePath = $this->getParameter('kernel.project_dir') . '/public' . $room->getImagePath();
            if (file_exists($oldFilePath)) {
                unlink($oldFilePath);
            }
        }

        $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/rooms';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $fileName = $uuid->toRfc4122() . '_' . time() . '.' . $extension;
        $file->move($uploadDir, $fileName);
        
        $imagePath = '/uploads/rooms/' . $fileName;
        $room->setImagePath($imagePath);
        $this->entityManager->flush();

        return new JsonResponse([
            'code' => 200,
            'message' => 'File uploaded successfully',
            'imagePath' => $imagePath
        ]);
    }

    #[Route('/{id}/image', name: 'rooms_get_image', methods: ['GET'])]
    #[OA\Get(
        path: '/api/rooms/{id}/image',
        summary: 'Get room image or PDF',
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Returns the room image or PDF file',
                content: [
                    new OA\MediaType(mediaType: 'image/jpeg'),
                    new OA\MediaType(mediaType: 'image/png'),
                    new OA\MediaType(mediaType: 'application/pdf')
                ]
            ),
            new OA\Response(
                response: 404,
                description: 'Room or image not found',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'error', type: 'string')
                    ]
                )
            )
        ]
    )]
    public function getImage(string $id): JsonResponse|BinaryFileResponse
    {
        try {
            $uuid = Uuid::fromString($id);
        } catch (Exception $e) {
            return new JsonResponse(['code' => 400, 'message' => 'Invalid UUID format'], 400);
        }

        $room = $this->entityManager->getRepository(Room::class)->find($uuid);
        if (!$room) {
            return new JsonResponse([
                'code' => 404,
                'message' => 'Room not found'
            ], 404);
        }

        if (!$room->getImagePath()) {
            return new JsonResponse([
                'code' => 404,
                'message' => 'No image uploaded for this room'
            ], 404);
        }

        $filePath = $this->getParameter('kernel.project_dir') . '/public' . $room->getImagePath();
        if (!file_exists($filePath)) {
            return new JsonResponse([
                'code' => 404,
                'message' => 'Image file not found'
            ], 404);
        }

        $response = new BinaryFileResponse($filePath);
        $response->headers->set('Content-Type', mime_content_type($filePath));
        
        return $response;
    }

    #[Route('/{id}', name: 'rooms_delete', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/api/rooms/{id}',
        summary: 'Delete a room',
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))
        ],
        responses: [
            new OA\Response(
                response: 204,
                description: 'Room deleted successfully'
            ),
            new OA\Response(
                response: 404,
                description: 'Room not found',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'error', type: 'string')
                    ]
                )
            ),
            new OA\Response(
                response: 409,
                description: 'Room cannot be deleted due to existing bookings',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'error', type: 'string')
                    ]
                )
            )
        ]
    )]
    public function delete(string $id): JsonResponse
    {
        try {
            $uuid = Uuid::fromString($id);
        } catch (Exception $e) {
            return new JsonResponse([
                'code' => 400,
                'message' => 'Invalid UUID format'
            ], 400);
        }

        $room = $this->roomService->getRoomById($uuid);
        if (!$room) {
            return new JsonResponse([
                'code' => 404,
                'message' => 'Room not found'
            ], 404);
        }

        $this->roomService->deleteRoom($room);

        return new JsonResponse(null, 204);
    }

    #[Route('/{id}/favorite', name: 'rooms_toggle_favorite', methods: ['POST'])]
    #[OA\Post(
        path: '/api/rooms/{id}/favorite',
        summary: 'Toggle room as favorite for the current user',
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid'),
                description: 'Room ID'
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Room favorite status toggled',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'isFavorite', type: 'boolean', description: 'Whether the room is now favorited'),
                        new OA\Property(property: 'message', type: 'string')
                    ],
                    example: [
                        'isFavorite' => true,
                        'message' => 'Room added to favorites'
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthorized'
            ),
            new OA\Response(
                response: 404,
                description: 'Room not found'
            )
        ]
    )]
    public function toggleFavorite(string $id): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();
        
        if (!$user) {
            return new JsonResponse([
                'code' => 401,
                'message' => 'Unauthorized'
            ], 401);
        }

        try {
            $uuid = Uuid::fromString($id);
        } catch (Exception $e) {
            return new JsonResponse([
                'code' => 400,
                'message' => 'Invalid UUID format'
            ], 400);
        }

        $room = $this->roomService->getRoomById($uuid);

        if (!$room) {
            return new JsonResponse([
                'code' => 404,
                'message' => 'Room not found'
            ], 404);
        }

        $isFavorite = $this->roomService->toggleFavorite($room, $user);
        $message = $isFavorite ? 'Room added to favorites' : 'Room removed from favorites';

        return new JsonResponse([
            'code' => 200,
            'message' => $message,
            'isFavorite' => $isFavorite
        ]);
    }
}
