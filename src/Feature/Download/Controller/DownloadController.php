<?php

declare(strict_types=1);

namespace App\Feature\Download\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

#[OA\Tag(name: 'Downloads')]
class DownloadController extends AbstractController
{
    #[Route('/download/android', name: 'download_android', methods: ['GET'])]
    #[OA\Get(
        path: '/api/download/android',
        summary: 'Download Android application',
        description: 'Downloads the Android application file (.apk or PDF placeholder)',
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
                        new OA\Property(property: 'error', type: 'string', example: 'Android application file not found')
                    ]
                )
            )
        ]
    )]
    public function downloadAndroid(): BinaryFileResponse
    {
        $publicDir = $this->getParameter('kernel.project_dir') . '/public';
        $androidDir = $publicDir . '/android';

        $files = glob($androidDir . '/*');

        if (empty($files)) {
            throw new NotFoundHttpException('Android application file not found');
        }

        $filePath = $files[0];

        if (!file_exists($filePath)) {
            throw new NotFoundHttpException('Android application file not found');
        }

        $response = new BinaryFileResponse($filePath);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            basename($filePath)
        );

        return $response;
    }

    #[Route('/download/ios', name: 'download_ios', methods: ['GET'])]
    #[OA\Get(
        path: '/api/download/ios',
        summary: 'Download iOS application',
        description: 'Downloads the iOS application file (.ipa or PDF placeholder)',
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
                        new OA\Property(property: 'error', type: 'string', example: 'iOS application file not found')
                    ]
                )
            )
        ]
    )]
    public function downloadIos(): BinaryFileResponse
    {
        $publicDir = $this->getParameter('kernel.project_dir') . '/public';
        $iosDir = $publicDir . '/ios';

        $files = glob($iosDir . '/*');

        if (empty($files)) {
            throw new NotFoundHttpException('iOS application file not found');
        }

        $filePath = $files[0];

        if (!file_exists($filePath)) {
            throw new NotFoundHttpException('iOS application file not found');
        }

        $response = new BinaryFileResponse($filePath);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            basename($filePath)
        );

        return $response;
    }
}
