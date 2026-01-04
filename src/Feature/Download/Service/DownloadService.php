<?php

declare(strict_types=1);

namespace App\Feature\Download\Service;

use InvalidArgumentException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use DateTime;

readonly class DownloadService implements DownloadServiceInterface
{
    public function __construct(
        private string $projectDir
    ) {
    }

    public function getAndroidFile(): BinaryFileResponse
    {
        $filePath = $this->findFileInDirectory($this->projectDir . '/public/android');

        return $this->createFileResponse($filePath);
    }

    public function getIosFile(): BinaryFileResponse
    {
        $filePath = $this->findFileInDirectory($this->projectDir . '/public/ios');

        return $this->createFileResponse($filePath);
    }

    private function findFileInDirectory(string $directory): string
    {
        $files = glob($directory . '/*');

        if (empty($files)) {
            throw new InvalidArgumentException('Application file not found in directory');
        }

        $filePath = $files[0];

        if (!file_exists($filePath)) {
            throw new InvalidArgumentException('Application file not found');
        }

        return $filePath;
    }

    private function createFileResponse(string $filePath): BinaryFileResponse
    {
        $response = new BinaryFileResponse($filePath);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            basename($filePath)
        );

        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        $response->setEtag(md5_file($filePath));
        $response->setLastModified(new DateTime('@' . filemtime($filePath)));

        return $response;
    }
}
