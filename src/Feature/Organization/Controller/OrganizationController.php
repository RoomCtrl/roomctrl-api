<?php

declare(strict_types=1);

namespace App\Feature\Organization\Controller;

use App\Feature\Organization\Service\OrganizationServiceInterface;
use App\Feature\Organization\DTO\CreateOrganizationDTO;
use App\Feature\Organization\DTO\OrganizationCreatedResponseDTO;
use App\Feature\Organization\DTO\OrganizationUpdatedResponseDTO;
use App\Feature\Organization\DTO\UpdateOrganizationDTO;
use App\Common\Utility\ValidationErrorFormatter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use OpenApi\Attributes as OA;
use Exception;
use InvalidArgumentException;

#[Route('/organizations')]
#[OA\Tag(name: 'Organizations')]
class OrganizationController extends AbstractController
{
    public function __construct(
        private readonly OrganizationServiceInterface $organizationService,
        private readonly ValidatorInterface $validator
    ) {
    }

    #[Route('', name: 'organizations_list', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    #[OA\Get(
        path: '/api/organizations',
        summary: 'Get all organizations',
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(
                name: 'withUsers',
                description: 'Include users count',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'boolean')
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
    public function list(Request $request): JsonResponse
    {
        $withUsers = filter_var($request->query->get('withUsers', false), FILTER_VALIDATE_BOOLEAN);
        $organizations = $this->organizationService->getAllOrganizations();

        $data = array_map(
            fn($organization) => $this->organizationService->getOrganizationResponse($organization, $withUsers)->toArray(),
            $organizations
        );

        return $this->json($data, Response::HTTP_OK);
    }

