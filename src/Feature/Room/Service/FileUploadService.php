<?php

declare(strict_types=1);

namespace App\Feature\Room\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use InvalidArgumentException;

class FileUploadService
{
    private const ALLOWED_MIME_TYPES = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'pdf'];

    public function __construct(
        private readonly string $uploadDirectory
    ) {
    }

    /**
     * Validate if file type is allowed
     */
    public function isValidFileType(UploadedFile $file): bool
    {
        $mimeType = $file->getMimeType();
        $extension = $file->getClientOriginalExtension();

        return in_array($mimeType, self::ALLOWED_MIME_TYPES)
            && in_array(strtolower($extension), self::ALLOWED_EXTENSIONS);
    }

    /**
     * Upload files and return their paths
     *
     * @param array<int, UploadedFile> $files
     * @param string $identifier
     * @return array<int, string>
     */
    public function uploadFiles(array $files, string $identifier): array
    {
        if (!is_dir($this->uploadDirectory)) {
            mkdir($this->uploadDirectory, 0755, true);
        }

        $uploadedPaths = [];

        foreach ($files as $file) {
            if (!$file) {
                continue;
            }

            if (!$this->isValidFileType($file)) {
                throw new InvalidArgumentException('Invalid file type. Only JPG, PNG, and PDF files are allowed.');
            }

            $extension = $file->getClientOriginalExtension();
            $fileName = sprintf(
                '%s_%s_%s.%s',
                $identifier,
                time(),
                uniqid(),
                $extension
            );

            $file->move($this->uploadDirectory, $fileName);
            $uploadedPaths[] = '/uploads/rooms/' . $fileName;
        }

        return $uploadedPaths;
    }

    /**
     * Delete a file from filesystem
     */
    public function deleteFile(string $relativePath, string $projectDir): bool
    {
        $fullPath = $projectDir . '/public' . $relativePath;

        if (file_exists($fullPath)) {
            return unlink($fullPath);
        }

        return false;
    }

    /**
     * Delete multiple files from filesystem
     *
     * @param array<int, string> $relativePaths
     */
    public function deleteFiles(array $relativePaths, string $projectDir): int
    {
        $deletedCount = 0;

        foreach ($relativePaths as $path) {
            if ($this->deleteFile($path, $projectDir)) {
                $deletedCount++;
            }
        }

        return $deletedCount;
    }
}
