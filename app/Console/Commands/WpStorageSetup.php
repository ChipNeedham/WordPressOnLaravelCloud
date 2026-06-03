<?php

namespace App\Console\Commands;

use App\Storage\UploadsStorage;
use Illuminate\Console\Command;

class WpStorageSetup extends Command
{
    protected $signature = 'wp:storage-setup';

    protected $description = 'Create the object-storage bucket and apply a public-read policy (local MinIO).';

    public function handle(): int
    {
        $uploads = UploadsStorage::fromConfig();

        if (! $uploads->isActive()) {
            $this->error('Object storage is not configured. Set FILESYSTEM_DISK=s3 and the AWS_* values.');

            return self::FAILURE;
        }

        $client = $uploads->client();
        $bucket = $uploads->bucket();

        if ($client->doesBucketExist($bucket)) {
            $this->info("Bucket {$bucket} already exists.");
        } else {
            $client->createBucket(['Bucket' => $bucket]);
            $this->info("Created bucket {$bucket}.");
        }

        $policy = json_encode([
            'Version' => '2012-10-17',
            'Statement' => [[
                'Effect' => 'Allow',
                'Principal' => '*',
                'Action' => ['s3:GetObject'],
                'Resource' => ["arn:aws:s3:::{$bucket}/*"],
            ]],
        ]);

        $client->putBucketPolicy(['Bucket' => $bucket, 'Policy' => $policy]);
        $this->info('Applied public-read policy for object GETs.');

        return self::SUCCESS;
    }
}
