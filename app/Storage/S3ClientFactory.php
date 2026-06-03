<?php

namespace App\Storage;

use Aws\S3\S3Client;

class S3ClientFactory
{
    /**
     * Map a Laravel filesystem disk config array to AWS S3 client args.
     *
     * @param  array<string,mixed>  $disk
     * @return array<string,mixed>
     */
    public static function buildArgs(array $disk): array
    {
        $args = [
            'version' => 'latest',
            'region' => $disk['region'] ?? 'us-east-1',
            'credentials' => [
                'key' => $disk['key'] ?? '',
                'secret' => $disk['secret'] ?? '',
            ],
        ];

        if (! empty($disk['endpoint'])) {
            $args['endpoint'] = $disk['endpoint'];
        }
        if (! empty($disk['use_path_style_endpoint'])) {
            $args['use_path_style_endpoint'] = true;
        }

        return $args;
    }

    /**
     * @param  array<string,mixed>  $disk
     */
    public static function make(array $disk): S3Client
    {
        return new S3Client(self::buildArgs($disk));
    }
}
