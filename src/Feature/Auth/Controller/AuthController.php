<?php

declare(strict_types=1);

namespace App\Feature\Auth\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use App\Feature\User\Service\UserService;
use OpenApi\Attributes as OA;
use LogicException;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;

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
        path: '/api/v1/me',
        summary: 'Get current authenticated user info',
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(
                name: 'withDetails',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'boolean'),
                description: 'If true, returns full organization data in response.'
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Returns the authenticated user data',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                        new OA\Property(property: 'username', type: 'string'),
                        new OA\Property(property: 'roles', type: 'array', items: new OA\Items(type: 'string')),
                        new OA\Property(property: 'firstName', type: 'string'),
                        new OA\Property(property: 'lastName', type: 'string'),
                        new OA\Property(property: 'email', type: 'string'),
                        new OA\Property(property: 'phone', type: 'string'),
                        new OA\Property(property: 'firstLoginStatus', type: 'boolean'),
                        new OA\Property(
                            property: 'organization',
                            type: 'object',
                            nullable: true,
                            description: 'Organization details (present only if withDetails=true)',
                            properties: [
                                new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                                new OA\Property(property: 'regon', type: 'string'),
                                new OA\Property(property: 'name', type: 'string'),
                                new OA\Property(property: 'email', type: 'string'),
                            ]
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
    public function me(Request $request, UserService $userService): JsonResponse
    {
        $user = $this->getUser();
        $withDetails = filter_var($request->query->get('withDetails', false), FILTER_VALIDATE_BOOLEAN);
        $data = $userService->getUserInfo($user, $withDetails);
        return $this->json($data);
    }

    #[Route('/token_refresh', name: 'auth_token_refresh', methods: ['GET'])]
    #[OA\Get(
        path: '/api/v1/token_refresh',
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
}
