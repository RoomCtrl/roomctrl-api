<?php

declare(strict_types=1);

namespace App\Feature\Room\Controller;

use App\Feature\Room\Entity\Room;
use App\Feature\Room\Entity\RoomStatus;
use App\Feature\Room\Entity\Equipment;
use App\Feature\Booking\Entity\Booking;
use App\Feature\Organization\Entity\Organization;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Uid\Uuid;
use OpenApi\Attributes as OA;

#[Route('/rooms')]
#[OA\Tag(name: 'Rooms')]
class RoomController extends AbstractController
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

        $rooms = $this->entityManager->getRepository(Room::class)->findAll();
        
        // Filter by status if provided
        if ($status) {
            $rooms = array_filter($rooms, function (Room $room) use ($status) {
                return $room->getRoomStatus() && $room->getRoomStatus()->getStatus() === $status;
            });
        }

        $data = array_map(function (Room $room) use ($withBookings) {
            return $this->serializeRoom($room, $withBookings);
        }, $rooms);

        return new JsonResponse(array_values($data));
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
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Invalid UUID format'], 400);
        }

        $room = $this->entityManager->getRepository(Room::class)->find($uuid);

        if (!$room) {
            return new JsonResponse(['error' => 'Room not found'], 404);
        }

        return new JsonResponse($this->serializeRoom($room, true));
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

        if (!isset($data['organizationId'])) {
            return new JsonResponse(['error' => 'Organization ID is required'], 400);
        }

        try {
            $orgUuid = Uuid::fromString($data['organizationId']);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Invalid organization UUID format'], 400);
        }

        $organization = $this->entityManager->getRepository(Organization::class)->find($orgUuid);
        if (!$organization) {
            return new JsonResponse(['error' => 'Organization not found'], 404);
        }

        $room = new Room();
        $room->setRoomName($data['roomName']);
        $room->setCapacity((int)$data['capacity']);
        $room->setSize((float)$data['size']);
        $room->setLocation($data['location']);
        $room->setAccess($data['access']);
        $room->setOrganization($organization);

        // Create RoomStatus
        $roomStatus = new RoomStatus();
        $roomStatus->setStatus($data['status'] ?? 'available');
        $roomStatus->setRoom($room);
        $room->setRoomStatus($roomStatus);

        if (isset($data['description'])) {
            $room->setDescription($data['description']);
        }
        if (isset($data['lighting'])) {
            $room->setLighting($data['lighting']);
        }
        if (isset($data['airConditioning'])) {
            $room->setAirConditioning($data['airConditioning']);
        }

        if (isset($data['equipment']) && is_array($data['equipment'])) {
            foreach ($data['equipment'] as $equipData) {
                $equipment = new Equipment();
                $equipment->setName($equipData['name']);
                $equipment->setCategory($equipData['category']);
                $equipment->setQuantity($equipData['quantity'] ?? 1);
                $room->addEquipment($equipment);
            }
        }

        $errors = $this->validator->validate($room);
        if (count($errors) > 0) {
            return new JsonResponse(['errors' => (string) $errors], 400);
        }

        $this->entityManager->persist($room);
        $this->entityManager->flush();

        return new JsonResponse($this->serializeRoom($room, false), 201);
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
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Invalid UUID format'], 400);
        }

        $room = $this->entityManager->getRepository(Room::class)->find($uuid);
        if (!$room) {
            return new JsonResponse(['error' => 'Room not found'], 404);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['roomName'])) {
            $room->setRoomName($data['roomName']);
        }
        if (isset($data['status'])) {
            if (!$room->getRoomStatus()) {
                $roomStatus = new RoomStatus();
                $roomStatus->setRoom($room);
                $room->setRoomStatus($roomStatus);
            }
            $room->getRoomStatus()->setStatus($data['status']);
        }
        if (isset($data['capacity'])) {
            $room->setCapacity((int)$data['capacity']);
        }
        if (isset($data['size'])) {
            $room->setSize((float)$data['size']);
        }
        if (isset($data['location'])) {
            $room->setLocation($data['location']);
        }
        if (isset($data['access'])) {
            $room->setAccess($data['access']);
        }
        if (isset($data['description'])) {
            $room->setDescription($data['description']);
        }
        if (isset($data['lighting'])) {
            $room->setLighting($data['lighting']);
        }
        if (isset($data['airConditioning'])) {
            $room->setAirConditioning($data['airConditioning']);
        }

        $errors = $this->validator->validate($room);
        if (count($errors) > 0) {
            return new JsonResponse(['errors' => (string) $errors], 400);
        }

        $this->entityManager->flush();

        return new JsonResponse($this->serializeRoom($room, false));
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
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Invalid UUID format'], 400);
        }

        $room = $this->entityManager->getRepository(Room::class)->find($uuid);
        if (!$room) {
            return new JsonResponse(['error' => 'Room not found'], 404);
        }

        $file = $request->files->get('file');
        if (!$file) {
            return new JsonResponse(['error' => 'No file uploaded'], 400);
        }

        // Walidacja typu pliku
        $allowedMimeTypes = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'pdf'];
        
        $mimeType = $file->getMimeType();
        $extension = $file->getClientOriginalExtension();

        if (!in_array($mimeType, $allowedMimeTypes) || !in_array(strtolower($extension), $allowedExtensions)) {
            return new JsonResponse([
                'error' => 'Invalid file type. Only JPG, PNG, and PDF files are allowed.'
            ], 400);
        }

        // Usuń stary plik jeśli istnieje
        if ($room->getImagePath()) {
            $oldFilePath = $this->getParameter('kernel.project_dir') . '/public' . $room->getImagePath();
            if (file_exists($oldFilePath)) {
                unlink($oldFilePath);
            }
        }

        // Zapisz nowy plik
        $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/rooms';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $fileName = $uuid->toRfc4122() . '_' . time() . '.' . $extension;
        $file->move($uploadDir, $fileName);

        // Zapisz ścieżkę w bazie
        $imagePath = '/uploads/rooms/' . $fileName;
        $room->setImagePath($imagePath);
        $this->entityManager->flush();

        return new JsonResponse([
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
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Invalid UUID format'], 400);
        }

        $room = $this->entityManager->getRepository(Room::class)->find($uuid);
        if (!$room) {
            return new JsonResponse(['error' => 'Room not found'], 404);
        }

        if (!$room->getImagePath()) {
            return new JsonResponse(['error' => 'No image uploaded for this room'], 404);
        }

        $filePath = $this->getParameter('kernel.project_dir') . '/public' . $room->getImagePath();
        if (!file_exists($filePath)) {
            return new JsonResponse(['error' => 'Image file not found'], 404);
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
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Invalid UUID format'], 400);
        }

        $room = $this->entityManager->getRepository(Room::class)->find($uuid);
        if (!$room) {
            return new JsonResponse(['error' => 'Room not found'], 404);
        }

        // Anuluj wszystkie aktywne rezerwacje (aktualne i przyszłe)
        $now = new \DateTimeImmutable();
        $activeBookings = $this->entityManager->getRepository(Booking::class)
            ->createQueryBuilder('b')
            ->where('b.room = :room')
            ->andWhere('b.status = :status')
            ->andWhere('b.endedAt >= :now')
            ->setParameter('room', $room)
            ->setParameter('status', 'active')
            ->setParameter('now', $now)
            ->getQuery()
            ->getResult();

        foreach ($activeBookings as $booking) {
            $booking->setStatus('cancelled');
        }

        // Najpierw flush anulowanych rezerwacji
        $this->entityManager->flush();

        // Potem usuń pokój
        $this->entityManager->remove($room);
        $this->entityManager->flush();

        return new JsonResponse(null, 204);
    }

    private function serializeRoom(Room $room, bool $withBookings = false): array
    {
        $data = [
            'roomId' => $room->getId()->toRfc4122(),
            'roomName' => $room->getRoomName(),
            'status' => $room->getRoomStatus() ? $room->getRoomStatus()->getStatus() : 'available',
            'capacity' => $room->getCapacity(),
            'size' => $room->getSize(),
            'location' => $room->getLocation(),
            'access' => $room->getAccess(),
            'description' => $room->getDescription(),
            'lighting' => $room->getLighting(),
            'airConditioning' => $room->getAirConditioning(),
            'imagePath' => $room->getImagePath(),
            'equipment' => array_map(function (Equipment $eq) {
                return [
                    'name' => $eq->getName(),
                    'category' => $eq->getCategory(),
                    'quantity' => $eq->getQuantity()
                ];
            }, $room->getEquipment()->toArray())
        ];

        if ($withBookings) {
            $now = new \DateTimeImmutable();
            $bookings = $this->entityManager->getRepository(Booking::class)->findBy(
                ['room' => $room, 'status' => 'active'],
                ['startedAt' => 'ASC']
            );

            $currentBooking = null;
            $nextBookings = [];

            foreach ($bookings as $booking) {
                if ($booking->getStartedAt() <= $now && $booking->getEndedAt() > $now) {
                    $currentBooking = $this->serializeBooking($booking);
                } elseif ($booking->getStartedAt() > $now) {
                    $nextBookings[] = $this->serializeBooking($booking);
                }
            }

            $data['currentBooking'] = $currentBooking;
            $data['nextBookings'] = $nextBookings;
        }

        return $data;
    }

    private function serializeBooking(Booking $booking): array
    {
        return [
            'id' => $booking->getId()->toRfc4122(),
            'title' => $booking->getTitle(),
            'startedAt' => $booking->getStartedAt()->format('c'),
            'endedAt' => $booking->getEndedAt()->format('c'),
            'participants' => $booking->getParticipants(),
            'isPrivate' => $booking->isPrivate()
        ];
    }
}
