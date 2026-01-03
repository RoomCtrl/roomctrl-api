<?php

declare(strict_types=1);

namespace App\Feature\Download\Controller;

use App\Feature\Download\DTO\FileNotFoundResponseDTO;
use App\Feature\Download\Service\DownloadServiceInterface;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Downloads')]
class DownloadController extends AbstractController
{
    public function __construct(
        private readonly DownloadServiceInterface $downloadService
    ) {
    }
    #[Route('/download/android', name: 'download_android', methods: ['GET'])]
    #[OA\Get(
        path: '/api/download/android',
        description: 'Downloads the Android application file (.apk or PDF placeholder)',
        summary: 'Download Android application',
        responses: [
            new OA\Response(
                response: 200,
                description: 'Returns the Android application file',
                content: new OA\MediaType(
                    mediaType: 'application/octet-stream'
                )
            ),
            new OA\Response(
                response: 404,
                description: 'File not found',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 404),
                        new OA\Property(property: 'message', type: 'string', example: 'Android application file not found')
                    ]
                )
            )
        ]
    )]
    public function downloadAndroid(): BinaryFileResponse|JsonResponse
    {
        try {
            return $this->downloadService->getAndroidFile();
        } catch (InvalidArgumentException $e) {
            $responseDTO = new FileNotFoundResponseDTO('Android application file not found');
            return $this->json($responseDTO->toArray(), Response::HTTP_NOT_FOUND);
        }
    }

    #[Route('/download/ios', name: 'download_ios', methods: ['GET'])]
    #[OA\Get(
        path: '/api/download/ios',
        description: 'Downloads the iOS application file (.ipa or PDF placeholder)',
        summary: 'Download iOS application',
        responses: [
            new OA\Response(
                response: 200,
                description: 'Returns the iOS application file',
                content: new OA\MediaType(
                    mediaType: 'application/octet-stream'
                )
            ),
            new OA\Response(
                response: 404,
                description: 'File not found',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 404),
                        new OA\Property(property: 'message', type: 'string', example: 'iOS application file not found')
                    ]
                )
            )
        ]
    )]
    public function downloadIos(): BinaryFileResponse|JsonResponse
    {
        try {
            return $this->downloadService->getIosFile();
        } catch (InvalidArgumentException $e) {
            $responseDTO = new FileNotFoundResponseDTO('iOS application file not found');
            return $this->json($responseDTO->toArray(), Response::HTTP_NOT_FOUND);
        }
    }
}
