<?php

namespace App\Storage;

use Aws\S3\S3Client;

class UploadsStorage
{
    private ?S3Client $client = null;

    /**
     * @param  array<string,mixed>  $disk  A Laravel filesystems disk config array.
     */
    public function __construct(
        private array $disk,
        private string $prefix = 'uploads',
    ) {}

    public static function fromConfig(): self
    {
        $diskName = (string) config('wordpress.uploads.disk', 's3');

        return new self(
            (array) config("filesystems.disks.{$diskName}", []),
            (string) config('wordpress.uploads.prefix', 'uploads'),
        );
    }

    public function isActive(): bool
    {
        return ($this->disk['driver'] ?? null) === 's3'
            && ! empty($this->disk['key'])
            && ! empty($this->disk['secret'])
            && ! empty($this->disk['bucket']);
    }

    public function bucket(): string
    {
        return (string) ($this->disk['bucket'] ?? '');
    }

    public function prefix(): string
    {
        return trim($this->prefix, '/');
    }

    public function streamBaseDir(): string
    {
        return 'wpcloud://' . $this->bucket() . '/' . $this->prefix();
    }

    public function publicBaseUrl(): string
    {
        if (! empty($this->disk['url'])) {
            return rtrim((string) $this->disk['url'], '/');
        }

        return rtrim((string) ($this->disk['endpoint'] ?? ''), '/') . '/' . $this->bucket();
    }

    public function client(): S3Client
    {
        return $this->client ??= S3ClientFactory::make($this->disk);
    }
}
