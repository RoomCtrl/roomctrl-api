<?php

declare(strict_types=1);

namespace App\Feature\User\Controller;

use App\Common\Utility\ValidationErrorFormatter;
use App\Feature\User\DTO\CreateUserDTO;
use App\Feature\User\DTO\GenericSuccessResponseDTO;
use App\Feature\User\DTO\NotificationSettingsResponseDTO;
use App\Feature\User\DTO\PasswordResetConfirmDTO;
use App\Feature\User\DTO\PasswordResetRequestDTO;
use App\Feature\User\DTO\UpdateNotificationSettingsDTO;
use App\Feature\User\DTO\UpdateUserDTO;
use App\Feature\User\DTO\UserCreatedResponseDTO;
use App\Feature\User\Entity\User;
use App\Feature\User\Repository\UserRepository;
use App\Feature\User\Service\UserServiceInterface;
use Exception;
use InvalidArgumentException;
use OpenApi\Attributes as OA;
use Random\RandomException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
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
        private readonly UserServiceInterface $userService,
        private readonly UserRepository $userRepository,
        private readonly ValidatorInterface $validator
    ) {
    }

    #[Route('', name: 'users_list', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
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
                            new OA\Property(property: 'emailNotificationsEnabled', type: 'boolean', example: true),
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
                'code' => Response::HTTP_UNAUTHORIZED,
                'message' => 'Unauthorized'
            ], Response::HTTP_UNAUTHORIZED);
        }

        $withDetails = filter_var($request->query->get('withDetails', false), FILTER_VALIDATE_BOOLEAN);
        $users = $this->userService->getAllUsers($withDetails, $currentUser->getOrganization());

        return $this->json($users, Response::HTTP_OK);
    }

    #[Route('/settings/notifications', name: 'users_get_notification_settings', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[OA\Get(
        path: '/api/users/settings/notifications',
        summary: 'Get current email notification settings',
        description: 'Retrieve the current email notification preferences for the authenticated user',
        security: [['Bearer' => []]],
        tags: ['Users'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Current notification settings',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'emailNotificationsEnabled', type: 'boolean', example: true)
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
    public function getNotificationSettings(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user) {
            return $this->json([
                'code' => Response::HTTP_UNAUTHORIZED,
                'message' => 'User not authenticated'
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $this->json([
            'emailNotificationsEnabled' => $user->isEmailNotificationsEnabled()
        ], Response::HTTP_OK);
    }

    #[Route('/settings/notifications', name: 'users_update_notification_settings', methods: ['PATCH'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[OA\Patch(
        path: '/api/users/settings/notifications',
        summary: 'Update email notification settings',
        description: 'Enable or disable email notifications for bookings and participant invitations',
        security: [['Bearer' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['emailNotificationsEnabled'],
                properties: [
                    new OA\Property(
                        property: 'emailNotificationsEnabled',
                        type: 'boolean',
                        description: 'Enable or disable email notifications',
                        example: false
                    )
                ]
            )
        ),
        tags: ['Users'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Notification settings updated successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 200),
                        new OA\Property(property: 'message', type: 'string', example: 'Notification settings updated successfully'),
                        new OA\Property(property: 'emailNotificationsEnabled', type: 'boolean', example: false)
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
                                    new OA\Property(property: 'field', type: 'string'),
                                    new OA\Property(property: 'message', type: 'string')
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
                        new OA\Property(property: 'message', type: 'string', example: 'JWT Token not found')
                    ]
                )
            )
        ]
    )]
    public function updateNotificationSettings(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            return $this->json([
                'code' => Response::HTTP_BAD_REQUEST,
                'message' => 'Invalid JSON'
            ], Response::HTTP_BAD_REQUEST);
        }

        $dto = new UpdateNotificationSettingsDTO();
        $dto->emailNotificationsEnabled = $data['emailNotificationsEnabled'] ?? null;

        $violations = $this->validator->validate($dto);
        if (count($violations) > 0) {
            return $this->json(
                ValidationErrorFormatter::format($violations),
                Response::HTTP_BAD_REQUEST
            );
        }

        /** @var User $user */
        $user = $this->getUser();

        if (!$user) {
            return $this->json([
                'code' => Response::HTTP_UNAUTHORIZED,
                'message' => 'User not authenticated'
            ], Response::HTTP_UNAUTHORIZED);
        }

        $user->setEmailNotificationsEnabled($dto->emailNotificationsEnabled);
        $this->userRepository->save($user, true);

        $responseDTO = new NotificationSettingsResponseDTO(
            emailNotificationsEnabled: $user->isEmailNotificationsEnabled()
        );

        return $this->json($responseDTO->toArray(), Response::HTTP_OK);
    }

    #[Route('/{id}', name: 'users_get', requirements: ['id' => '.+'], methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
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
                        new OA\Property(property: 'emailNotificationsEnabled', type: 'boolean', example: true),
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
                'code' => Response::HTTP_UNAUTHORIZED,
                'message' => 'Unauthorized'
            ], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $uuid = Uuid::fromString($id);
        } catch (InvalidArgumentException) {
            return $this->json([
                'code' => Response::HTTP_BAD_REQUEST,
                'message' => 'Invalid UUID format'
            ], Response::HTTP_BAD_REQUEST);
        }

        $withDetails = filter_var($request->query->get('withDetails', false), FILTER_VALIDATE_BOOLEAN);
        $userDTO = $this->userService->getUserById($uuid, $withDetails);

        if (!$userDTO) {
            return $this->json([
                'code' => Response::HTTP_NOT_FOUND,
                'message' => 'User not found'
            ], Response::HTTP_NOT_FOUND);
        }

        $targetUser = $this->userRepository->findByUuid($uuid);
        if ($targetUser && !$this->userService->canCurrentUserAccessUser($targetUser, $currentUser)) {
            return $this->json([
                'code' => Response::HTTP_FORBIDDEN,
                'message' => 'Access denied to this user'
            ], Response::HTTP_FORBIDDEN);
        }

        return $this->json($userDTO->toArray(), Response::HTTP_OK);
    }

    #[Route('', name: 'users_create', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
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
            ),
            new OA\Response(
                response: 403,
                description: 'Forbidden - Requires ROLE_ADMIN',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 403),
                        new OA\Property(property: 'message', type: 'string', example: 'Access denied')
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

        $dto = CreateUserDTO::fromArray($data);
        $violations = $this->validator->validate($dto);

        if (count($violations) > 0) {
            return $this->json(
                ValidationErrorFormatter::format($violations),
                Response::HTTP_BAD_REQUEST
            );
        }

        try {
            $user = $this->userService->createUser($dto);

            $responseDTO = new UserCreatedResponseDTO(
                id: $user->getId()->toRfc4122()
            );

            return $this->json($responseDTO->toArray(), Response::HTTP_CREATED);
        } catch (InvalidArgumentException $e) {
            if (
                str_contains($e->getMessage(), 'username') ||
                str_contains($e->getMessage(), 'email') ||
                str_contains($e->getMessage(), 'phone')
            ) {
                return $this->json([
                    'code' => Response::HTTP_CONFLICT,
                    'message' => $e->getMessage()
                ], Response::HTTP_CONFLICT);
            }

            return $this->json([
                'code' => Response::HTTP_NOT_FOUND,
                'message' => $e->getMessage()
            ], Response::HTTP_NOT_FOUND);
        } catch (Exception $e) {
            // Obsługa naruszenia unique constraint z bazy danych
            if (str_contains($e->getMessage(), '23505') || str_contains($e->getMessage(), 'duplicate key')) {
                if (str_contains($e->getMessage(), 'username')) {
                    return $this->json([
                        'code' => Response::HTTP_CONFLICT,
                        'message' => 'This username is already taken.'
                    ], Response::HTTP_CONFLICT);
                }
            }

            return $this->json([
                'code' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'message' => 'Failed to create user'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{id}', name: 'users_update', requirements: ['id' => '.+'], methods: ['PUT', 'PATCH'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
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
                        new OA\Property(property: 'message', type: 'string', example: 'Access denied')
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
                        new OA\Property(property: 'message', type: 'string', example: 'Access denied')
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
                'code' => Response::HTTP_UNAUTHORIZED,
                'message' => 'Unauthorized'
            ], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $uuid = Uuid::fromString($id);
        } catch (InvalidArgumentException) {
            return $this->json([
                'code' => Response::HTTP_BAD_REQUEST,
                'message' => 'Invalid UUID format'
            ], Response::HTTP_BAD_REQUEST);
        }

        $user = $this->userRepository->findByUuid($uuid);

        if (!$user) {
            return $this->json([
                'code' => Response::HTTP_NOT_FOUND,
                'message' => 'User not found'
            ], Response::HTTP_NOT_FOUND);
        }

        if (!$this->userService->canCurrentUserAccessUser($user, $currentUser)) {
            return $this->json([
                'code' => Response::HTTP_FORBIDDEN,
                'message' => 'Access denied to this user'
            ], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            return $this->json([
                'code' => Response::HTTP_BAD_REQUEST,
                'message' => 'Invalid JSON'
            ], Response::HTTP_BAD_REQUEST);
        }

        $dto = UpdateUserDTO::fromArray($data);
        $violations = $this->validator->validate($dto);

        if (count($violations) > 0) {
            return $this->json(
                ValidationErrorFormatter::format($violations),
                Response::HTTP_BAD_REQUEST
            );
        }

        try {
            $this->userService->updateUser($user, $dto);

            $responseDTO = new GenericSuccessResponseDTO('User updated successfully');

            return $this->json($responseDTO->toArray(), Response::HTTP_OK);
        } catch (InvalidArgumentException $e) {
            if (
                str_contains($e->getMessage(), 'username') ||
                str_contains($e->getMessage(), 'email') ||
                str_contains($e->getMessage(), 'phone')
            ) {
                return $this->json([
                    'code' => Response::HTTP_CONFLICT,
                    'message' => $e->getMessage()
                ], Response::HTTP_CONFLICT);
            }

            return $this->json([
                'code' => Response::HTTP_NOT_FOUND,
                'message' => $e->getMessage()
            ], Response::HTTP_NOT_FOUND);
        } catch (Exception) {
            return $this->json([
                'code' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'message' => 'Failed to update user'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{id}', name: 'users_delete', requirements: ['id' => '.+'], methods: ['DELETE'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
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
                'code' => Response::HTTP_UNAUTHORIZED,
                'message' => 'Unauthorized'
            ], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $uuid = Uuid::fromString($id);
        } catch (InvalidArgumentException) {
            return $this->json([
                'code' => Response::HTTP_BAD_REQUEST,
                'message' => 'Invalid UUID format'
            ], Response::HTTP_BAD_REQUEST);
        }

        $user = $this->userRepository->findByUuid($uuid);

        if (!$user) {
            return $this->json([
                'code' => Response::HTTP_NOT_FOUND,
                'message' => 'User not found'
            ], Response::HTTP_NOT_FOUND);
        }

        if (!$this->userService->canCurrentUserAccessUser($user, $currentUser)) {
            return $this->json([
                'code' => Response::HTTP_FORBIDDEN,
                'message' => 'Access denied to this user'
            ], Response::HTTP_FORBIDDEN);
        }

        try {
            $this->userService->deleteUser($user);

            $responseDTO = new GenericSuccessResponseDTO('User deleted successfully');

            return $this->json($responseDTO->toArray(), Response::HTTP_OK);
        } catch (Exception $e) {
            return $this->json([
                'code' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'message' => 'Failed to delete user: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
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
                'code' => Response::HTTP_BAD_REQUEST,
                'message' => 'Invalid JSON'
            ], Response::HTTP_BAD_REQUEST);
        }

        $dto = PasswordResetRequestDTO::fromArray($data);
        $violations = $this->validator->validate($dto);

        if (count($violations) > 0) {
            return $this->json(
                ValidationErrorFormatter::format($violations),
                Response::HTTP_BAD_REQUEST
            );
        }

        try {
            $this->userService->requestPasswordReset($dto->email);

            $responseDTO = new GenericSuccessResponseDTO('If the email exists, a password reset link has been sent');

            return $this->json($responseDTO->toArray(), Response::HTTP_OK);
        } catch (Exception) {
            return $this->json([
                'code' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'message' => 'Failed to send password reset email'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
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
                'code' => Response::HTTP_BAD_REQUEST,
                'message' => 'Invalid JSON'
            ], Response::HTTP_BAD_REQUEST);
        }

        $dto = PasswordResetConfirmDTO::fromArray($data);
        $violations = $this->validator->validate($dto);

        if (count($violations) > 0) {
            return $this->json(
                ValidationErrorFormatter::format($violations),
                Response::HTTP_BAD_REQUEST
            );
        }

        try {
            $success = $this->userService->confirmPasswordReset(
                $dto->token,
                $dto->newPassword
            );

            if (!$success) {
                return $this->json([
                    'code' => Response::HTTP_BAD_REQUEST,
                    'message' => 'Invalid or expired reset token'
                ], Response::HTTP_BAD_REQUEST);
            }

            $responseDTO = new GenericSuccessResponseDTO('Password has been successfully reset');

            return $this->json($responseDTO->toArray(), Response::HTTP_OK);
        } catch (Exception) {
            return $this->json([
                'code' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'message' => 'Failed to reset password'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
