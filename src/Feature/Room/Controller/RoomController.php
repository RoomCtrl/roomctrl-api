<?php

declare(strict_types=1);

namespace App\Feature\Room\Controller;

use App\Feature\Room\DTO\CreateRoomRequest;
use App\Feature\Room\DTO\RecentRoomResponseDTO;
use App\Feature\Room\DTO\UpdateRoomRequest;
use App\Feature\Room\DTO\ImageUploadResponseDTO;
use App\Feature\Room\DTO\ImageDeleteResponseDTO;
use App\Feature\Room\DTO\FavoriteToggleResponseDTO;
use App\Feature\Room\DTO\ImageListResponseDTO;
use App\Feature\Room\Service\RoomServiceInterface;
use App\Feature\Room\Service\FileUploadService;
use App\Feature\User\Entity\User;
use App\Common\Utility\ValidationErrorFormatter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Uid\Uuid;
use OpenApi\Attributes as OA;
use Exception;

#[Route('/rooms')]
#[OA\Tag(name: 'Rooms')]
class RoomController extends AbstractController
{
    public function __construct(
        private readonly RoomServiceInterface $roomService,
        private readonly FileUploadService $fileUploadService,
        private readonly ValidatorInterface $validator
    ) {
    }

    #[Route('', name: 'rooms_list', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[OA\Get(
        path: '/api/rooms',
        summary: 'Get all rooms',
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(
                name: 'status',
                description: 'Filter by room status',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['available', 'out_of_use'])
            ),
            new OA\Parameter(
                name: 'withBookings',
                description: 'Include current and next bookings',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'boolean')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Returns list of rooms',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'roomId', type: 'string', format: 'uuid'),
                            new OA\Property(property: 'roomName', type: 'string'),
                            new OA\Property(property: 'status', type: 'string', enum: ['available', 'out_of_use']),
                            new OA\Property(property: 'capacity', type: 'integer'),
                            new OA\Property(property: 'size', description: 'Size in square meters', type: 'number', format: 'float'),
                            new OA\Property(property: 'location', type: 'string'),
                            new OA\Property(property: 'access', type: 'string'),
                            new OA\Property(property: 'description', type: 'string'),
                            new OA\Property(property: 'lighting', type: 'string'),
                            new OA\Property(
                                property: 'airConditioning',
                                properties: [
                                    new OA\Property(property: 'min', type: 'number'),
                                    new OA\Property(property: 'max', type: 'number')
                                ],
                                type: 'object',
                                nullable: true
                            ),
                            new OA\Property(
                                property: 'imagePaths',
                                description: 'Array of paths to uploaded images or PDF files',
                                type: 'array',
                                items: new OA\Items(type: 'string'),
                                nullable: true
                            ),
                            new OA\Property(
                                property: 'equipment',
                                type: 'array',
                                items: new OA\Items(
                                    properties: [
                                        new OA\Property(property: 'name', type: 'string'),
                                        new OA\Property(property: 'category', type: 'string', enum: ['video', 'audio', 'computer', 'accessory', 'furniture']),
                                        new OA\Property(property: 'quantity', type: 'integer')
                                    ],
                                    type: 'object'
                                )
                            ),
                            new OA\Property(
                                property: 'currentBooking',
                                description: 'Present only when withBookings=true',
                                properties: [
                                    new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                                    new OA\Property(property: 'title', type: 'string'),
                                    new OA\Property(property: 'startedAt', type: 'string', format: 'date-time'),
                                    new OA\Property(property: 'endedAt', type: 'string', format: 'date-time'),
                                    new OA\Property(property: 'participants', type: 'integer'),
                                    new OA\Property(property: 'isPrivate', type: 'boolean')
                                ],
                                type: 'object',
                                nullable: true
                            ),
                            new OA\Property(
                                property: 'nextBookings',
                                description: 'Present only when withBookings=true',
                                type: 'array',
                                items: new OA\Items(
                                    properties: [
                                        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                                        new OA\Property(property: 'title', type: 'string'),
                                        new OA\Property(property: 'startedAt', type: 'string', format: 'date-time'),
                                        new OA\Property(property: 'endedAt', type: 'string', format: 'date-time'),
                                        new OA\Property(property: 'participants', type: 'integer'),
                                        new OA\Property(property: 'isPrivate', type: 'boolean')
                                    ],
                                    type: 'object'
                                )
                            )
                        ],
                        type: 'object'
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
    public function list(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user) {
            return new JsonResponse([
                'code' => Response::HTTP_UNAUTHORIZED,
                'message' => 'Unauthorized'
            ], Response::HTTP_UNAUTHORIZED);
        }

        $status = $request->query->get('status');
        $withBookings = $request->query->getBoolean('withBookings', false);

        $rooms = $this->roomService->getAllRooms($status, $user->getOrganization());
        $responseDTOs = $this->roomService->getRoomResponses($rooms, $withBookings);
        $data = array_map(fn($dto) => $dto->toArray(), $responseDTOs);

        return new JsonResponse(array_values($data), Response::HTTP_OK);
    }

    #[Route('/statistics/most_used', name: 'rooms_statistics_most_used', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    #[OA\Get(
        path: '/api/rooms/statistics/most_used',
        description: 'Returns top 5 rooms with the highest number of bookings in the user\'s organization.',
        summary: 'Get most frequently used rooms',
        security: [['Bearer' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of most used rooms with booking statistics',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'roomId', type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000'),
                            new OA\Property(property: 'roomName', type: 'string', example: 'Sala nr 208'),
                            new OA\Property(property: 'count', description: 'Total number of bookings', type: 'integer', example: 45),
                            new OA\Property(property: 'percentage', description: 'Percentage of total bookings', type: 'number', format: 'float', example: 25.0),
                            new OA\Property(property: 'weeklyBookings', description: 'Number of bookings in the last week', type: 'integer', example: 12),
                            new OA\Property(property: 'monthlyBookings', description: 'Number of bookings in the last month', type: 'integer', example: 38)
                        ]
                    )
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
            ),
            new OA\Response(
                response: 403,
                description: 'Forbidden - Requires ROLE_USER',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 403),
                        new OA\Property(property: 'message', type: 'string', example: 'Access denied. You do not have sufficient permissions to access this resource.')
                    ]
                )
            )
        ]
    )]
    public function getMostUsedRooms(): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user || !$user->getOrganization()) {
            return new JsonResponse([
                'code' => Response::HTTP_UNAUTHORIZED,
                'message' => 'Unauthorized'
            ], Response::HTTP_UNAUTHORIZED);
        }

        $stats = $this->roomService->getMostUsedRooms($user->getOrganization());

        return new JsonResponse($stats, Response::HTTP_OK);
    }

    #[Route('/statistics/least_used', name: 'rooms_statistics_least_used', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    #[OA\Get(
        path: '/api/rooms/statistics/least_used',
        description: 'Returns top 5 rooms with the lowest number of bookings in the user\'s organization.',
        summary: 'Get least frequently used rooms',
        security: [['Bearer' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of least used rooms with booking statistics',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'roomId', type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000'),
                            new OA\Property(property: 'roomName', type: 'string', example: 'Sala nr 101'),
                            new OA\Property(property: 'count', description: 'Total number of bookings', type: 'integer', example: 3),
                            new OA\Property(property: 'percentage', description: 'Percentage of total bookings', type: 'number', format: 'float', example: 2.0),
                            new OA\Property(property: 'weeklyBookings', description: 'Number of bookings in the last week', type: 'integer', example: 1),
                            new OA\Property(property: 'monthlyBookings', description: 'Number of bookings in the last month', type: 'integer', example: 2)
                        ]
                    )
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
    public function getLeastUsedRooms(): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user || !$user->getOrganization()) {
            return new JsonResponse([
                'code' => Response::HTTP_UNAUTHORIZED,
                'message' => 'Unauthorized'
            ], Response::HTTP_UNAUTHORIZED);
        }

        $stats = $this->roomService->getLeastUsedRooms($user->getOrganization());

        return new JsonResponse($stats, Response::HTTP_OK);
    }

    #[Route('/statistics/most_issues', name: 'rooms_statistics_most_issues', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    #[OA\Get(
        path: '/api/rooms/statistics/most_issues',
        description: 'Returns top 5 rooms with the highest number of reported issues in the user\'s organization.',
        summary: 'Get rooms with most issues',
        security: [['Bearer' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of rooms with most issues',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'roomId', type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000'),
                            new OA\Property(property: 'roomName', type: 'string', example: 'Sala nr 208'),
                            new OA\Property(property: 'issueCount', description: 'Number of issues', type: 'integer', example: 12),
                            new OA\Property(property: 'priority', description: 'Priority level based on issue count', type: 'string', enum: ['low', 'medium', 'high'], example: 'high')
                        ]
                    )
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
    public function getRoomsWithMostIssues(): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user || !$user->getOrganization()) {
            return new JsonResponse([
                'code' => Response::HTTP_UNAUTHORIZED,
                'message' => 'Unauthorized'
            ], Response::HTTP_UNAUTHORIZED);
        }

        $stats = $this->roomService->getRoomsWithMostIssues($user->getOrganization());

        return new JsonResponse($stats, Response::HTTP_OK);
    }

    #[Route('/favorites', name: 'rooms_favorites_list', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
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
                        properties: [
                            new OA\Property(property: 'roomId', type: 'string', format: 'uuid'),
                            new OA\Property(property: 'roomName', type: 'string'),
                            new OA\Property(property: 'status', type: 'string', enum: ['available', 'out_of_use']),
                            new OA\Property(property: 'capacity', type: 'integer'),
                            new OA\Property(property: 'size', type: 'number', format: 'float'),
                            new OA\Property(property: 'location', type: 'string'),
                            new OA\Property(property: 'access', type: 'string'),
                            new OA\Property(property: 'description', type: 'string'),
                            new OA\Property(property: 'lighting', type: 'string'),
                            new OA\Property(
                                property: 'airConditioning',
                                properties: [
                                    new OA\Property(property: 'min', type: 'number'),
                                    new OA\Property(property: 'max', type: 'number')
                                ],
                                type: 'object',
                                nullable: true
                            ),
                            new OA\Property(
                                property: 'imagePaths',
                                type: 'array',
                                items: new OA\Items(type: 'string'),
                                nullable: true
                            ),
                            new OA\Property(
                                property: 'equipment',
                                type: 'array',
                                items: new OA\Items(
                                    properties: [
                                        new OA\Property(property: 'name', type: 'string'),
                                        new OA\Property(property: 'category', type: 'string'),
                                        new OA\Property(property: 'quantity', type: 'integer')
                                    ],
                                    type: 'object'
                                )
                            )
                        ],
                        type: 'object'
                    )
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
    public function getFavorites(): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user) {
            return $this->json([
                'code' => Response::HTTP_UNAUTHORIZED,
                'message' => 'Unauthorized'
            ], Response::HTTP_UNAUTHORIZED);
        }

        $favoriteRooms = $this->roomService->getFavoriteRooms($user);
        $responseDTOs = $this->roomService->getRoomResponses($favoriteRooms, false);
        $data = array_map(fn($dto) => $dto->toArray(), $responseDTOs);

        return new JsonResponse(array_values($data), Response::HTTP_OK);
    }

    #[Route('/recent', name: 'rooms_recent', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
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
                        properties: [
                            new OA\Property(property: 'roomId', type: 'string', format: 'uuid'),
                            new OA\Property(property: 'roomName', type: 'string'),
                            new OA\Property(property: 'status', type: 'string', enum: ['available', 'out_of_use']),
                            new OA\Property(property: 'capacity', type: 'integer'),
                            new OA\Property(property: 'size', type: 'number', format: 'float'),
                            new OA\Property(property: 'location', type: 'string'),
                            new OA\Property(property: 'access', type: 'string'),
                            new OA\Property(property: 'description', type: 'string'),
                            new OA\Property(property: 'lighting', type: 'string'),
                            new OA\Property(
                                property: 'airConditioning',
                                properties: [
                                    new OA\Property(property: 'min', type: 'number'),
                                    new OA\Property(property: 'max', type: 'number')
                                ],
                                type: 'object',
                                nullable: true
                            ),
                            new OA\Property(
                                property: 'imagePaths',
                                type: 'array',
                                items: new OA\Items(type: 'string'),
                                nullable: true
                            ),
                            new OA\Property(
                                property: 'equipment',
                                type: 'array',
                                items: new OA\Items(
                                    properties: [
                                        new OA\Property(property: 'name', type: 'string'),
                                        new OA\Property(property: 'category', type: 'string'),
                                        new OA\Property(property: 'quantity', type: 'integer')
                                    ],
                                    type: 'object'
                                )
                            ),
                            new OA\Property(
                                property: 'lastBooking',
                                properties: [
                                    new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                                    new OA\Property(property: 'title', type: 'string'),
                                    new OA\Property(property: 'startedAt', type: 'string', format: 'date-time'),
                                    new OA\Property(property: 'endedAt', type: 'string', format: 'date-time')
                                ],
                                type: 'object',
                                nullable: true
                            )
                        ],
                        type: 'object'
                    )
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
    public function getRecentRooms(): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user) {
            return new JsonResponse([
                'code' => Response::HTTP_UNAUTHORIZED,
                'message' => 'Unauthorized'
            ], Response::HTTP_UNAUTHORIZED);
        }

        $recentRooms = $this->roomService->getRecentlyBookedRooms($user, 3);

        $data = array_map(function ($item) {
            return RecentRoomResponseDTO::fromEntityWithBooking($item['room'], $item['lastBooking'])->toArray();
        }, $recentRooms);

        return new JsonResponse($data, Response::HTTP_OK);
    }

    #[Route('/{id}', name: 'rooms_get', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
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
                    properties: [
                        new OA\Property(property: 'roomId', type: 'string', format: 'uuid'),
                        new OA\Property(property: 'roomName', type: 'string'),
                        new OA\Property(property: 'status', type: 'string', enum: ['available', 'out_of_use']),
                        new OA\Property(property: 'capacity', type: 'integer'),
                        new OA\Property(property: 'size', type: 'number', format: 'float'),
                        new OA\Property(property: 'location', type: 'string'),
                        new OA\Property(property: 'access', type: 'string'),
                        new OA\Property(property: 'description', type: 'string'),
                        new OA\Property(property: 'lighting', type: 'string'),
                        new OA\Property(
                            property: 'airConditioning',
                            properties: [
                                new OA\Property(property: 'min', type: 'number'),
                                new OA\Property(property: 'max', type: 'number')
                            ],
                            type: 'object',
                            nullable: true
                        ),
                        new OA\Property(
                            property: 'imagePaths',
                            description: 'Array of paths to uploaded images or PDF files',
                            type: 'array',
                            items: new OA\Items(type: 'string'),
                            nullable: true
                        ),
                        new OA\Property(
                            property: 'equipment',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'name', type: 'string'),
                                    new OA\Property(property: 'category', type: 'string'),
                                    new OA\Property(property: 'quantity', type: 'integer')
                                ],
                                type: 'object'
                            )
                        ),
                        new OA\Property(
                            property: 'currentBooking',
                            properties: [
                                new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                                new OA\Property(property: 'title', type: 'string'),
                                new OA\Property(property: 'startedAt', type: 'string', format: 'date-time'),
                                new OA\Property(property: 'endedAt', type: 'string', format: 'date-time'),
                                new OA\Property(property: 'participants', type: 'integer'),
                                new OA\Property(property: 'isPrivate', type: 'boolean')
                            ],
                            type: 'object',
                            nullable: true
                        ),
                        new OA\Property(
                            property: 'nextBookings',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                                    new OA\Property(property: 'title', type: 'string'),
                                    new OA\Property(property: 'startedAt', type: 'string', format: 'date-time'),
                                    new OA\Property(property: 'endedAt', type: 'string', format: 'date-time'),
                                    new OA\Property(property: 'participants', type: 'integer'),
                                    new OA\Property(property: 'isPrivate', type: 'boolean')
                                ],
                                type: 'object'
                            )
                        )
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(
                response: 400,
                description: 'Bad request - Invalid UUID format',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 400),
                        new OA\Property(property: 'message', type: 'string', example: 'Invalid UUID format')
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
                        new OA\Property(property: 'message', type: 'string', example: 'Unauthorized')
                    ]
                )
            ),
            new OA\Response(
                response: 403,
                description: 'Access denied - Room belongs to different organization',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 403),
                        new OA\Property(property: 'message', type: 'string', example: 'Access denied to this room')
                    ]
                )
            )
        ]
    )]
    public function get(string $id): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user) {
            return new JsonResponse([
                'code' => Response::HTTP_UNAUTHORIZED,
                'message' => 'Unauthorized'
            ], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $uuid = Uuid::fromString($id);
        } catch (Exception) {
            return new JsonResponse([
                'code' => Response::HTTP_BAD_REQUEST,
                'message' => 'Invalid UUID format'
            ], Response::HTTP_BAD_REQUEST);
        }

        $room = $this->roomService->getRoomById($uuid);

        if (!$room) {
            return new JsonResponse([
                'code' => Response::HTTP_NOT_FOUND,
                'message' => 'Room not found'
            ], Response::HTTP_NOT_FOUND);
        }

        if (!$this->roomService->canUserAccessRoom($room, $user)) {
            return new JsonResponse([
                'code' => Response::HTTP_FORBIDDEN,
                'message' => 'Access denied to this room'
            ], Response::HTTP_FORBIDDEN);
        }

        $responseDTO = $this->roomService->getRoomResponse($room, true);
        return new JsonResponse($responseDTO->toArray(), Response::HTTP_OK);
    }

    #[Route('', name: 'rooms_create', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
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
                    new OA\Property(property: 'status', type: 'string', enum: ['available', 'out_of_use']),
                    new OA\Property(property: 'capacity', type: 'integer', maximum: 200, minimum: 1),
                    new OA\Property(property: 'size', description: 'Size in square meters', type: 'number', format: 'float'),
                    new OA\Property(property: 'location', type: 'string'),
                    new OA\Property(property: 'access', description: 'Access method: magnetic card, pin, key, biometric, etc.', type: 'string'),
                    new OA\Property(property: 'description', type: 'string'),
                    new OA\Property(property: 'lighting', type: 'string'),
                    new OA\Property(
                        property: 'airConditioning',
                        description: 'Air conditioning temperature range',
                        properties: [
                            new OA\Property(property: 'min', type: 'number'),
                            new OA\Property(property: 'max', type: 'number')
                        ],
                        type: 'object',
                        example: ['min' => 18, 'max' => 24]
                    ),
                    new OA\Property(property: 'organizationId', type: 'string', format: 'uuid'),
                    new OA\Property(
                        property: 'equipment',
                        type: 'array',
                        items: new OA\Items(
                            properties: [
                                new OA\Property(property: 'name', type: 'string'),
                                new OA\Property(property: 'category', type: 'string', enum: ['video', 'audio', 'computer', 'accessory', 'furniture']),
                                new OA\Property(property: 'quantity', type: 'integer')
                            ],
                            type: 'object'
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
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 201),
                        new OA\Property(property: 'message', type: 'string', example: 'Room created successfully'),
                        new OA\Property(property: 'id', type: 'string', format: 'uuid')
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: 'Validation error',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 400),
                        new OA\Property(property: 'message', type: 'string', example: 'Validation failed'),
                        new OA\Property(
                            property: 'violations',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'field', type: 'string', example: 'roomName'),
                                    new OA\Property(property: 'message', type: 'string', example: 'Room name is required')
                                ]
                            )
                        )
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
            ),
            new OA\Response(
                response: 403,
                description: 'Forbidden - Requires ROLE_ADMIN or trying to create room for different organization',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 403),
                        new OA\Property(property: 'message', type: 'string', example: 'Access denied or you can only create rooms for your organization')
                    ]
                )
            )
        ]
    )]
    public function create(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user) {
            return new JsonResponse([
                'code' => Response::HTTP_UNAUTHORIZED,
                'message' => 'Unauthorized'
            ], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            return $this->json([
                'code' => Response::HTTP_BAD_REQUEST,
                'message' => 'Invalid JSON'
            ], Response::HTTP_BAD_REQUEST);
        }

        $createRequest = CreateRoomRequest::fromArray($data);

        $errors = $this->validator->validate($createRequest);
        if (count($errors) > 0) {
            return $this->json(
                ValidationErrorFormatter::format($errors),
                Response::HTTP_BAD_REQUEST
            );
        }

        try {
            $orgUuid = Uuid::fromString($createRequest->organizationId);
        } catch (Exception) {
            return $this->json([
                'code' => Response::HTTP_BAD_REQUEST,
                'message' => 'Invalid organization UUID format'
            ], Response::HTTP_BAD_REQUEST);
        }

        if ($orgUuid->toRfc4122() !== $user->getOrganization()->getId()->toRfc4122()) {
            return $this->json([
                'code' => Response::HTTP_FORBIDDEN,
                'message' => 'You can only create rooms for your organization'
            ], Response::HTTP_FORBIDDEN);
        }

        $room = $this->roomService->createRoom($createRequest, $user->getOrganization());

        return $this->json([
            'code' => Response::HTTP_CREATED,
            'message' => 'Room created successfully',
            'id' => $room->getId()->toRfc4122()
        ], Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'rooms_update', methods: ['PUT', 'PATCH'])]
    #[IsGranted('ROLE_ADMIN')]
    #[OA\Put(
        path: '/api/rooms/{id}',
        summary: 'Update a room',
        security: [['Bearer' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'roomName', type: 'string'),
                    new OA\Property(property: 'status', type: 'string', enum: ['available', 'out_of_use']),
                    new OA\Property(property: 'capacity', type: 'integer'),
                    new OA\Property(property: 'size', type: 'number', format: 'float'),
                    new OA\Property(property: 'location', type: 'string'),
                    new OA\Property(property: 'access', type: 'string'),
                    new OA\Property(property: 'description', type: 'string'),
                    new OA\Property(property: 'lighting', type: 'string'),
                    new OA\Property(
                        property: 'airConditioning',
                        properties: [
                            new OA\Property(property: 'min', type: 'number'),
                            new OA\Property(property: 'max', type: 'number')
                        ],
                        type: 'object',
                        nullable: true
                    ),
                    new OA\Property(
                        property: 'equipment',
                        type: 'array',
                        items: new OA\Items(
                            properties: [
                                new OA\Property(property: 'name', type: 'string'),
                                new OA\Property(property: 'category', type: 'string', enum: ['video', 'audio', 'computer', 'accessory', 'furniture']),
                                new OA\Property(property: 'quantity', type: 'integer')
                            ],
                            type: 'object'
                        ),
                        nullable: true
                    )
                ],
                type: 'object'
            )
        ),
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Room updated successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 200),
                        new OA\Property(property: 'message', type: 'string', example: 'Room updated successfully')
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: 'Bad request - Invalid UUID format, Invalid JSON, or Validation error',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 400),
                        new OA\Property(property: 'message', type: 'string', example: 'Validation failed'),
                        new OA\Property(
                            property: 'violations',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'field', type: 'string', example: 'capacity'),
                                    new OA\Property(property: 'message', type: 'string', example: 'Capacity must be between 1 and 200.')
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
                        new OA\Property(property: 'message', type: 'string', example: 'Unauthorized')
                    ]
                )
            ),
            new OA\Response(
                response: 403,
                description: 'Forbidden - Requires ROLE_ADMIN or room belongs to different organization',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 403),
                        new OA\Property(property: 'message', type: 'string', example: 'Access denied')
                    ]
                )
            )
        ]
    )]
    #[OA\Patch(
        path: '/api/rooms/{id}',
        summary: 'Partially update a room',
        security: [['Bearer' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'roomName', type: 'string'),
                    new OA\Property(property: 'status', type: 'string', enum: ['available', 'out_of_use']),
                    new OA\Property(property: 'capacity', type: 'integer'),
                    new OA\Property(property: 'size', type: 'number', format: 'float'),
                    new OA\Property(property: 'location', type: 'string'),
                    new OA\Property(property: 'access', type: 'string'),
                    new OA\Property(property: 'description', type: 'string'),
                    new OA\Property(property: 'lighting', type: 'string'),
                    new OA\Property(
                        property: 'airConditioning',
                        properties: [
                            new OA\Property(property: 'min', type: 'number'),
                            new OA\Property(property: 'max', type: 'number')
                        ],
                        type: 'object',
                        nullable: true
                    ),
                    new OA\Property(
                        property: 'equipment',
                        type: 'array',
                        items: new OA\Items(
                            properties: [
                                new OA\Property(property: 'name', type: 'string'),
                                new OA\Property(property: 'category', type: 'string', enum: ['video', 'audio', 'computer', 'accessory', 'furniture']),
                                new OA\Property(property: 'quantity', type: 'integer')
                            ],
                            type: 'object'
                        ),
                        nullable: true
                    )
                ],
                type: 'object'
            )
        ),
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Room updated successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 200),
                        new OA\Property(property: 'message', type: 'string', example: 'Room updated successfully')
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: 'Bad request - Invalid UUID format, Invalid JSON, or Validation error',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 400),
                        new OA\Property(property: 'message', type: 'string', example: 'Validation failed'),
                        new OA\Property(
                            property: 'violations',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'field', type: 'string', example: 'capacity'),
                                    new OA\Property(property: 'message', type: 'string', example: 'Capacity must be between 1 and 200.')
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
                        new OA\Property(property: 'message', type: 'string', example: 'Unauthorized')
                    ]
                )
            ),
            new OA\Response(
                response: 403,
                description: 'Forbidden - Requires ROLE_ADMIN or room belongs to different organization',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 403),
                        new OA\Property(property: 'message', type: 'string', example: 'Access denied')
                    ]
                )
            )
        ]
    )]
    public function update(string $id, Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user) {
            return new JsonResponse([
                'code' => Response::HTTP_UNAUTHORIZED,
                'message' => 'Unauthorized'
            ], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $uuid = Uuid::fromString($id);
        } catch (Exception $e) {
            return $this->json([
                'code' => Response::HTTP_BAD_REQUEST,
                'message' => 'Invalid UUID format'
            ], Response::HTTP_BAD_REQUEST);
        }

        $room = $this->roomService->getRoomById($uuid);
        if (!$room) {
            return $this->json([
                'code' => Response::HTTP_NOT_FOUND,
                'message' => 'Room not found'
            ], Response::HTTP_NOT_FOUND);
        }

        if (!$this->roomService->canUserAccessRoom($room, $user)) {
            return $this->json([
                'code' => Response::HTTP_FORBIDDEN,
                'message' => 'Access denied to this room'
            ], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            return $this->json([
                'code' => Response::HTTP_BAD_REQUEST,
                'message' => 'Invalid JSON'
            ], Response::HTTP_BAD_REQUEST);
        }

        $updateRequest = UpdateRoomRequest::fromArray($data);

        $errors = $this->validator->validate($updateRequest);
        if (count($errors) > 0) {
            return $this->json(
                ValidationErrorFormatter::format($errors),
                Response::HTTP_BAD_REQUEST
            );
        }

        $this->roomService->updateRoom($room, $updateRequest);

        return $this->json([
            'code' => Response::HTTP_OK,
            'message' => 'Room updated successfully'
        ], Response::HTTP_OK);
    }

    #[Route('/{id}/upload', name: 'rooms_upload_image', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    #[OA\Post(
        path: '/api/rooms/{id}/upload',
        summary: 'Upload one or multiple images/PDFs for a room',
        security: [['Bearer' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['files[]'],
                    properties: [
                        new OA\Property(
                            property: 'files[]',
                            type: 'array',
                            items: new OA\Items(type: 'string', format: 'binary'),
                            description: 'One or multiple image files (JPG, PNG) or PDF documents'
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
                description: 'Files uploaded successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 200),
                        new OA\Property(property: 'message', type: 'string', example: 'Files uploaded successfully'),
                        new OA\Property(
                            property: 'imagePaths',
                            type: 'array',
                            items: new OA\Items(type: 'string'),
                            example: ['/uploads/rooms/01234567-89ab-cdef-0123-456789abcdef_1234567890.jpg', '/uploads/rooms/01234567-89ab-cdef-0123-456789abcdef_1234567891.png']
                        )
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: 'Invalid file type or no files uploaded',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 400),
                        new OA\Property(property: 'message', type: 'string', example: 'Invalid file type. Only JPG, PNG, and PDF files are allowed.')
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
                        new OA\Property(property: 'message', type: 'string', example: 'Unauthorized')
                    ]
                )
            ),
            new OA\Response(
                response: 403,
                description: 'Forbidden - Requires ROLE_ADMIN or room belongs to different organization',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 403),
                        new OA\Property(property: 'message', type: 'string', example: 'Access denied')
                    ]
                )
            )
        ]
    )]
    public function uploadImage(string $id, Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user) {
            return new JsonResponse([
                'code' => Response::HTTP_UNAUTHORIZED,
                'message' => 'Unauthorized'
            ], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $uuid = Uuid::fromString($id);
        } catch (Exception $e) {
            return new JsonResponse([
                'code' => Response::HTTP_BAD_REQUEST,
                'message' => 'Invalid UUID format'
            ], Response::HTTP_BAD_REQUEST);
        }

        $room = $this->roomService->getRoomById($uuid);
        if (!$room) {
            return new JsonResponse([
                'code' => Response::HTTP_NOT_FOUND,
                'message' => 'Room not found'
            ], Response::HTTP_NOT_FOUND);
        }

        if (!$this->roomService->canUserAccessRoom($room, $user)) {
            return new JsonResponse([
                'code' => Response::HTTP_FORBIDDEN,
                'message' => 'Access denied to this room'
            ], Response::HTTP_FORBIDDEN);
        }

        $files = $request->files->get('files');
        if (!$files) {
            return new JsonResponse([
                'code' => Response::HTTP_BAD_REQUEST,
                'message' => 'No files uploaded'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Convert single file to array for uniform processing
        if (!is_array($files)) {
            $files = [$files];
        }

        try {
            $uploadedPaths = $this->fileUploadService->uploadFiles($files, $uuid->toRfc4122());
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse([
                'code' => Response::HTTP_BAD_REQUEST,
                'message' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }

        if (empty($uploadedPaths)) {
            return new JsonResponse([
                'code' => Response::HTTP_BAD_REQUEST,
                'message' => 'No valid files were uploaded'
            ], Response::HTTP_BAD_REQUEST);
        }

        $this->roomService->addImagePaths($room, $uploadedPaths);

        $responseDTO = new ImageUploadResponseDTO(
            'Files uploaded successfully',
            $uploadedPaths
        );

        return new JsonResponse($responseDTO->toArray(), Response::HTTP_OK);
    }

    #[Route('/{id}/images', name: 'rooms_get_images', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[OA\Get(
        path: '/api/rooms/{id}/images',
        summary: 'Get all room images/PDFs',
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Returns list of room images and PDFs',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 200),
                        new OA\Property(
                            property: 'imagePaths',
                            type: 'array',
                            items: new OA\Items(type: 'string'),
                            example: ['/uploads/rooms/01234567-89ab-cdef-0123-456789abcdef_1234567890.jpg', '/uploads/rooms/01234567-89ab-cdef-0123-456789abcdef_1234567891.png']
                        )
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: 'Bad request - Invalid UUID format',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 400),
                        new OA\Property(property: 'message', type: 'string', example: 'Invalid UUID format')
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
                        new OA\Property(property: 'message', type: 'string', example: 'Unauthorized')
                    ]
                )
            ),
            new OA\Response(
                response: 403,
                description: 'Access denied - Room belongs to different organization',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 403),
                        new OA\Property(property: 'message', type: 'string', example: 'Access denied to this room')
                    ]
                )
            )
        ]
    )]
    public function getImages(string $id): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user) {
            return new JsonResponse([
                'code' => Response::HTTP_UNAUTHORIZED,
                'message' => 'Unauthorized'
            ], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $uuid = Uuid::fromString($id);
        } catch (Exception $e) {
            return new JsonResponse(['code' => Response::HTTP_BAD_REQUEST, 'message' => 'Invalid UUID format'], Response::HTTP_BAD_REQUEST);
        }

        $room = $this->roomService->getRoomById($uuid);
        if (!$room) {
            return new JsonResponse([
                'code' => Response::HTTP_NOT_FOUND,
                'message' => 'Room not found'
            ], Response::HTTP_NOT_FOUND);
        }

        if (!$this->roomService->canUserAccessRoom($room, $user)) {
            return new JsonResponse([
                'code' => Response::HTTP_FORBIDDEN,
                'message' => 'Access denied to this room'
            ], Response::HTTP_FORBIDDEN);
        }

        $imagePaths = $this->roomService->getImagePaths($room);

        $responseDTO = new ImageListResponseDTO($imagePaths);
        return new JsonResponse($responseDTO->toArray(), Response::HTTP_OK);
    }

    #[Route('/{id}/image/{imageIndex}', name: 'rooms_get_image', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[OA\Get(
        path: '/api/rooms/{id}/image/{imageIndex}',
        summary: 'Get a specific room image or PDF by index',
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'imageIndex', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), description: 'Zero-based index of the image')
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Returns the requested room image or PDF file',
                content: [
                    new OA\MediaType(mediaType: 'image/jpeg'),
                    new OA\MediaType(mediaType: 'image/png'),
                    new OA\MediaType(mediaType: 'application/pdf')
                ]
            ),
            new OA\Response(
                response: 400,
                description: 'Bad request - Invalid UUID format',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 400),
                        new OA\Property(property: 'message', type: 'string', example: 'Invalid UUID format')
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Room or image not found',
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
                        new OA\Property(property: 'message', type: 'string', example: 'Unauthorized')
                    ]
                )
            )
        ]
    )]
    public function getImage(string $id, int $imageIndex): JsonResponse|BinaryFileResponse
    {
        try {
            $uuid = Uuid::fromString($id);
        } catch (Exception $e) {
            return new JsonResponse(['code' => Response::HTTP_BAD_REQUEST, 'message' => 'Invalid UUID format'], Response::HTTP_BAD_REQUEST);
        }

        $room = $this->roomService->getRoomById($uuid);
        if (!$room) {
            return new JsonResponse([
                'code' => Response::HTTP_NOT_FOUND,
                'message' => 'Room not found'
            ], Response::HTTP_NOT_FOUND);
        }

        /** @var User|null $user */
        $user = $this->getUser();
        if ($user && !$this->roomService->canUserAccessRoom($room, $user)) {
            return new JsonResponse([
                'code' => Response::HTTP_FORBIDDEN,
                'message' => 'Access denied to this room'
            ], Response::HTTP_FORBIDDEN);
        }

        $imagePaths = $this->roomService->getImagePaths($room);

        if (empty($imagePaths) || !isset($imagePaths[$imageIndex])) {
            return new JsonResponse([
                'code' => Response::HTTP_NOT_FOUND,
                'message' => 'Image not found'
            ], Response::HTTP_NOT_FOUND);
        }

        $filePath = $this->getParameter('kernel.project_dir') . '/public' . $imagePaths[$imageIndex];
        if (!file_exists($filePath)) {
            return new JsonResponse([
                'code' => Response::HTTP_NOT_FOUND,
                'message' => 'Image file not found'
            ], Response::HTTP_NOT_FOUND);
        }

        $response = new BinaryFileResponse($filePath);
        $response->headers->set('Content-Type', mime_content_type($filePath));

        return $response;
    }

    #[Route('/{id}/images', name: 'rooms_delete_all_images', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN')]
    #[OA\Delete(
        path: '/api/rooms/{id}/images',
        summary: 'Delete all images/PDFs for a room',
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'All images deleted successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 200),
                        new OA\Property(property: 'message', type: 'string', example: 'All images deleted successfully'),
                        new OA\Property(property: 'deletedCount', type: 'integer', example: 3)
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: 'Bad request - Invalid UUID format',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 400),
                        new OA\Property(property: 'message', type: 'string', example: 'Invalid UUID format')
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Room not found or no images to delete',
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
                        new OA\Property(property: 'message', type: 'string', example: 'Unauthorized')
                    ]
                )
            ),
            new OA\Response(
                response: 403,
                description: 'Forbidden - Requires ROLE_ADMIN or room belongs to different organization',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 403),
                        new OA\Property(property: 'message', type: 'string', example: 'Access denied')
                    ]
                )
            )
        ]
    )]
    public function deleteAllImages(string $id): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user) {
            return new JsonResponse([
                'code' => Response::HTTP_UNAUTHORIZED,
                'message' => 'Unauthorized'
            ], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $uuid = Uuid::fromString($id);
        } catch (Exception $e) {
            return new JsonResponse([
                'code' => Response::HTTP_BAD_REQUEST,
                'message' => 'Invalid UUID format'
            ], Response::HTTP_BAD_REQUEST);
        }

        $room = $this->roomService->getRoomById($uuid);
        if (!$room) {
            return new JsonResponse([
                'code' => Response::HTTP_NOT_FOUND,
                'message' => 'Room not found'
            ], Response::HTTP_NOT_FOUND);
        }

        if (!$this->roomService->canUserAccessRoom($room, $user)) {
            return new JsonResponse([
                'code' => Response::HTTP_FORBIDDEN,
                'message' => 'Access denied to this room'
            ], Response::HTTP_FORBIDDEN);
        }

        $imagePaths = $this->roomService->getImagePaths($room);
        if (empty($imagePaths)) {
            return new JsonResponse([
                'code' => Response::HTTP_NOT_FOUND,
                'message' => 'No images to delete'
            ], Response::HTTP_NOT_FOUND);
        }

        $deletedCount = $this->fileUploadService->deleteFiles(
            $imagePaths,
            $this->getParameter('kernel.project_dir')
        );
        $this->roomService->clearAllImages($room);

        $responseDTO = new ImageDeleteResponseDTO(
            'All images deleted successfully',
            $deletedCount
        );

        return new JsonResponse($responseDTO->toArray(), Response::HTTP_OK);
    }

    #[Route('/{id}/images/{imageIndex}', name: 'rooms_delete_single_image', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN')]
    #[OA\Delete(
        path: '/api/rooms/{id}/images/{imageIndex}',
        summary: 'Delete a specific image/PDF by index',
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'imageIndex', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), description: 'Index of the image to delete (0-based)')
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Image deleted successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 200),
                        new OA\Property(property: 'message', type: 'string', example: 'Image deleted successfully'),
                        new OA\Property(property: 'deletedPath', type: 'string', example: '/uploads/rooms/01234567-89ab-cdef-0123-456789abcdef_1234567890.jpg')
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: 'Bad request - Invalid UUID format or image index',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 400),
                        new OA\Property(property: 'message', type: 'string', example: 'Invalid UUID format')
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Room or image not found',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 404),
                        new OA\Property(property: 'message', type: 'string', example: 'Image not found')
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
            ),
            new OA\Response(
                response: 403,
                description: 'Forbidden - Requires ROLE_ADMIN or room belongs to different organization',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 403),
                        new OA\Property(property: 'message', type: 'string', example: 'Access denied')
                    ]
                )
            )
        ]
    )]
    public function deleteSingleImage(string $id, int $imageIndex): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user) {
            return new JsonResponse([
                'code' => Response::HTTP_UNAUTHORIZED,
                'message' => 'Unauthorized'
            ], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $uuid = Uuid::fromString($id);
        } catch (Exception $e) {
            return new JsonResponse([
                'code' => Response::HTTP_BAD_REQUEST,
                'message' => 'Invalid UUID format'
            ], Response::HTTP_BAD_REQUEST);
        }

        if ($imageIndex < 0) {
            return new JsonResponse([
                'code' => Response::HTTP_BAD_REQUEST,
                'message' => 'Invalid image index'
            ], Response::HTTP_BAD_REQUEST);
        }

        $room = $this->roomService->getRoomById($uuid);
        if (!$room) {
            return new JsonResponse([
                'code' => Response::HTTP_NOT_FOUND,
                'message' => 'Room not found'
            ], Response::HTTP_NOT_FOUND);
        }

        if (!$this->roomService->canUserAccessRoom($room, $user)) {
            return new JsonResponse([
                'code' => Response::HTTP_FORBIDDEN,
                'message' => 'Access denied to this room'
            ], Response::HTTP_FORBIDDEN);
        }

        $deletedPath = $this->roomService->removeImagePath($room, $imageIndex);
        if (!$deletedPath) {
            return new JsonResponse([
                'code' => Response::HTTP_NOT_FOUND,
                'message' => 'Image not found'
            ], Response::HTTP_NOT_FOUND);
        }

        $this->fileUploadService->deleteFile(
            $deletedPath,
            $this->getParameter('kernel.project_dir')
        );

        $responseDTO = new ImageDeleteResponseDTO(
            'Image deleted successfully',
            null,
            $deletedPath
        );

        return new JsonResponse($responseDTO->toArray(), Response::HTTP_OK);
    }

    #[Route('/{id}', name: 'rooms_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN')]
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
                response: 400,
                description: 'Bad request - Invalid UUID format',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 400),
                        new OA\Property(property: 'message', type: 'string', example: 'Invalid UUID format')
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
                description: 'Room cannot be deleted due to existing bookings',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 409),
                        new OA\Property(property: 'message', type: 'string', example: 'Room cannot be deleted due to existing bookings')
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
            ),
            new OA\Response(
                response: 403,
                description: 'Forbidden - Requires ROLE_ADMIN or room belongs to different organization',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 403),
                        new OA\Property(property: 'message', type: 'string', example: 'Access denied')
                    ]
                )
            )
        ]
    )]
    public function delete(string $id): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user) {
            return new JsonResponse([
                'code' => Response::HTTP_UNAUTHORIZED,
                'message' => 'Unauthorized'
            ], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $uuid = Uuid::fromString($id);
        } catch (Exception $e) {
            return new JsonResponse([
                'code' => Response::HTTP_BAD_REQUEST,
                'message' => 'Invalid UUID format'
            ], Response::HTTP_BAD_REQUEST);
        }

        $room = $this->roomService->getRoomById($uuid);
        if (!$room) {
            return new JsonResponse([
                'code' => Response::HTTP_NOT_FOUND,
                'message' => 'Room not found'
            ], Response::HTTP_NOT_FOUND);
        }

        if (!$this->roomService->canUserAccessRoom($room, $user)) {
            return new JsonResponse([
                'code' => Response::HTTP_FORBIDDEN,
                'message' => 'Access denied to this room'
            ], Response::HTTP_FORBIDDEN);
        }

        $this->roomService->deleteRoom($room);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/{id}/favorite', name: 'rooms_toggle_favorite', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
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
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 200),
                        new OA\Property(property: 'message', type: 'string', example: 'Room added to favorites'),
                        new OA\Property(property: 'isFavorite', type: 'boolean', description: 'Whether the room is now favorited', example: true)
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: 'Bad request - Invalid UUID format',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 400),
                        new OA\Property(property: 'message', type: 'string', example: 'Invalid UUID format')
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
                response: 403,
                description: 'Access denied - Room belongs to different organization',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 403),
                        new OA\Property(property: 'message', type: 'string', example: 'Access denied to this room')
                    ]
                )
            )
        ]
    )]
    public function toggleFavorite(string $id): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user) {
            return new JsonResponse([
                'code' => Response::HTTP_UNAUTHORIZED,
                'message' => 'Unauthorized'
            ], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $uuid = Uuid::fromString($id);
        } catch (Exception $e) {
            return new JsonResponse([
                'code' => Response::HTTP_BAD_REQUEST,
                'message' => 'Invalid UUID format'
            ], Response::HTTP_BAD_REQUEST);
        }

        $room = $this->roomService->getRoomById($uuid);

        if (!$room) {
            return new JsonResponse([
                'code' => Response::HTTP_NOT_FOUND,
                'message' => 'Room not found'
            ], Response::HTTP_NOT_FOUND);
        }

        if (!$this->roomService->canUserAccessRoom($room, $user)) {
            return new JsonResponse([
                'code' => Response::HTTP_FORBIDDEN,
                'message' => 'Access denied to this room'
            ], Response::HTTP_FORBIDDEN);
        }

        $isFavorite = $this->roomService->toggleFavorite($room, $user);
        $message = $isFavorite ? 'Room added to favorites' : 'Room removed from favorites';

        $responseDTO = new FavoriteToggleResponseDTO(
            $message,
            $isFavorite
        );

        return new JsonResponse($responseDTO->toArray(), Response::HTTP_OK);
    }
}
