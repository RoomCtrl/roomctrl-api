<?php

declare(strict_types=1);

namespace App\Tests\Feature\Download\Service;

use App\Feature\Download\Service\DownloadService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DownloadServiceTest extends TestCase
{
    private DownloadService $downloadService;
    private string $projectDir;
    private string $androidDir;
    private string $iosDir;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/test_project_' . uniqid();
        $this->androidDir = $this->projectDir . '/public/android';
        $this->iosDir = $this->projectDir . '/public/ios';

        // Create test directories
        mkdir($this->androidDir, 0777, true);
        mkdir($this->iosDir, 0777, true);

        $this->downloadService = new DownloadService($this->projectDir);
    }

    protected function tearDown(): void
    {
        // Clean up test directories
        $this->removeDirectory($this->projectDir);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    public function testGetAndroidFileThrowsExceptionWhenNoFileExists(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Application file not found in directory');

        $this->downloadService->getAndroidFile();
    }

    public function testGetAndroidFileReturnsBinaryFileResponseWhenFileExists(): void
    {
        // Create a test file
        $testFile = $this->androidDir . '/app.apk';
        file_put_contents($testFile, 'test content');

        $response = $this->downloadService->getAndroidFile();

        $this->assertInstanceOf(BinaryFileResponse::class, $response);
        $this->assertEquals($testFile, $response->getFile()->getPathname());
    }

    public function testGetIosFileThrowsExceptionWhenNoFileExists(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Application file not found in directory');

        $this->downloadService->getIosFile();
    }

    public function testGetIosFileReturnsBinaryFileResponseWhenFileExists(): void
    {
        // Create a test file
        $testFile = $this->iosDir . '/app.ipa';
        file_put_contents($testFile, 'test content');

        $response = $this->downloadService->getIosFile();

        $this->assertInstanceOf(BinaryFileResponse::class, $response);
        $this->assertEquals($testFile, $response->getFile()->getPathname());
    }

    public function testGetAndroidFileReturnsFirstFileWhenMultipleExist(): void
    {
        // Create multiple test files
        file_put_contents($this->androidDir . '/app1.apk', 'test content 1');
        file_put_contents($this->androidDir . '/app2.apk', 'test content 2');

        $response = $this->downloadService->getAndroidFile();

        $this->assertInstanceOf(BinaryFileResponse::class, $response);
        // Should return the first file found
        $this->assertStringContainsString('.apk', $response->getFile()->getPathname());
    }

    public function testGetIosFileReturnsFirstFileWhenMultipleExist(): void
    {
        // Create multiple test files
        file_put_contents($this->iosDir . '/app1.ipa', 'test content 1');
        file_put_contents($this->iosDir . '/app2.ipa', 'test content 2');

        $response = $this->downloadService->getIosFile();

        $this->assertInstanceOf(BinaryFileResponse::class, $response);
        // Should return the first file found
        $this->assertStringContainsString('.ipa', $response->getFile()->getPathname());
    }

    public function testBinaryFileResponseHasCorrectHeaders(): void
    {
        $testFile = $this->androidDir . '/app.apk';
        file_put_contents($testFile, 'test content');

        $response = $this->downloadService->getAndroidFile();

        $headers = $response->headers;

        // Check cache control headers
        $this->assertStringContainsString('no-store', $headers->get('Cache-Control'));
        $this->assertStringContainsString('no-cache', $headers->get('Cache-Control'));
        $this->assertStringContainsString('must-revalidate', $headers->get('Cache-Control'));
        
        $this->assertEquals('no-cache', $headers->get('Pragma'));
        $this->assertEquals('0', $headers->get('Expires'));

        // Check ETag exists
        $this->assertNotNull($headers->get('ETag'));
    }

    public function testBinaryFileResponseHasCorrectDisposition(): void
    {
        $testFile = $this->androidDir . '/myapp.apk';
        file_put_contents($testFile, 'test content');

        $response = $this->downloadService->getAndroidFile();

        $disposition = $response->headers->get('Content-Disposition');
        
        $this->assertStringContainsString('attachment', $disposition);
        $this->assertStringContainsString('myapp.apk', $disposition);
    }
}
