<?php

declare(strict_types=1);

namespace App\Feature\Issue\Controller;

use App\Common\Utility\ValidationErrorFormatter;
use App\Feature\Issue\DTO\CreateIssueDTO;
use App\Feature\Issue\DTO\CreateNoteDTO;
use App\Feature\Issue\DTO\UpdateIssueDTO;
use App\Feature\Issue\Repository\RoomIssueRepository;
use App\Feature\Issue\Service\IssueService;
use App\Feature\User\Entity\User;
use InvalidArgumentException;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/issues')]
#[OA\Tag(name: 'Issues')]
class IssueController extends AbstractController
{
    public function __construct(
        private readonly IssueService $issueService,
        private readonly RoomIssueRepository $issueRepository,
        private readonly ValidatorInterface $validator
    ) {
    }

    #[Route('', name: 'issues_list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/issues',
        summary: 'Get all issues for the authenticated user\'s organization',
        description: 'Returns a list of all issues within the authenticated user\'s organization. Optionally filter by status (pending, in_progress, closed). Only issues belonging to the user\'s organization are visible.',
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(
                name: 'status',
                description: 'Filter issues by status: pending (awaiting review), in_progress (being worked on), or closed (resolved)',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['pending', 'in_progress', 'closed']),
                example: 'pending'
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of all issues for the organization',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'id', type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000', description: 'Unique issue identifier'),
                            new OA\Property(property: 'roomId', type: 'string', format: 'uuid', example: '9d6c9c2f-8b3a-4c5e-9a1b-2c3d4e5f6a7b', description: 'Room where issue was reported'),
                            new OA\Property(property: 'roomName', type: 'string', example: 'Sala 101', description: 'Name of the room'),
                            new OA\Property(property: 'reporterId', type: 'string', format: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000', description: 'User who reported the issue'),
                            new OA\Property(property: 'reporterName', type: 'string', example: 'Jan Kowalski', description: 'Full name of the reporter'),
                            new OA\Property(property: 'category', type: 'string', enum: ['equipment', 'infrastructure', 'furniture'], example: 'equipment', description: 'Issue category'),
                            new OA\Property(property: 'description', type: 'string', example: 'Projektor nie działa - brak obrazu', description: 'Detailed issue description'),
                            new OA\Property(property: 'status', type: 'string', enum: ['pending', 'in_progress', 'closed'], example: 'pending', description: 'Current issue status'),
                            new OA\Property(property: 'priority', type: 'string', enum: ['low', 'medium', 'high', 'critical'], example: 'high', description: 'Issue priority level'),
                            new OA\Property(property: 'reportedAt', type: 'string', format: 'date-time', example: '2025-12-28T10:30:00+00:00', description: 'When the issue was reported'),
                            new OA\Property(property: 'closedAt', type: 'string', format: 'date-time', example: '2025-12-28T15:45:00+00:00', nullable: true, description: 'When the issue was closed (null if not closed)')
                        ]
                    )
                )
            ),
            new OA\Response(
                response: 400,
                description: 'Invalid request - bad status parameter',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 400),
                        new OA\Property(property: 'message', type: 'string', example: 'Invalid status parameter')
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthorized - JWT token missing or invalid',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 401),
                        new OA\Property(property: 'message', type: 'string', example: 'JWT Token not found')
                    ]
                )
            )
        ]
    )]
    public function list(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $status = $request->query->get('status');

        if ($status && !in_array($status, ['pending', 'in_progress', 'closed'])) {
            return $this->json([
                'code' => 400,
                'message' => 'Invalid status parameter'
            ], 400);
        }

        $issues = $this->issueService->getAllIssues($user->getOrganization(), $status);

        return $this->json($issues);
    }

    #[Route('/my', name: 'issues_my', methods: ['GET'])]
    #[OA\Get(
        path: '/api/issues/my',
        summary: 'Get issues reported by the authenticated user',
        description: 'Returns a list of issues that were reported by the currently authenticated user. Optionally filter by status.',
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(
                name: 'status',
                description: 'Filter issues by status: pending, in_progress, or closed',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['pending', 'in_progress', 'closed']),
                example: 'in_progress'
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of issues reported by the current user',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'id', type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000'),
                            new OA\Property(property: 'roomId', type: 'string', format: 'uuid', example: '9d6c9c2f-8b3a-4c5e-9a1b-2c3d4e5f6a7b'),
                            new OA\Property(property: 'roomName', type: 'string', example: 'Sala 101'),
                            new OA\Property(property: 'reporterId', type: 'string', format: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000'),
                            new OA\Property(property: 'reporterName', type: 'string', example: 'Jan Kowalski'),
                            new OA\Property(property: 'category', type: 'string', enum: ['equipment', 'infrastructure', 'furniture'], example: 'equipment'),
                            new OA\Property(property: 'description', type: 'string', example: 'Projektor nie działa - brak obrazu'),
                            new OA\Property(property: 'status', type: 'string', enum: ['pending', 'in_progress', 'closed'], example: 'pending'),
                            new OA\Property(property: 'priority', type: 'string', enum: ['low', 'medium', 'high', 'critical'], example: 'high'),
                            new OA\Property(property: 'reportedAt', type: 'string', format: 'date-time', example: '2025-12-28T10:30:00+00:00'),
                            new OA\Property(property: 'closedAt', type: 'string', format: 'date-time', example: '2025-12-28T15:45:00+00:00', nullable: true)
                        ]
                    )
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthorized - JWT token missing or invalid',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 401),
                        new OA\Property(property: 'message', type: 'string', example: 'JWT Token not found')
                    ]
                )
            )
        ]
    )]
    public function myIssues(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $status = $request->query->get('status');

        $issues = $this->issueService->getMyIssues($user, $status);

        return $this->json($issues);
    }

    #[Route('/count', name: 'issues_count', methods: ['GET'])]
    #[OA\Get(
        path: '/api/issues/count',
        summary: 'Get issue statistics for current user',
        description: 'Returns the total count and breakdown by status (pending, in_progress, closed) of all issues reported by the authenticated user.',
        security: [['Bearer' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Returns issue count statistics for the current user',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'count', type: 'integer', description: 'Total number of user issues', example: 8),
                        new OA\Property(property: 'pending', type: 'integer', description: 'Number of pending issues', example: 3),
                        new OA\Property(property: 'in_progress', type: 'integer', description: 'Number of issues in progress', example: 4),
                        new OA\Property(property: 'closed', type: 'integer', description: 'Number of closed issues', example: 1)
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
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user) {
            return $this->json([
                'code' => 401,
                'message' => 'Unauthorized'
            ], 401);
        }

        $response = $this->issueService->getIssueCounts($user);

        return $this->json($response->toArray());
    }

    #[Route('/organization/count', name: 'issues_organization_count', methods: ['GET'])]
    #[OA\Get(
        path: '/api/issues/organization/count',
        summary: 'Get issue statistics for user\'s organization',
        description: 'Returns the total count and breakdown by status (pending, in_progress, closed) of all issues within the authenticated user\'s organization.',
        security: [['Bearer' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Returns issue count statistics for the organization',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'count', type: 'integer', description: 'Total number of organization issues', example: 45),
                        new OA\Property(property: 'pending', type: 'integer', description: 'Number of pending issues', example: 15),
                        new OA\Property(property: 'in_progress', type: 'integer', description: 'Number of issues in progress', example: 20),
                        new OA\Property(property: 'closed', type: 'integer', description: 'Number of closed issues', example: 10)
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
    public function getOrganizationCount(): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user) {
            return $this->json([
                'code' => 401,
                'message' => 'Unauthorized'
            ], 401);
        }

        $response = $this->issueService->getIssueCountsByOrganization($user->getOrganization());

        return $this->json($response->toArray());
    }

    #[Route('/{id}', name: 'issues_get', methods: ['GET'])]
    #[OA\Get(
        path: '/api/issues/{id}',
        summary: 'Get detailed information about a specific issue',
        description: 'Returns complete issue details including room information, reporter details, status history, and service notes. Only accessible for issues within the user\'s organization.',
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'Issue UUID identifier',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid'),
                example: '550e8400-e29b-41d4-a716-446655440000'
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Complete issue details with history and notes',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000', description: 'Issue unique identifier'),
                        new OA\Property(property: 'roomId', type: 'string', format: 'uuid', example: '9d6c9c2f-8b3a-4c5e-9a1b-2c3d4e5f6a7b', description: 'Associated room ID'),
                        new OA\Property(property: 'roomName', type: 'string', example: 'Sala 101', description: 'Name of the room'),
                        new OA\Property(property: 'reporterId', type: 'string', format: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000', description: 'Reporter user ID'),
                        new OA\Property(property: 'reporterName', type: 'string', example: 'Jan Kowalski', description: 'Full name of the reporter'),
                        new OA\Property(property: 'category', type: 'string', enum: ['equipment', 'infrastructure', 'furniture'], example: 'equipment', description: 'Issue category'),
                        new OA\Property(property: 'description', type: 'string', example: 'Projektor nie działa - brak obrazu', description: 'Detailed issue description'),
                        new OA\Property(property: 'status', type: 'string', enum: ['pending', 'in_progress', 'closed'], example: 'in_progress', description: 'Current issue status'),
                        new OA\Property(property: 'priority', type: 'string', enum: ['low', 'medium', 'high', 'critical'], example: 'high', description: 'Issue priority level'),
                        new OA\Property(property: 'reportedAt', type: 'string', format: 'date-time', example: '2025-12-28T10:30:00+00:00', description: 'When the issue was reported'),
                        new OA\Property(property: 'closedAt', type: 'string', format: 'date-time', example: '2025-12-28T15:45:00+00:00', nullable: true, description: 'When the issue was closed (null if not closed)'),
                        new OA\Property(
                            property: 'notes',
                            description: 'Service notes and updates on the issue',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'id', type: 'string', format: 'uuid', example: 'a1b2c3d4-e5f6-7890-abcd-ef1234567890', description: 'Note ID'),
                                    new OA\Property(property: 'content', type: 'string', example: 'Sprawdzono przewód zasilający - jest prawidłowy', description: 'Note content'),
                                    new OA\Property(property: 'authorId', type: 'string', format: 'uuid', example: '123e4567-e89b-12d3-a456-426614174001', description: 'Author user ID'),
                                    new OA\Property(property: 'authorName', type: 'string', example: 'Adam Nowak', description: 'Full name of the note author'),
                                    new OA\Property(property: 'createdAt', type: 'string', format: 'date-time', example: '2025-12-28T12:15:00+00:00', description: 'When the note was created')
                                ]
                            )
                        ),
                        new OA\Property(
                            property: 'history',
                            description: 'Complete history of status changes and actions on this issue',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'id', type: 'string', format: 'uuid', example: 'f1e2d3c4-b5a6-7890-1234-567890abcdef', description: 'History entry ID'),
                                    new OA\Property(property: 'action', type: 'string', example: 'status_changed', description: 'Action type (created, status_changed, note_added, closed)'),
                                    new OA\Property(property: 'description', type: 'string', example: 'Status changed to in_progress', description: 'Description of the action'),
                                    new OA\Property(property: 'userId', type: 'string', format: 'uuid', example: '123e4567-e89b-12d3-a456-426614174001', description: 'User who performed the action'),
                                    new OA\Property(property: 'userName', type: 'string', example: 'Admin User', description: 'Full name of the user'),
                                    new OA\Property(property: 'createdAt', type: 'string', format: 'date-time', example: '2025-12-28T11:00:00+00:00', description: 'When the action occurred')
                                ]
                            )
                        )
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: 'Invalid UUID format',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 400),
                        new OA\Property(property: 'message', type: 'string', example: 'Invalid UUID format')
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Issue not found',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 404),
                        new OA\Property(property: 'message', type: 'string', example: 'Issue not found')
                    ]
                )
            ),
            new OA\Response(
                response: 403,
                description: 'Forbidden - issue belongs to different organization',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 403),
                        new OA\Property(property: 'message', type: 'string', example: 'Access denied')
                    ]
                )
            )
        ]
    )]
    public function get(string $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        try {
            $uuid = Uuid::fromString($id);
        } catch (\Exception $e) {
            return $this->json([
                'code' => 400,
                'message' => 'Invalid UUID format'
            ], 400);
        }

        $issueDTO = $this->issueService->getIssueById($uuid, true);

        if (!$issueDTO) {
            return $this->json([
                'code' => 404,
                'message' => 'Issue not found'
            ], 404);
        }

        return $this->json($issueDTO->toArray());
    }

    #[Route('', name: 'issues_create', methods: ['POST'])]
    #[OA\Post(
        path: '/api/issues',
        summary: 'Create a new issue report',
        description: 'Report a new issue for a room. The issue will be assigned to the authenticated user as the reporter and will start in pending status. Room must exist and belong to the user\'s organization.',
        security: [['Bearer' => []]],
        requestBody: new OA\RequestBody(
            description: 'Issue details to create',
            required: true,
            content: new OA\JsonContent(
                required: ['roomId', 'category', 'description'],
                properties: [
                    new OA\Property(
                        property: 'roomId',
                        type: 'string',
                        format: 'uuid',
                        example: '9d6c9c2f-8b3a-4c5e-9a1b-2c3d4e5f6a7b',
                        description: 'UUID of the room where the issue occurred'
                    ),
                    new OA\Property(
                        property: 'category',
                        type: 'string',
                        enum: ['equipment', 'infrastructure', 'furniture'],
                        example: 'equipment',
                        description: 'Issue category: equipment (devices), infrastructure (building), or furniture'
                    ),
                    new OA\Property(
                        property: 'description',
                        type: 'string',
                        example: 'Projektor nie działa - brak obrazu po włączeniu zasilania',
                        description: 'Detailed description of the issue'
                    ),
                    new OA\Property(
                        property: 'priority',
                        type: 'string',
                        enum: ['low', 'medium', 'high', 'critical'],
                        example: 'high',
                        description: 'Priority level (optional, defaults to medium)'
                    )
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Issue created successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 201),
                        new OA\Property(property: 'message', type: 'string', example: 'Issue created successfully'),
                        new OA\Property(property: 'id', type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000', description: 'UUID of the created issue')
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: 'Validation error or invalid data',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 400),
                        new OA\Property(property: 'message', type: 'string', example: 'Validation failed'),
                        new OA\Property(
                            property: 'violations',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'field', type: 'string', example: 'description'),
                                    new OA\Property(property: 'message', type: 'string', example: 'Description cannot be blank.')
                                ]
                            )
                        )
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthorized - JWT token missing or invalid',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 401),
                        new OA\Property(property: 'message', type: 'string', example: 'JWT Token not found')
                    ]
                )
            )
        ]
    )]
    public function create(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json([
                'code' => 400,
                'message' => 'Invalid JSON'
            ], 400);
        }

        $dto = CreateIssueDTO::fromArray($data);
        $errors = $this->validator->validate($dto);

        if (count($errors) > 0) {
            return $this->json(
                ValidationErrorFormatter::format($errors),
                400
            );
        }

        try {
            $issue = $this->issueService->createIssue($dto, $user);

            return $this->json([
                'code' => 201,
                'message' => 'Issue created successfully',
                'id' => $issue->getId()->toRfc4122()
            ], 201);
        } catch (InvalidArgumentException $e) {
            return $this->json([
                'code' => 400,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    #[Route('/{id}', name: 'issues_update', methods: ['PATCH'])]
    #[OA\Patch(
        path: '/api/issues/{id}',
        summary: 'Update issue status or priority',
        description: 'Update the status (pending, in_progress, closed) or priority (low, medium, high, critical) of an existing issue. Changes are tracked in the issue history. Only issues from the user\'s organization can be updated.',
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'Issue UUID identifier',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid'),
                example: '550e8400-e29b-41d4-a716-446655440000'
            )
        ],
        requestBody: new OA\RequestBody(
            description: 'Fields to update (at least one required)',
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(
                        property: 'status',
                        type: 'string',
                        enum: ['pending', 'in_progress', 'closed'],
                        example: 'in_progress',
                        description: 'New status for the issue'
                    ),
                    new OA\Property(
                        property: 'priority',
                        type: 'string',
                        enum: ['low', 'medium', 'high', 'critical'],
                        example: 'critical',
                        description: 'New priority level for the issue'
                    )
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Issue updated successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 200),
                        new OA\Property(property: 'message', type: 'string', example: 'Issue updated successfully')
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: 'Invalid data or validation error',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 400),
                        new OA\Property(property: 'message', type: 'string', example: 'Validation failed'),
                        new OA\Property(
                            property: 'violations',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'field', type: 'string', example: 'status'),
                                    new OA\Property(property: 'message', type: 'string', example: 'Invalid status value.')
                                ]
                            )
                        )
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Issue not found',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 404),
                        new OA\Property(property: 'message', type: 'string', example: 'Issue not found')
                    ]
                )
            ),
            new OA\Response(
                response: 403,
                description: 'Access denied - Issue belongs to different organization',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 403),
                        new OA\Property(property: 'message', type: 'string', example: 'Access denied')
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthorized - JWT token missing or invalid',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 401),
                        new OA\Property(property: 'message', type: 'string', example: 'JWT Token not found')
                    ]
                )
            )
        ]
    )]
    public function update(string $id, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        try {
            $uuid = Uuid::fromString($id);
        } catch (\Exception $e) {
            return $this->json([
                'code' => 400,
                'message' => 'Invalid UUID format'
            ], 400);
        }

        $issueDTO = $this->issueService->getIssueById($uuid, false);

        if (!$issueDTO) {
            return $this->json([
                'code' => 404,
                'message' => 'Issue not found'
            ], 404);
        }

        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json([
                'code' => 400,
                'message' => 'Invalid JSON'
            ], 400);
        }

        $dto = UpdateIssueDTO::fromArray($data);
        $errors = $this->validator->validate($dto);

        if (count($errors) > 0) {
            return $this->json(
                ValidationErrorFormatter::format($errors),
                400
            );
        }

        $issue = $this->issueRepository->findByUuid($uuid);
        $this->issueService->updateIssue($issue, $dto, $user);

        return $this->json([
            'code' => 200,
            'message' => 'Issue updated successfully'
        ]);
    }

    #[Route('/{id}/notes', name: 'issues_add_note', methods: ['POST'])]
    #[OA\Post(
        path: '/api/issues/{id}/notes',
        summary: 'Add a service note to an issue',
        description: 'Add a new service note to an existing issue. Notes are used to document progress, actions taken, or other relevant information. The note will be attributed to the authenticated user and timestamped.',
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'Issue UUID identifier',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid'),
                example: '550e8400-e29b-41d4-a716-446655440000'
            )
        ],
        requestBody: new OA\RequestBody(
            description: 'Note content to add',
            required: true,
            content: new OA\JsonContent(
                required: ['content'],
                properties: [
                    new OA\Property(
                        property: 'content',
                        type: 'string',
                        example: 'Sprawdzono przewód zasilający - jest prawidłowy. Zamówiono nową lampę projektora.',
                        description: 'Content of the service note'
                    )
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Note added successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 201),
                        new OA\Property(property: 'message', type: 'string', example: 'Note added successfully'),
                        new OA\Property(property: 'id', type: 'string', format: 'uuid', example: 'a1b2c3d4-e5f6-7890-abcd-ef1234567890', description: 'UUID of the created note')
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: 'Invalid data or validation error',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 400),
                        new OA\Property(property: 'message', type: 'string', example: 'Validation failed'),
                        new OA\Property(
                            property: 'violations',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'field', type: 'string', example: 'content'),
                                    new OA\Property(property: 'message', type: 'string', example: 'Content cannot be blank.')
                                ]
                            )
                        )
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Issue not found',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 404),
                        new OA\Property(property: 'message', type: 'string', example: 'Issue not found')
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthorized - JWT token missing or invalid',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 401),
                        new OA\Property(property: 'message', type: 'string', example: 'JWT Token not found')
                    ]
                )
            )
        ]
    )]
    public function addNote(string $id, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        try {
            $uuid = Uuid::fromString($id);
        } catch (\Exception $e) {
            return $this->json([
                'code' => 400,
                'message' => 'Invalid UUID format'
            ], 400);
        }

        $issueDTO = $this->issueService->getIssueById($uuid, false);

        if (!$issueDTO) {
            return $this->json([
                'code' => 404,
                'message' => 'Issue not found'
            ], 404);
        }

        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json([
                'code' => 400,
                'message' => 'Invalid JSON'
            ], 400);
        }

        $dto = CreateNoteDTO::fromArray($data);
        $errors = $this->validator->validate($dto);

        if (count($errors) > 0) {
            return $this->json(
                ValidationErrorFormatter::format($errors),
                400
            );
        }

        $note = $this->issueService->addNote(
            $this->issueRepository->findByUuid($uuid),
            $dto,
            $user
        );

        return $this->json([
            'code' => 201,
            'message' => 'Note added successfully',
            'id' => $note->getId()->toRfc4122()
        ], 201);
    }

    #[Route('/{id}', name: 'issues_delete', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/api/issues/{id}',
        summary: 'Delete an issue permanently',
        description: 'Permanently delete an issue from the system. This action cannot be undone. All associated notes and history will also be deleted. Only issues from the user\'s organization can be deleted.',
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'Issue UUID identifier',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid'),
                example: '550e8400-e29b-41d4-a716-446655440000'
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Issue deleted successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 200),
                        new OA\Property(property: 'message', type: 'string', example: 'Issue deleted successfully')
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: 'Invalid UUID format',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 400),
                        new OA\Property(property: 'message', type: 'string', example: 'Invalid UUID format')
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Issue not found',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 404),
                        new OA\Property(property: 'message', type: 'string', example: 'Issue not found')
                    ]
                )
            ),
            new OA\Response(
                response: 403,
                description: 'Access denied - Issue belongs to different organization',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 403),
                        new OA\Property(property: 'message', type: 'string', example: 'Access denied')
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthorized - JWT token missing or invalid',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 401),
                        new OA\Property(property: 'message', type: 'string', example: 'JWT Token not found')
                    ]
                )
            )
        ]
    )]
    public function delete(string $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        try {
            $uuid = Uuid::fromString($id);
        } catch (\Exception $e) {
            return $this->json([
                'code' => 400,
                'message' => 'Invalid UUID format'
            ], 400);
        }

        $issueDTO = $this->issueService->getIssueById($uuid, false);

        if (!$issueDTO) {
            return $this->json([
                'code' => 404,
                'message' => 'Issue not found'
            ], 404);
        }

        $issue = $this->issueRepository->findByUuid($uuid);
        $this->issueService->deleteIssue($issue);

        return $this->json([
            'code' => 200,
            'message' => 'Issue deleted successfully'
        ]);
    }
}
