<?php

declare(strict_types=1);

namespace App\Feature\Download\Service;

use Symfony\Component\HttpFoundation\BinaryFileResponse;

interface DownloadServiceInterface
{
    /**
     * Get Android application file for download
     *
     * @throws \InvalidArgumentException when file not found
     */
    public function getAndroidFile(): BinaryFileResponse;

    /**
     * Get iOS application file for download
     *
     * @throws \InvalidArgumentException when file not found
     */
    public function getIosFile(): BinaryFileResponse;
}
