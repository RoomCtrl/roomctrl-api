<?php

declare(strict_types=1);

namespace App\Feature\Auth\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use App\Feature\Auth\Service\AuthService;
use App\Feature\Auth\Service\RegistrationService;
use App\Common\Utility\ValidationErrorFormatter;
use App\Feature\Auth\DTO\RegisterRequestDTO;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use OpenApi\Attributes as OA;
use LogicException;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use InvalidArgumentException;
use Exception;

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
        summary: 'Get current authenticated user information',
        description: 'Returns detailed information about the authenticated user. Optionally includes organization data that the user is assigned to.',
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(
                name: 'withOrganization',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'boolean', default: false),
                description: 'If true, the response includes full organization data assigned to the user. If false or not provided, organization data is not returned.'
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Returns authenticated user data',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'string', format: 'uuid', description: 'Unique user identifier'),
                        new OA\Property(property: 'username', type: 'string', description: 'Username'),
                        new OA\Property(property: 'roles', type: 'array', items: new OA\Items(type: 'string'), description: 'List of user roles'),
                        new OA\Property(property: 'firstName', type: 'string', description: 'User first name'),
                        new OA\Property(property: 'lastName', type: 'string', description: 'User last name'),
                        new OA\Property(property: 'email', type: 'string', description: 'User email address'),
                        new OA\Property(property: 'phone', type: 'string', description: 'User phone number'),
                        new OA\Property(property: 'firstLoginStatus', type: 'boolean', description: 'First login status'),
                        new OA\Property(
                            property: 'organization',
                            type: 'object',
                            nullable: true,
                            description: 'Organization details assigned to the user (present only when withOrganization=true)',
                            properties: [
                                new OA\Property(property: 'id', type: 'string', format: 'uuid', description: 'Unique organization identifier'),
                                new OA\Property(property: 'regon', type: 'string', description: 'Organization REGON number'),
                                new OA\Property(property: 'name', type: 'string', description: 'Organization name'),
                                new OA\Property(property: 'email', type: 'string', description: 'Organization email address'),
                            ]
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
                    new OA\Property(property: 'username', type: 'string', example: 'johndoe', description: 'Unique username (min 3 characters)'),
                    new OA\Property(property: 'password', type: 'string', example: 'SecurePass123', description: 'Password (min 6 characters)'),
                    new OA\Property(property: 'firstName', type: 'string', example: 'John', description: 'User first name'),
                    new OA\Property(property: 'lastName', type: 'string', example: 'Doe', description: 'User last name'),
                    new OA\Property(property: 'email', type: 'string', example: 'john.doe@example.com', description: 'User email address'),
                    new OA\Property(property: 'phone', type: 'string', example: '+48123456789', description: 'User phone number'),
                    new OA\Property(property: 'regon', type: 'string', example: '123456789', description: 'Organization REGON number (9-14 digits)'),
                    new OA\Property(property: 'organizationName', type: 'string', example: 'My Company Ltd', description: 'Organization name'),
                    new OA\Property(property: 'organizationEmail', type: 'string', example: 'contact@company.com', description: 'Organization email address')
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
                        new OA\Property(property: 'userId', type: 'string', format: 'uuid', description: 'ID of the newly created user'),
                        new OA\Property(property: 'organizationId', type: 'string', format: 'uuid', description: 'ID of the newly created organization')
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
        Request $request,
        ValidatorInterface $validator,
        RegistrationService $registrationService
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            return $this->json([
                'code' => 400,
                'message' => 'Invalid JSON'
            ], 400);
        }

        $registerDTO = RegisterRequestDTO::fromArray($data);

        $violations = $validator->validate($registerDTO);
        if (count($violations) > 0) {
            return $this->json(
                ValidationErrorFormatter::format($violations),
                400
            );
        }

        try {
            $user = $registrationService->register($registerDTO);

            return $this->json([
                'code' => 201,
                'message' => 'User registered successfully',
                'userId' => $user->getId()->toRfc4122(),
                'organizationId' => $user->getOrganization()->getId()->toRfc4122()
            ], 201);

        } catch (InvalidArgumentException $e) {
            $message = $e->getMessage();

            return $this->json([
                'code' => 409,
                'message' => $message
            ], 409);

        } catch (Exception $e) {
            return $this->json([
                'code' => 500,
                'message' => 'An error occurred during registration'
            ], 500);
        }
    }
}
