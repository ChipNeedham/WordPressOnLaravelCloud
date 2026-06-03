<?php

namespace Tests\Unit;

use App\Storage\S3ClientFactory;
use PHPUnit\Framework\TestCase;

class S3ClientFactoryTest extends TestCase
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
            'use_path_style_endpoint' => true,
        ], $overrides);
    }

    public function test_builds_core_args(): void
    {
        $args = S3ClientFactory::buildArgs($this->disk());

        $this->assertSame('latest', $args['version']);
        $this->assertSame('us-east-1', $args['region']);
        $this->assertSame('minioadmin', $args['credentials']['key']);
        $this->assertSame('minioadmin', $args['credentials']['secret']);
        $this->assertSame('http://localhost:9000', $args['endpoint']);
        $this->assertTrue($args['use_path_style_endpoint']);
    }

    public function test_omits_endpoint_and_path_style_when_absent(): void
    {
        $args = S3ClientFactory::buildArgs($this->disk([
            'endpoint' => null,
            'use_path_style_endpoint' => false,
        ]));

        $this->assertArrayNotHasKey('endpoint', $args);
        $this->assertArrayNotHasKey('use_path_style_endpoint', $args);
        $this->assertSame('us-east-1', $args['region']);
    }

    public function test_make_returns_s3_client(): void
    {
        $this->assertInstanceOf(\Aws\S3\S3Client::class, S3ClientFactory::make($this->disk()));
    }
}
