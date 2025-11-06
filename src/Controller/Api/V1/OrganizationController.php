<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Service\OrganizationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Uid\Uuid;
use OpenApi\Attributes as OA;

#[Route('/organizations')]
#[OA\Tag(name: 'Organizations')]
class OrganizationController extends AbstractController
{
    private OrganizationService $organizationService;

    public function __construct(OrganizationService $organizationService)
    {
        $this->organizationService = $organizationService;
    }

    #[Route('', name: 'organizations_list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/v1/organizations',
        summary: 'Get all organizations',
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(
                name: 'withUsers',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'boolean'),
                description: 'Include users count'
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of all organizations',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                            new OA\Property(property: 'regon', type: 'string'),
                            new OA\Property(property: 'name', type: 'string'),
                            new OA\Property(property: 'email', type: 'string'),
                            new OA\Property(property: 'usersCount', type: 'integer')
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
            )
        ]
    )]
    public function list(Request $request): JsonResponse
    {
        $withUsers = filter_var($request->query->get('withUsers', false), FILTER_VALIDATE_BOOLEAN);
        $organizations = $this->organizationService->getAllOrganizations();

        $data = array_map(function ($organization) use ($withUsers) {
            return $this->organizationService->serializeOrganization($organization, $withUsers);
        }, $organizations);

        return $this->json($data, 200);
    }

    #[Route('/{id}', name: 'organizations_get', methods: ['GET'], requirements: ['id' => '.+'])]
    #[OA\Get(
        path: '/api/v1/organizations/{id}',
        summary: 'Get single organization by ID',
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid'),
                description: 'Organization UUID'
            ),
            new OA\Parameter(
                name: 'withUsers',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'boolean'),
                description: 'Include users count'
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Organization details',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                        new OA\Property(property: 'regon', type: 'string'),
                        new OA\Property(property: 'name', type: 'string'),
                        new OA\Property(property: 'email', type: 'string'),
                        new OA\Property(property: 'usersCount', type: 'integer')
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Organization not found',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 404),
                        new OA\Property(property: 'message', type: 'string', example: 'Organization not found')
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
    public function get(string $id, Request $request): JsonResponse
    {
        try {
            $uuid = Uuid::fromString($id);
        } catch (\InvalidArgumentException $e) {
            return $this->json([
                'code' => 400,
                'message' => 'Invalid UUID format'
            ], 400);
        }

        $organization = $this->organizationService->getOrganizationById($uuid);

        if (!$organization) {
            return $this->json([
                'code' => 404,
                'message' => 'Organization not found'
            ], 404);
        }

        $withUsers = filter_var($request->query->get('withUsers', false), FILTER_VALIDATE_BOOLEAN);

        return $this->json($this->organizationService->serializeOrganization($organization, $withUsers), 200);
    }

    #[Route('', name: 'organizations_create', methods: ['POST'])]
    #[OA\Post(
        path: '/api/v1/organizations',
        summary: 'Create new organization',
        security: [['Bearer' => []]],
        requestBody: new OA\RequestBody(
            description: 'Organization data',
            required: true,
            content: new OA\JsonContent(
                required: ['regon', 'name', 'email'],
                properties: [
                    new OA\Property(property: 'regon', type: 'string', example: '123456789'),
                    new OA\Property(property: 'name', type: 'string', example: 'Example Organization Ltd.'),
                    new OA\Property(property: 'email', type: 'string', example: 'contact@organization.com')
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Organization created successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 201),
                        new OA\Property(property: 'message', type: 'string', example: 'Organization created successfully'),
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
                        new OA\Property(property: 'errors', type: 'array', items: new OA\Items(type: 'string'))
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
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json([
                'code' => 400,
                'message' => 'Invalid JSON'
            ], 400);
        }

        $result = $this->organizationService->createOrganization($data);

        if (!$result['success']) {
            return $this->json([
                'code' => $result['code'],
                'message' => $result['message'],
                'errors' => $result['errors'] ?? null
            ], $result['code']);
        }

        return $this->json([
            'code' => $result['code'],
            'message' => $result['message'],
            'id' => $result['id']
        ], $result['code']);
    }

    #[Route('/{id}', name: 'organizations_update', methods: ['PUT', 'PATCH'], requirements: ['id' => '.+'])]
    #[OA\Put(
        path: '/api/v1/organizations/{id}',
        summary: 'Update organization',
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid'),
                description: 'Organization UUID'
            )
        ],
        requestBody: new OA\RequestBody(
            description: 'Organization data to update',
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'regon', type: 'string', example: '123456789'),
                    new OA\Property(property: 'name', type: 'string', example: 'Updated Organization Ltd.'),
                    new OA\Property(property: 'email', type: 'string', example: 'updated@organization.com')
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Organization updated successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 200),
                        new OA\Property(property: 'message', type: 'string', example: 'Organization updated successfully')
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
                        new OA\Property(property: 'errors', type: 'array', items: new OA\Items(type: 'string'))
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Organization not found',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 404),
                        new OA\Property(property: 'message', type: 'string', example: 'Organization not found')
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
        path: '/api/v1/organizations/{id}',
        summary: 'Partially update organization',
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid'),
                description: 'Organization UUID'
            )
        ],
        requestBody: new OA\RequestBody(
            description: 'Organization data to update (partial)',
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'regon', type: 'string', example: '123456789'),
                    new OA\Property(property: 'name', type: 'string', example: 'Updated Organization Ltd.'),
                    new OA\Property(property: 'email', type: 'string', example: 'updated@organization.com')
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Organization updated successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 200),
                        new OA\Property(property: 'message', type: 'string', example: 'Organization updated successfully')
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
                        new OA\Property(property: 'errors', type: 'array', items: new OA\Items(type: 'string'))
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Organization not found',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 404),
                        new OA\Property(property: 'message', type: 'string', example: 'Organization not found')
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
    public function update(string $id, Request $request): JsonResponse
    {
        try {
            $uuid = Uuid::fromString($id);
        } catch (\InvalidArgumentException $e) {
            return $this->json([
                'code' => 400,
                'message' => 'Invalid UUID format'
            ], 400);
        }

        $organization = $this->organizationService->getOrganizationById($uuid);

        if (!$organization) {
            return $this->json([
                'code' => 404,
                'message' => 'Organization not found'
            ], 404);
        }

        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json([
                'code' => 400,
                'message' => 'Invalid JSON'
            ], 400);
        }

        $result = $this->organizationService->updateOrganization($organization, $data);

        if (!$result['success']) {
            return $this->json([
                'code' => $result['code'],
                'message' => $result['message'],
                'errors' => $result['errors'] ?? null
            ], $result['code']);
        }

        return $this->json([
            'code' => $result['code'],
            'message' => $result['message']
        ], $result['code']);
    }

    #[Route('/{id}', name: 'organizations_delete', methods: ['DELETE'], requirements: ['id' => '.+'])]
    #[OA\Delete(
        path: '/api/v1/organizations/{id}',
        summary: 'Delete organization',
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid'),
                description: 'Organization UUID'
            )
        ],
        responses: [
            new OA\Response(
                response: 204,
                description: 'Organization deleted successfully'
            ),
            new OA\Response(
                response: 404,
                description: 'Organization not found',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 404),
                        new OA\Property(property: 'message', type: 'string', example: 'Organization not found')
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
    public function delete(string $id): JsonResponse
    {
        try {
            $uuid = Uuid::fromString($id);
        } catch (\InvalidArgumentException $e) {
            return $this->json([
                'code' => 400,
                'message' => 'Invalid UUID format'
            ], 400);
        }

        $organization = $this->organizationService->getOrganizationById($uuid);

        if (!$organization) {
            return $this->json([
                'code' => 404,
                'message' => 'Organization not found'
            ], 404);
        }

        $result = $this->organizationService->deleteOrganization($organization);

        if (!$result['success']) {
            return $this->json([
                'code' => $result['code'],
                'message' => $result['message']
            ], $result['code']);
        }

        return new JsonResponse(null, 204);
    }
}
