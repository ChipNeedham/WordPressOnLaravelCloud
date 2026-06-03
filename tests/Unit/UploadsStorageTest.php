<?php

namespace Tests\Unit;

use App\Storage\UploadsStorage;
use PHPUnit\Framework\TestCase;

class UploadsStorageTest extends TestCase
{
    private function disk(array $overrides = []): array
    {
        return array_merge([
            'driver' => 's3',
            'key' => 'minioadmin',
            'secret' => 'minioadmin',
            'region' => 'us-east-1',
            'bucket' => 'wordpressonlaravelcloud',
            'endpoint' => 'http://localhost:9000',
            'url' => 'http://localhost:9000/wordpressonlaravelcloud',
            'use_path_style_endpoint' => true,
        ], $overrides);
    }

    public function test_is_active_when_s3_with_credentials(): void
    {
        $this->assertTrue((new UploadsStorage($this->disk()))->isActive());
    }

    public function test_inactive_when_not_s3_or_missing_creds(): void
    {
        $this->assertFalse((new UploadsStorage(['driver' => 'local']))->isActive());
        $this->assertFalse((new UploadsStorage($this->disk(['key' => ''])))->isActive());
        $this->assertFalse((new UploadsStorage($this->disk(['bucket' => ''])))->isActive());
    }

    public function test_stream_base_dir(): void
    {
        $this->assertSame(
            'wpcloud://wordpressonlaravelcloud/uploads',
            (new UploadsStorage($this->disk(), 'uploads'))->streamBaseDir()
        );
    }

    public function test_public_base_url_prefers_disk_url(): void
    {
        $this->assertSame(
            'http://localhost:9000/wordpressonlaravelcloud',
            (new UploadsStorage($this->disk()))->publicBaseUrl()
        );
    }

    public function test_public_base_url_falls_back_to_endpoint_and_bucket(): void
    {
        $this->assertSame(
            'http://localhost:9000/wordpressonlaravelcloud',
            (new UploadsStorage($this->disk(['url' => null])))->publicBaseUrl()
        );
    }

    public function test_prefix_is_trimmed(): void
    {
        $this->assertSame('uploads', (new UploadsStorage($this->disk(), '/uploads/'))->prefix());
    }
}
