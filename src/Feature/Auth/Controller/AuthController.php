<?php

declare(strict_types=1);

namespace App\Feature\Auth\Controller;

use App\Common\Utility\ValidationErrorFormatter;
use App\Feature\Auth\DTO\RegisterRequestDTO;
use App\Feature\Auth\Service\AuthService;
use App\Feature\User\Entity\User;
use Exception;
use InvalidArgumentException;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use LogicException;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[OA\Tag(name: 'Authentication')]
class AuthController extends AbstractController
{
    #[Route('/login_check', name: 'auth_login_check', methods: ['POST'])]
    #[OA\Post(
        path: '/api/login_check',
        summary: 'Login and get JWT token',
        requestBody: new OA\RequestBody(
            description: 'Login credentials',
            content: new OA\JsonContent(
                required: ['username', 'password'],
                properties: [
                    new OA\Property(property: 'username', type: 'string', example: 'user'),
                    new OA\Property(property: 'password', type: 'string', example: 'password')
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Returns JWT token',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'token', type: 'string')
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Invalid credentials',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 401),
                        new OA\Property(property: 'message', type: 'string', example: 'Invalid credentials')
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: 'Bad request',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 400),
                        new OA\Property(property: 'message', type: 'string', example: 'Invalid JSON')
                    ]
                )
            )
        ]
    )]
    public function getToken(): JsonResponse
    {
        throw new LogicException('This method should not be called directly - it\'s handled by the JWT authentication system.');
    }

    #[Route('/me', name: 'auth_me', methods: ['GET'])]
    #[OA\Get(
        path: '/api/me',
        description: 'Returns detailed information about the authenticated user. Optionally includes organization data that the user is assigned to.',
        summary: 'Get current authenticated user information',
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(
                name: 'withOrganization',
                description: 'If true, the response includes full organization data assigned to the user. If false or not provided, organization data is not returned.',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'boolean', default: false)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Returns authenticated user data',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', description: 'Unique user identifier', type: 'string', format: 'uuid'),
                        new OA\Property(property: 'username', description: 'Username', type: 'string'),
                        new OA\Property(property: 'roles', description: 'List of user roles', type: 'array', items: new OA\Items(type: 'string')),
                        new OA\Property(property: 'firstName', description: 'User first name', type: 'string'),
                        new OA\Property(property: 'lastName', description: 'User last name', type: 'string'),
                        new OA\Property(property: 'email', description: 'User email address', type: 'string'),
                        new OA\Property(property: 'phone', description: 'User phone number', type: 'string'),
                        new OA\Property(
                            property: 'organization',
                            description: 'Organization details assigned to the user (present only when withOrganization=true)',
                            properties: [
                                new OA\Property(property: 'id', description: 'Unique organization identifier', type: 'string', format: 'uuid'),
                                new OA\Property(property: 'regon', description: 'Organization REGON number', type: 'string'),
                                new OA\Property(property: 'name', description: 'Organization name', type: 'string'),
                                new OA\Property(property: 'email', description: 'Organization email address', type: 'string'),
                            ],
                            type: 'object',
                            nullable: true
                        )
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthorized - JWT token not found or invalid',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 401),
                        new OA\Property(property: 'message', type: 'string', example: 'JWT Token not found')
                    ]
                )
            )
        ]
    )]
    public function me(Request $request, AuthService $authService): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $withOrganization = filter_var($request->query->get('withOrganization', false), FILTER_VALIDATE_BOOLEAN);
        $data = $authService->getCurrentUserInfo($user, $withOrganization);
        return $this->json($data);
    }

    #[Route('/token_refresh', name: 'auth_token_refresh', methods: ['GET'])]
    #[OA\Get(
        path: '/api/token_refresh',
        description: 'Creates a new JWT token using current authentication. The current token must be provided in the Authorization header.',
        summary: 'Refresh JWT token',
        security: [['Bearer' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Returns refreshed JWT token',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'token', type: 'string')
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthorized',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 401),
                        new OA\Property(property: 'message', type: 'string', example: 'JWT Token not found or invalid')
                    ]
                )
            )
        ]
    )]
    public function refreshToken(JWTTokenManagerInterface $jwtManager): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $token = $jwtManager->create($user);

        return $this->json([
            'token' => $token
        ]);
    }

    #[Route('/register', name: 'auth_register', methods: ['POST'])]
    #[OA\Post(
        path: '/api/register',
        summary: 'Register a new user and create organization',
        requestBody: new OA\RequestBody(
            description: 'User registration data with organization details',
            required: true,
            content: new OA\JsonContent(
                required: ['username', 'password', 'firstName', 'lastName', 'email', 'phone', 'regon', 'organizationName', 'organizationEmail'],
                properties: [
                    new OA\Property(property: 'username', description: 'Unique username (min 3 characters)', type: 'string', example: 'johndoe'),
                    new OA\Property(property: 'password', description: 'Password (min 6 characters)', type: 'string', example: 'SecurePass123'),
                    new OA\Property(property: 'firstName', description: 'User first name', type: 'string', example: 'John'),
                    new OA\Property(property: 'lastName', description: 'User last name', type: 'string', example: 'Doe'),
                    new OA\Property(property: 'email', description: 'User email address', type: 'string', example: 'john.doe@example.com'),
                    new OA\Property(property: 'phone', description: 'User phone number', type: 'string', example: '+48123456789'),
                    new OA\Property(property: 'regon', description: 'Organization REGON number (9-14 digits)', type: 'string', example: '123456789'),
                    new OA\Property(property: 'organizationName', description: 'Organization name', type: 'string', example: 'My Company Ltd'),
                    new OA\Property(property: 'organizationEmail', description: 'Organization email address', type: 'string', example: 'contact@company.com')
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'User and organization created successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 201),
                        new OA\Property(property: 'message', type: 'string', example: 'User registered successfully'),
                        new OA\Property(property: 'userId', description: 'ID of the newly created user', type: 'string', format: 'uuid'),
                        new OA\Property(property: 'organizationId', description: 'ID of the newly created organization', type: 'string', format: 'uuid')
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: 'Validation failed',
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
                response: 409,
                description: 'Conflict - Username, REGON or organization email already exists',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 409),
                        new OA\Property(property: 'message', type: 'string', example: 'This username is already taken.')
                    ]
                )
            ),
            new OA\Response(
                response: 500,
                description: 'Server error',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 500),
                        new OA\Property(property: 'message', type: 'string', example: 'An error occurred during registration')
                    ]
                )
            )
        ]
    )]
    public function register(
        Request             $request,
        ValidatorInterface  $validator,
        AuthService         $authService
    ): JsonResponse
    {
        $data = $this->decodeJsonRequest($request);
        if ($data instanceof JsonResponse) {
            return $data;
        }

        $registerDTO = RegisterRequestDTO::fromArray($data);

        $validationResponse = $this->validateDTO($registerDTO, $validator);
        if ($validationResponse !== null) {
            return $validationResponse;
        }

        try {
            $user = $authService->register($registerDTO);

            return $this->createRegistrationSuccessResponse($user);

        } catch (InvalidArgumentException $e) {
            return $this->createConflictResponse($e->getMessage());

        } catch (Exception) {
            return $this->createServerErrorResponse();
        }
    }

    private function decodeJsonRequest(Request $request): array|JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            return $this->json([
                'code' => 400,
                'message' => 'Invalid JSON'
            ], 400);
        }

        return $data;
    }

    private function validateDTO(object $dto, ValidatorInterface $validator): ?JsonResponse
    {
        $violations = $validator->validate($dto);

        if (count($violations) > 0) {
            return $this->json(
                ValidationErrorFormatter::format($violations),
                400
            );
        }

        return null;
    }

    private function createRegistrationSuccessResponse(mixed $user): JsonResponse
    {
        return $this->json([
            'code' => 201,
            'message' => 'User registered successfully',
            'userId' => $user->getId()->toRfc4122(),
            'organizationId' => $user->getOrganization()->getId()->toRfc4122()
        ], 201);
    }

    private function createConflictResponse(string $message): JsonResponse
    {
        return $this->json([
            'code' => 409,
            'message' => $message
        ], 409);
    }

    private function createServerErrorResponse(): JsonResponse
    {
        return $this->json([
            'code' => 500,
            'message' => 'An error occurred during registration'
        ], 500);
    }
}
