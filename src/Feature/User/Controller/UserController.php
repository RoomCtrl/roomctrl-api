<?php

declare(strict_types=1);

namespace App\Feature\User\Controller;

use App\Common\Utility\ValidationErrorFormatter;
use App\Feature\User\DTO\CreateUserDTO;
use App\Feature\User\DTO\PasswordResetConfirmDTO;
use App\Feature\User\DTO\PasswordResetRequestDTO;
use App\Feature\User\DTO\UpdateUserDTO;
use App\Feature\User\Entity\User;
use App\Feature\User\Repository\UserRepository;
use App\Feature\User\Service\UserService;
use Exception;
use InvalidArgumentException;
use OpenApi\Attributes as OA;
use Random\RandomException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

#[Route('/users')]
#[OA\Tag(name: 'Users')]
class UserController extends AbstractController
{
    public function __construct(
        private readonly UserService         $userService,
        private readonly UserRepository      $userRepository,
        private readonly ValidatorInterface  $validator
    )
    {
    }

    #[Route('', name: 'users_list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/users',
        summary: 'Get all users',
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(
                name: 'withDetails',
                description: 'Include organization details',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'boolean', default: false)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of all users',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'id', type: 'string', format: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000'),
                            new OA\Property(property: 'username', type: 'string', example: 'john.doe'),
                            new OA\Property(property: 'firstName', type: 'string', example: 'John'),
                            new OA\Property(property: 'lastName', type: 'string', example: 'Doe'),
                            new OA\Property(property: 'email', type: 'string', example: 'john.doe@example.com'),
                            new OA\Property(property: 'phone', type: 'string', example: '+48123456789'),
                            new OA\Property(property: 'roles', type: 'array', items: new OA\Items(type: 'string'), example: ['ROLE_USER']),
                            new OA\Property(property: 'isActive', type: 'boolean', example: true),
                            new OA\Property(
                                property: 'organization',
                                properties: [
                                    new OA\Property(property: 'id', type: 'string', format: 'uuid', example: '987e6543-e21b-12d3-a456-426614174999'),
                                    new OA\Property(property: 'regon', type: 'string', example: '123456789'),
                                    new OA\Property(property: 'name', type: 'string', example: 'Example Organization'),
                                    new OA\Property(property: 'email', type: 'string', example: 'contact@organization.com')
                                ],
                                type: 'object',
                                nullable: true
                            )
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
                        new OA\Property(property: 'message', type: 'string', example: 'JWT Token not found')
                    ]
                )
            )
        ]
    )]
    public function list(Request $request): JsonResponse
    {
        /** @var User|null $currentUser */
        $currentUser = $this->getUser();
        
        if (!$currentUser) {
            return new JsonResponse([
                'code' => 401,
                'message' => 'Unauthorized'
            ], 401);
        }

        $withDetails = filter_var($request->query->get('withDetails', false), FILTER_VALIDATE_BOOLEAN);
        $users = $this->userService->getAllUsers($withDetails, $currentUser->getOrganization());

        return $this->json($users, 200);
    }

    #[Route('/{id}', name: 'users_get', requirements: ['id' => '.+'], methods: ['GET'])]
    #[OA\Get(
        path: '/api/users/{id}',
        summary: 'Get a single user by ID',
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'User UUID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid')
            ),
            new OA\Parameter(
                name: 'withDetails',
                description: 'Include organization details',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'boolean', default: false)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'User details',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'string', format: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000'),
                        new OA\Property(property: 'username', type: 'string', example: 'john.doe'),
                        new OA\Property(property: 'firstName', type: 'string', example: 'John'),
                        new OA\Property(property: 'lastName', type: 'string', example: 'Doe'),
                        new OA\Property(property: 'email', type: 'string', example: 'john.doe@example.com'),
                        new OA\Property(property: 'phone', type: 'string', example: '+48123456789'),
                        new OA\Property(property: 'roles', type: 'array', items: new OA\Items(type: 'string'), example: ['ROLE_USER']),
                        new OA\Property(property: 'isActive', type: 'boolean', example: true),
                        new OA\Property(
                            property: 'organization',
                            properties: [
                                new OA\Property(property: 'id', type: 'string', format: 'uuid', example: '987e6543-e21b-12d3-a456-426614174999'),
                                new OA\Property(property: 'regon', type: 'string', example: '123456789'),
                                new OA\Property(property: 'name', type: 'string', example: 'Example Organization'),
                                new OA\Property(property: 'email', type: 'string', example: 'contact@organization.com')
                            ],
                            type: 'object',
                            nullable: true
                        )
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'User not found',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 404),
                        new OA\Property(property: 'message', type: 'string', example: 'User not found')
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
                description: 'Access denied - User belongs to different organization',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 403),
                        new OA\Property(property: 'message', type: 'string', example: 'Access denied to this user')
                    ]
                )
            )
        ]
    )]
    public function get(string $id, Request $request): JsonResponse
    {
        /** @var User|null $currentUser */
        $currentUser = $this->getUser();
        
        if (!$currentUser) {
            return new JsonResponse([
                'code' => 401,
                'message' => 'Unauthorized'
            ], 401);
        }

        try {
            $uuid = Uuid::fromString($id);
        } catch (InvalidArgumentException) {
            return $this->json([
                'code' => 400,
                'message' => 'Invalid UUID format'
            ], 400);
        }

        $withDetails = filter_var($request->query->get('withDetails', false), FILTER_VALIDATE_BOOLEAN);
        $userDTO = $this->userService->getUserById($uuid, $withDetails);

        if (!$userDTO) {
            return $this->json([
                'code' => 404,
                'message' => 'User not found'
            ], 404);
        }

        $targetUser = $this->userRepository->findByUuid($uuid);
        if ($targetUser && $targetUser->getOrganization()->getId()->toRfc4122() !== $currentUser->getOrganization()->getId()->toRfc4122()) {
            return $this->json([
                'code' => 403,
                'message' => 'Access denied to this user'
            ], 403);
        }

        return $this->json($userDTO->toArray(), 200);
    }

    #[Route('', name: 'users_create', methods: ['POST'])]
    #[OA\Post(
        path: '/api/users',
        summary: 'Create a new user',
        security: [['Bearer' => []]],
        requestBody: new OA\RequestBody(
            description: 'User data',
            required: true,
            content: new OA\JsonContent(
                required: ['username', 'password', 'firstName', 'lastName', 'email', 'phone', 'organizationId'],
                properties: [
                    new OA\Property(property: 'username', type: 'string', example: 'johndoe'),
                    new OA\Property(property: 'password', type: 'string', example: 'SecurePassword123'),
                    new OA\Property(property: 'firstName', type: 'string', example: 'John'),
                    new OA\Property(property: 'lastName', type: 'string', example: 'Doe'),
                    new OA\Property(property: 'email', type: 'string', example: 'john.doe@example.com'),
                    new OA\Property(property: 'phone', type: 'string', example: '+48123456789'),
                    new OA\Property(property: 'roles', type: 'array', items: new OA\Items(type: 'string'), example: ['ROLE_USER']),
                    new OA\Property(property: 'isActive', type: 'boolean', example: true),
                    new OA\Property(property: 'organizationId', type: 'string', format: 'uuid', example: '9d6c9c2f-8b3a-4c5e-9a1b-2c3d4e5f6a7b')
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'User created successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 201),
                        new OA\Property(property: 'message', type: 'string', example: 'User created successfully'),
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
                                    new OA\Property(property: 'field', type: 'string', example: 'username'),
                                    new OA\Property(property: 'message', type: 'string', example: 'Username must be at least 3 characters long.')
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
                response: 409,
                description: 'Conflict - Username, email or phone already exists',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 409),
                        new OA\Property(property: 'message', type: 'string', example: 'This email is already in use.')
                    ]
                )
            ),
            new OA\Response(
                response: 500,
                description: 'Server error',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 500),
                        new OA\Property(property: 'message', type: 'string', example: 'Failed to create user')
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

        $dto = CreateUserDTO::fromArray($data);
        $violations = $this->validator->validate($dto);

        if (count($violations) > 0) {
            return $this->json(
                ValidationErrorFormatter::format($violations),
                400
            );
        }

        try {
            $user = $this->userService->createUser($dto);

            return $this->json([
                'code' => 201,
                'message' => 'User created successfully',
                'id' => $user->getId()->toRfc4122()
            ], 201);
        } catch (InvalidArgumentException $e) {
            if (str_contains($e->getMessage(), 'username') || 
                str_contains($e->getMessage(), 'email') || 
                str_contains($e->getMessage(), 'phone')) {
                return $this->json([
                    'code' => 409,
                    'message' => $e->getMessage()
                ], 409);
            }

            return $this->json([
                'code' => 404,
                'message' => $e->getMessage()
            ], 404);
        } catch (Exception $e) {
            // Obsługa naruszenia unique constraint z bazy danych
            if (str_contains($e->getMessage(), '23505') || str_contains($e->getMessage(), 'duplicate key')) {
                if (str_contains($e->getMessage(), 'username')) {
                    return $this->json([
                        'code' => 409,
                        'message' => 'This username is already taken.'
                    ], 409);
                }
            }

            return $this->json([
                'code' => 500,
                'message' => 'Failed to create user'
            ], 500);
        }
    }

    #[Route('/{id}', name: 'users_update', requirements: ['id' => '.+'], methods: ['PUT', 'PATCH'])]
    #[OA\Put(
        path: '/api/users/{id}',
        summary: 'Update a user',
        security: [['Bearer' => []]],
        requestBody: new OA\RequestBody(
            description: 'User data to update',
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'username', type: 'string', example: 'johndoe'),
                    new OA\Property(property: 'password', type: 'string', example: 'NewSecurePassword123'),
                    new OA\Property(property: 'firstName', type: 'string', example: 'John'),
                    new OA\Property(property: 'lastName', type: 'string', example: 'Doe'),
                    new OA\Property(property: 'email', type: 'string', example: 'john.doe@example.com'),
                    new OA\Property(property: 'phone', type: 'string', example: '+48123456789'),
                    new OA\Property(property: 'roles', type: 'array', items: new OA\Items(type: 'string'), example: ['ROLE_ADMIN']),
                    new OA\Property(property: 'isActive', type: 'boolean', example: true),
                    new OA\Property(property: 'organizationId', type: 'string', format: 'uuid', example: '9d6c9c2f-8b3a-4c5e-9a1b-2c3d4e5f6a7b')
                ]
            )
        ),
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'User UUID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'User updated successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 200),
                        new OA\Property(property: 'message', type: 'string', example: 'User updated successfully')
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
                description: 'User or Organization not found',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 404),
                        new OA\Property(property: 'message', type: 'string', example: 'User not found')
                    ]
                )
            ),
            new OA\Response(
                response: 409,
                description: 'Conflict - Username, email or phone already exists',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 409),
                        new OA\Property(property: 'message', type: 'string', example: 'This email is already in use.')
                    ]
                )
            ),
            new OA\Response(
                response: 500,
                description: 'Server error',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 500),
                        new OA\Property(property: 'message', type: 'string', example: 'Failed to update user')
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
                description: 'Access denied - User belongs to different organization',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 403),
                        new OA\Property(property: 'message', type: 'string', example: 'Access denied to this user')
                    ]
                )
            )
        ]
    )]
    #[OA\Patch(
        path: '/api/users/{id}',
        summary: 'Partially update a user',
        security: [['Bearer' => []]],
        requestBody: new OA\RequestBody(
            description: 'User data to update (partial)',
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'username', type: 'string', example: 'johndoe'),
                    new OA\Property(property: 'password', type: 'string', example: 'NewSecurePassword123'),
                    new OA\Property(property: 'firstName', type: 'string', example: 'John'),
                    new OA\Property(property: 'lastName', type: 'string', example: 'Doe'),
                    new OA\Property(property: 'email', type: 'string', example: 'john.doe@example.com'),
                    new OA\Property(property: 'phone', type: 'string', example: '+48123456789'),
                    new OA\Property(property: 'roles', type: 'array', items: new OA\Items(type: 'string'), example: ['ROLE_ADMIN']),
                    new OA\Property(property: 'isActive', type: 'boolean', example: true),
                    new OA\Property(property: 'organizationId', type: 'string', format: 'uuid', example: '9d6c9c2f-8b3a-4c5e-9a1b-2c3d4e5f6a7b')
                ]
            )
        ),
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'User UUID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'User updated successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 200),
                        new OA\Property(property: 'message', type: 'string', example: 'User updated successfully')
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
                description: 'User or Organization not found',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 404),
                        new OA\Property(property: 'message', type: 'string', example: 'User not found')
                    ]
                )
            ),
            new OA\Response(
                response: 409,
                description: 'Conflict - Username, email or phone already exists',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 409),
                        new OA\Property(property: 'message', type: 'string', example: 'This email is already in use.')
                    ]
                )
            ),
            new OA\Response(
                response: 500,
                description: 'Server error',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 500),
                        new OA\Property(property: 'message', type: 'string', example: 'Failed to update user')
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
                description: 'Access denied - User belongs to different organization',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 403),
                        new OA\Property(property: 'message', type: 'string', example: 'Access denied to this user')
                    ]
                )
            )
        ]
    )]
    public function update(string $id, Request $request): JsonResponse
    {
        /** @var User|null $currentUser */
        $currentUser = $this->getUser();
        
        if (!$currentUser) {
            return new JsonResponse([
                'code' => 401,
                'message' => 'Unauthorized'
            ], 401);
        }

        try {
            $uuid = Uuid::fromString($id);
        } catch (InvalidArgumentException) {
            return $this->json([
                'code' => 400,
                'message' => 'Invalid UUID format'
            ], 400);
        }

        $user = $this->userRepository->findByUuid($uuid);

        if (!$user) {
            return $this->json([
                'code' => 404,
                'message' => 'User not found'
            ], 404);
        }

        if ($user->getOrganization()->getId()->toRfc4122() !== $currentUser->getOrganization()->getId()->toRfc4122()) {
            return $this->json([
                'code' => 403,
                'message' => 'Access denied to this user'
            ], 403);
        }

        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            return $this->json([
                'code' => 400,
                'message' => 'Invalid JSON'
            ], 400);
        }

        $dto = UpdateUserDTO::fromArray($data);
        $violations = $this->validator->validate($dto);

        if (count($violations) > 0) {
            return $this->json(
                ValidationErrorFormatter::format($violations),
                400
            );
        }

        try {
            $this->userService->updateUser($user, $dto);

            return $this->json([
                'code' => 200,
                'message' => 'User updated successfully'
            ], 200);
        } catch (InvalidArgumentException $e) {
            if (str_contains($e->getMessage(), 'username') || 
                str_contains($e->getMessage(), 'email') || 
                str_contains($e->getMessage(), 'phone')) {
                return $this->json([
                    'code' => 409,
                    'message' => $e->getMessage()
                ], 409);
            }

            return $this->json([
                'code' => 404,
                'message' => $e->getMessage()
            ], 404);
        } catch (Exception) {
            return $this->json([
                'code' => 500,
                'message' => 'Failed to update user'
            ], 500);
        }
    }

    #[Route('/{id}', name: 'users_delete', requirements: ['id' => '.+'], methods: ['DELETE'])]
    #[OA\Delete(
        path: '/api/users/{id}',
        summary: 'Delete a user',
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'User UUID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'User deleted successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 200),
                        new OA\Property(property: 'message', type: 'string', example: 'User deleted successfully')
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'User not found',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 404),
                        new OA\Property(property: 'message', type: 'string', example: 'User not found')
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
                description: 'Access denied - User belongs to different organization',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 403),
                        new OA\Property(property: 'message', type: 'string', example: 'Access denied to this user')
                    ]
                )
            )
        ]
    )]
    public function delete(string $id): JsonResponse
    {
        /** @var User|null $currentUser */
        $currentUser = $this->getUser();
        
        if (!$currentUser) {
            return new JsonResponse([
                'code' => 401,
                'message' => 'Unauthorized'
            ], 401);
        }

        try {
            $uuid = Uuid::fromString($id);
        } catch (InvalidArgumentException) {
            return $this->json([
                'code' => 400,
                'message' => 'Invalid UUID format'
            ], 400);
        }

        $user = $this->userRepository->findByUuid($uuid);

        if (!$user) {
            return $this->json([
                'code' => 404,
                'message' => 'User not found'
            ], 404);
        }

        if ($user->getOrganization()->getId()->toRfc4122() !== $currentUser->getOrganization()->getId()->toRfc4122()) {
            return $this->json([
                'code' => 403,
                'message' => 'Access denied to this user'
            ], 403);
        }

        try {
            $this->userService->deleteUser($user);

            return $this->json([
                'code' => 200,
                'message' => 'User deleted successfully'
            ], 200);
        } catch (Exception $e) {
            return $this->json([
                'code' => 500,
                'message' => 'Failed to delete user: ' . $e->getMessage()
            ], 500);
        }
    }


    /**
     * @throws TransportExceptionInterface
     */
    #[Route('/password_reset/request', name: 'user_password_reset_request', methods: ['POST'])]
    #[OA\Post(
        path: '/api/users/password_reset/request',
        description: 'Send a password reset token to user email',
        summary: 'Request password reset',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', example: 'user@example.com')
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Password reset email sent (or user not found - security)',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 200),
                        new OA\Property(property: 'message', type: 'string', example: 'If the email exists, a password reset link has been sent')
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
                                    new OA\Property(property: 'message', type: 'string', example: 'Email cannot be blank.')
                                ]
                            )
                        )
                    ]
                )
            ),
            new OA\Response(
                response: 500,
                description: 'Server error',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 500),
                        new OA\Property(property: 'message', type: 'string', example: 'Failed to send password reset email')
                    ]
                )
            )
        ]
    )]
    public function requestPasswordReset(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            return $this->json([
                'code' => 400,
                'message' => 'Invalid JSON'
            ], 400);
        }

        $dto = PasswordResetRequestDTO::fromArray($data);
        $violations = $this->validator->validate($dto);

        if (count($violations) > 0) {
            return $this->json(
                ValidationErrorFormatter::format($violations),
                400
            );
        }

        try {
            $this->userService->requestPasswordReset($dto->email);

            return $this->json([
                'code' => 200,
                'message' => 'If the email exists, a password reset link has been sent'
            ], 200);
        } catch (Exception) {
            return $this->json([
                'code' => 500,
                'message' => 'Failed to send password reset email'
            ], 500);
        }
    }

    #[Route('/password_reset/confirm', name: 'user_password_reset_confirm', methods: ['POST'])]
    #[OA\Post(
        path: '/api/users/password_reset/confirm',
        description: 'Reset password using the token received by email',
        summary: 'Confirm password reset',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['token', 'newPassword'],
                properties: [
                    new OA\Property(property: 'token', type: 'string', example: 'abc123def456...'),
                    new OA\Property(property: 'newPassword', type: 'string', example: 'NewP@ssw0rd!')
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Password successfully reset',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 200),
                        new OA\Property(property: 'message', type: 'string', example: 'Password has been successfully reset')
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: 'Invalid or expired token or validation error',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 400),
                        new OA\Property(property: 'message', type: 'string', example: 'Invalid or expired reset token'),
                        new OA\Property(
                            property: 'violations',
                            description: 'Validation errors (only present when validation fails)',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'field', type: 'string', example: 'token'),
                                    new OA\Property(property: 'message', type: 'string', example: 'Token cannot be blank.')
                                ]
                            ),
                            nullable: true
                        )
                    ]
                )
            ),
            new OA\Response(
                response: 500,
                description: 'Server error',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 500),
                        new OA\Property(property: 'message', type: 'string', example: 'Failed to reset password')
                    ]
                )
            )
        ]
    )]
    public function confirmPasswordReset(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            return $this->json([
                'code' => 400,
                'message' => 'Invalid JSON'
            ], 400);
        }

        $dto = PasswordResetConfirmDTO::fromArray($data);
        $violations = $this->validator->validate($dto);

        if (count($violations) > 0) {
            return $this->json(
                ValidationErrorFormatter::format($violations),
                400
            );
        }

        try {
            $success = $this->userService->confirmPasswordReset(
                $dto->token,
                $dto->newPassword
            );

            if (!$success) {
                return $this->json([
                    'code' => 400,
                    'message' => 'Invalid or expired reset token'
                ], 400);
            }

            return $this->json([
                'code' => 200,
                'message' => 'Password has been successfully reset'
            ], 200);
        } catch (Exception) {
            return $this->json([
                'code' => 500,
                'message' => 'Failed to reset password'
            ], 500);
        }
    }
}
