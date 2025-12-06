<?php

declare(strict_types=1);

namespace App\Feature\User\Controller;

use App\Feature\User\Entity\User;
use App\Feature\Organization\Entity\Organization;
use App\Feature\User\Service\UserPasswordResetService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Uid\Uuid;
use OpenApi\Attributes as OA;

#[Route('/users')]
#[OA\Tag(name: 'Users')]
class UserController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private ValidatorInterface $validator;
    private UserPasswordHasherInterface $passwordHasher;
    private UserPasswordResetService $passwordResetService;

    public function __construct(
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator,
        UserPasswordHasherInterface $passwordHasher,
        UserPasswordResetService $passwordResetService
    ) {
        $this->entityManager = $entityManager;
        $this->validator = $validator;
        $this->passwordHasher = $passwordHasher;
        $this->passwordResetService = $passwordResetService;
    }

    #[Route('', name: 'users_list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/users',
        summary: 'Get all users',
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(
                name: 'withDetails',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'boolean'),
                description: 'Include organization details'
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
                            new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                            new OA\Property(property: 'username', type: 'string'),
                            new OA\Property(property: 'firstName', type: 'string'),
                            new OA\Property(property: 'lastName', type: 'string'),
                            new OA\Property(property: 'email', type: 'string'),
                            new OA\Property(property: 'phone', type: 'string'),
                            new OA\Property(property: 'roles', type: 'array', items: new OA\Items(type: 'string')),
                            new OA\Property(property: 'firstLoginStatus', type: 'boolean'),
                            new OA\Property(property: 'organization', type: 'object', nullable: true)
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
        $withDetails = filter_var($request->query->get('withDetails', false), FILTER_VALIDATE_BOOLEAN);
        $users = $this->entityManager->getRepository(User::class)->findAll();

        $data = array_map(function (User $user) use ($withDetails) {
            return $this->serializeUser($user, $withDetails);
        }, $users);

        return $this->json($data, 200);
    }

    #[Route('/{id}', name: 'users_get', methods: ['GET'], requirements: ['id' => '.+'])]
    #[OA\Get(
        path: '/api/users/{id}',
        summary: 'Get a single user by ID',
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid'),
                description: 'User UUID'
            ),
            new OA\Parameter(
                name: 'withDetails',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'boolean'),
                description: 'Include organization details'
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'User details',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                        new OA\Property(property: 'username', type: 'string'),
                        new OA\Property(property: 'firstName', type: 'string'),
                        new OA\Property(property: 'lastName', type: 'string'),
                        new OA\Property(property: 'email', type: 'string'),
                        new OA\Property(property: 'phone', type: 'string'),
                        new OA\Property(property: 'roles', type: 'array', items: new OA\Items(type: 'string')),
                        new OA\Property(property: 'firstLoginStatus', type: 'boolean'),
                        new OA\Property(property: 'organization', type: 'object', nullable: true)
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

        $user = $this->entityManager->getRepository(User::class)->find($uuid);

        if (!$user) {
            return $this->json([
                'code' => 404,
                'message' => 'User not found'
            ], 404);
        }

        $withDetails = filter_var($request->query->get('withDetails', false), FILTER_VALIDATE_BOOLEAN);

        return $this->json($this->serializeUser($user, $withDetails), 200);
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
                    new OA\Property(property: 'firstLoginStatus', type: 'boolean', example: true),
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
                        new OA\Property(property: 'message', type: 'string', example: 'JWT Token not found')
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

        $requiredFields = ['username', 'password', 'firstName', 'lastName', 'email', 'phone', 'organizationId'];
        $missingFields = [];
        
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $missingFields[] = $field;
            }
        }

        if (!empty($missingFields)) {
            return $this->json([
                'code' => 400,
                'message' => 'Missing required fields',
                'errors' => $missingFields
            ], 400);
        }

        try {
            $organizationUuid = Uuid::fromString($data['organizationId']);
        } catch (\InvalidArgumentException $e) {
            return $this->json([
                'code' => 400,
                'message' => 'Invalid organization UUID format'
            ], 400);
        }

        $organization = $this->entityManager->getRepository(Organization::class)->find($organizationUuid);
        if (!$organization) {
            return $this->json([
                'code' => 404,
                'message' => 'Organization not found'
            ], 404);
        }

        $user = new User();
        $user->setUsername($this->sanitizeInput($data['username']));
        $user->setFirstName($this->sanitizeInput($data['firstName']));
        $user->setLastName($this->sanitizeInput($data['lastName']));
        $user->setEmail($this->sanitizeInput($data['email']));
        $user->setPhone($this->sanitizeInput($data['phone']));
        $user->setOrganization($organization);
        
        $hashedPassword = $this->passwordHasher->hashPassword($user, $data['password']);
        $user->setPassword($hashedPassword);

        if (isset($data['roles']) && is_array($data['roles'])) {
            $user->setRoles($data['roles']);
        }

        if (isset($data['firstLoginStatus'])) {
            $user->setFirstLoginStatus((bool)$data['firstLoginStatus']);
        }

        $errors = $this->validator->validate($user);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getPropertyPath() . ': ' . $error->getMessage();
            }

            return $this->json([
                'code' => 400,
                'message' => 'Validation failed',
                'errors' => $errorMessages
            ], 400);
        }

        try {
            $this->entityManager->persist($user);
            $this->entityManager->flush();

            return $this->json([
                'code' => 201,
                'message' => 'User created successfully',
                'id' => $user->getId()->toRfc4122()
            ], 201);
        } catch (\Exception $e) {
            return $this->json([
                'code' => 500,
                'message' => 'Failed to create user: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/{id}', name: 'users_update', methods: ['PUT', 'PATCH'], requirements: ['id' => '.+'])]
    #[OA\Put(
        path: '/api/users/{id}',
        summary: 'Update a user',
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid'),
                description: 'User UUID'
            )
        ],
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
                    new OA\Property(property: 'firstLoginStatus', type: 'boolean', example: false),
                    new OA\Property(property: 'organizationId', type: 'string', format: 'uuid', example: '9d6c9c2f-8b3a-4c5e-9a1b-2c3d4e5f6a7b')
                ]
            )
        ),
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
                        new OA\Property(property: 'errors', type: 'array', items: new OA\Items(type: 'string'))
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
            )
        ]
    )]
    #[OA\Patch(
        path: '/api/users/{id}',
        summary: 'Partially update a user',
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid'),
                description: 'User UUID'
            )
        ],
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
                    new OA\Property(property: 'firstLoginStatus', type: 'boolean', example: false),
                    new OA\Property(property: 'organizationId', type: 'string', format: 'uuid', example: '9d6c9c2f-8b3a-4c5e-9a1b-2c3d4e5f6a7b')
                ]
            )
        ),
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
                        new OA\Property(property: 'errors', type: 'array', items: new OA\Items(type: 'string'))
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

        $user = $this->entityManager->getRepository(User::class)->find($uuid);

        if (!$user) {
            return $this->json([
                'code' => 404,
                'message' => 'User not found'
            ], 404);
        }

        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json([
                'code' => 400,
                'message' => 'Invalid JSON'
            ], 400);
        }

        if (isset($data['username'])) {
            $user->setUsername($this->sanitizeInput($data['username']));
        }

        if (isset($data['password'])) {
            $hashedPassword = $this->passwordHasher->hashPassword($user, $data['password']);
            $user->setPassword($hashedPassword);
        }

        if (isset($data['firstName'])) {
            $user->setFirstName($this->sanitizeInput($data['firstName']));
        }

        if (isset($data['lastName'])) {
            $user->setLastName($this->sanitizeInput($data['lastName']));
        }

        if (isset($data['roles']) && is_array($data['roles'])) {
            $user->setRoles($data['roles']);
        }

        if (isset($data['firstLoginStatus'])) {
            $user->setFirstLoginStatus((bool)$data['firstLoginStatus']);
        }

        if (isset($data['email'])) {
            $user->setEmail($this->sanitizeInput($data['email']));
        }

        if (isset($data['phone'])) {
            $user->setPhone($this->sanitizeInput($data['phone']));
        }

        if (isset($data['organizationId'])) {
            try {
                $organizationUuid = Uuid::fromString($data['organizationId']);
            } catch (\InvalidArgumentException $e) {
                return $this->json([
                    'code' => 400,
                    'message' => 'Invalid organization UUID format'
                ], 400);
            }

            $organization = $this->entityManager->getRepository(Organization::class)->find($organizationUuid);
            if (!$organization) {
                return $this->json([
                    'code' => 404,
                    'message' => 'Organization not found'
                ], 404);
            }
            $user->setOrganization($organization);
        }

        $errors = $this->validator->validate($user);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getPropertyPath() . ': ' . $error->getMessage();
            }

            return $this->json([
                'code' => 400,
                'message' => 'Validation failed',
                'errors' => $errorMessages
            ], 400);
        }

        try {
            $this->entityManager->flush();

            return $this->json([
                'code' => 200,
                'message' => 'User updated successfully'
            ], 200);
        } catch (\Exception $e) {
            return $this->json([
                'code' => 500,
                'message' => 'Failed to update user: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/{id}', name: 'users_delete', methods: ['DELETE'], requirements: ['id' => '.+'])]
    #[OA\Delete(
        path: '/api/users/{id}',
        summary: 'Delete a user',
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid'),
                description: 'User UUID'
            )
        ],
        responses: [
            new OA\Response(
                response: 204,
                description: 'User deleted successfully'
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

        $user = $this->entityManager->getRepository(User::class)->find($uuid);

        if (!$user) {
            return $this->json([
                'code' => 404,
                'message' => 'User not found'
            ], 404);
        }

        try {
            $this->entityManager->remove($user);
            $this->entityManager->flush();

            return new JsonResponse(null, 204);
        } catch (\Exception $e) {
            return $this->json([
                'code' => 500,
                'message' => 'Failed to delete user: ' . $e->getMessage()
            ], 500);
        }
    }

    private function serializeUser(User $user, bool $withDetails = false): array
    {
        $data = [
            'id' => $user->getId()->toRfc4122(),
            'username' => $user->getUsername(),
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName(),
            'email' => $user->getEmail(),
            'phone' => $user->getPhone(),
            'roles' => $user->getRoles(),
            'firstLoginStatus' => $user->isFirstLoginStatus()
        ];

        if ($withDetails) {
            $organization = $user->getOrganization();
            if ($organization) {
                $data['organization'] = [
                    'id' => $organization->getId()->toRfc4122(),
                    'regon' => $organization->getRegon(),
                    'name' => $organization->getName(),
                    'email' => $organization->getEmail()
                ];
            }
        }

        return $data;
    }

    private function sanitizeInput(string $input): string
    {
        return strip_tags($input);
    }

    #[Route('/password_reset/request', name: 'user_password_reset_request', methods: ['POST'])]
    #[OA\Post(
        path: '/api/users/password_reset/request',
        summary: 'Request password reset',
        description: 'Send a password reset token to user email',
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
                        new OA\Property(property: 'message', type: 'string', example: 'If the email exists, a password reset link has been sent')
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Invalid request')
        ]
    )]
    public function requestPasswordReset(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['email'])) {
            return new JsonResponse(['error' => 'Email is required'], 400);
        }

        $this->passwordResetService->requestPasswordReset($data['email']);

        return new JsonResponse([
            'message' => 'If the email exists, a password reset link has been sent'
        ]);
    }

    #[Route('/password_reset/confirm', name: 'user_password_reset_confirm', methods: ['POST'])]
    #[OA\Post(
        path: '/api/users/password_reset/confirm',
        summary: 'Confirm password reset',
        description: 'Reset password using the token received by email',
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
                        new OA\Property(property: 'message', type: 'string', example: 'Password has been successfully reset')
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Invalid or expired token')
        ]
    )]
    public function confirmPasswordReset(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['token']) || !isset($data['newPassword'])) {
            return new JsonResponse(['error' => 'Token and new password are required'], 400);
        }

        $success = $this->passwordResetService->confirmPasswordReset(
            $data['token'],
            $data['newPassword']
        );

        if (!$success) {
            return new JsonResponse(['error' => 'Invalid or expired reset token'], 400);
        }

        return new JsonResponse(['message' => 'Password has been successfully reset']);
    }
}