    #[Route('/{id}', name: 'organizations_get', requirements: ['id' => '.+'], methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    #[OA\Get(
        path: '/api/organizations/{id}',
        summary: 'Get a single organization by ID',
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'Organization UUID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid')
            ),
            new OA\Parameter(
                name: 'withUsers',
                description: 'Include users count',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'boolean')
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
    public function get(string $id, Request $request): JsonResponse
    {
        try {
            $uuid = Uuid::fromString($id);
        } catch (InvalidArgumentException) {
            return $this->json([
                'code' => Response::HTTP_BAD_REQUEST,
                'message' => 'Invalid UUID format'
            ], Response::HTTP_BAD_REQUEST);
        }

        $organization = $this->organizationService->getOrganizationById($uuid);

        if (!$organization) {
            return $this->json([
                'code' => Response::HTTP_NOT_FOUND,
                'message' => 'Organization not found'
            ], Response::HTTP_NOT_FOUND);
        }

        $withUsers = filter_var($request->query->get('withUsers', false), FILTER_VALIDATE_BOOLEAN);

        return $this->json(
            $this->organizationService->getOrganizationResponse($organization, $withUsers)->toArray(),
            Response::HTTP_OK
        );
    }

    #[Route('', name: 'organizations_create', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    #[OA\Post(
        path: '/api/organizations',
        summary: 'Create a new organization',
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
                        new OA\Property(
                            property: 'violations',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'field', type: 'string', example: 'email'),
                                    new OA\Property(property: 'message', type: 'string', example: 'The email "invalid" is not a valid email.')
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
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            return $this->json([
                'code' => Response::HTTP_BAD_REQUEST,
                'message' => 'Invalid JSON'
            ], Response::HTTP_BAD_REQUEST);
        }

        $dto = CreateOrganizationDTO::fromArray($data);

        $violations = $this->validator->validate($dto);
        if (count($violations) > 0) {
            return $this->json(
                ValidationErrorFormatter::format($violations),
                Response::HTTP_BAD_REQUEST
            );
        }

        try {
            $organization = $this->organizationService->createOrganization($dto);

            $response = new OrganizationCreatedResponseDTO($organization->getId()->toRfc4122());
            return $this->json($response->toArray(), Response::HTTP_CREATED);
        } catch (Exception) {
            return $this->json([
                'code' => Response::HTTP_CONFLICT,
                'message' => 'This REGON or email is already registered.'
            ], Response::HTTP_CONFLICT);
        }
    }

    #[Route('/{id}', name: 'organizations_update', requirements: ['id' => '.+'], methods: ['PUT', 'PATCH'])]
    #[IsGranted('ROLE_ADMIN')]
    #[OA\Put(
        path: '/api/organizations/{id}',
        summary: 'Update an organization',
        security: [['Bearer' => []]],
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
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'Organization UUID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid')
            )
        ],
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
    #[OA\Patch(
        path: '/api/organizations/{id}',
        summary: 'Partially update an organization',
        security: [['Bearer' => []]],
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
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'Organization UUID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid')
            )
        ],
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
                        new OA\Property(
                            property: 'violations',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'field', type: 'string', example: 'email'),
                                    new OA\Property(property: 'message', type: 'string', example: 'The email "invalid" is not a valid email.')
                                ]
                            )
                        )
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
    public function update(string $id, Request $request): JsonResponse
    {
        try {
            $uuid = Uuid::fromString($id);
        } catch (InvalidArgumentException) {
            return $this->json([
                'code' => Response::HTTP_BAD_REQUEST,
                'message' => 'Invalid UUID format'
            ], Response::HTTP_BAD_REQUEST);
        }

        $organization = $this->organizationService->getOrganizationById($uuid);

        if (!$organization) {
            return $this->json([
                'code' => Response::HTTP_NOT_FOUND,
                'message' => 'Organization not found'
            ], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            return $this->json([
                'code' => Response::HTTP_BAD_REQUEST,
                'message' => 'Invalid JSON'
            ], Response::HTTP_BAD_REQUEST);
        }

        $dto = UpdateOrganizationDTO::fromArray($data);

        $violations = $this->validator->validate($dto);
        if (count($violations) > 0) {
            return $this->json(
                ValidationErrorFormatter::format($violations),
                Response::HTTP_BAD_REQUEST
            );
        }

        try {
            $this->organizationService->updateOrganization($organization, $dto);

            $response = new OrganizationUpdatedResponseDTO();
            return $this->json($response->toArray(), Response::HTTP_OK);
        } catch (Exception) {
            return $this->json([
                'code' => Response::HTTP_CONFLICT,
                'message' => 'This REGON or email is already registered.'
            ], Response::HTTP_CONFLICT);
        }
    }

    #[Route('/{id}', name: 'organizations_delete', requirements: ['id' => '.+'], methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN')]
    #[OA\Delete(
        path: '/api/organizations/{id}',
        description: 'Deletes an organization only if it has no assigned users. All associated bookings will be cancelled and kept in history.',
        summary: 'Delete an organization',
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'Organization UUID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid')
            )
        ],
        responses: [
            new OA\Response(
                response: 204,
                description: 'Organization deleted successfully (all associated bookings have been cancelled)'
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
                description: 'Organization not found',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 404),
                        new OA\Property(property: 'message', type: 'string', example: 'Organization not found')
                    ]
                )
            ),
            new OA\Response(
                response: 409,
                description: 'Conflict - Organization has assigned users',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 409),
                        new OA\Property(property: 'message', type: 'string', example: 'Cannot delete organization with assigned users. Please remove or reassign users first.')
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
                response: 500,
                description: 'Server error during deletion',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 500),
                        new OA\Property(property: 'message', type: 'string', example: 'Failed to delete organization: ...')
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
    public function delete(string $id): JsonResponse
    {
        try {
            $uuid = Uuid::fromString($id);
        } catch (InvalidArgumentException) {
            return $this->json([
                'code' => Response::HTTP_BAD_REQUEST,
                'message' => 'Invalid UUID format'
            ], Response::HTTP_BAD_REQUEST);
        }

        $organization = $this->organizationService->getOrganizationById($uuid);

        if (!$organization) {
            return $this->json([
                'code' => Response::HTTP_NOT_FOUND,
                'message' => 'Organization not found'
            ], Response::HTTP_NOT_FOUND);
        }

        $result = $this->organizationService->deleteOrganization($organization);

        if (!$result->isSuccess()) {
            return $this->json($result->toArray(), $result->getCode());
        }

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
