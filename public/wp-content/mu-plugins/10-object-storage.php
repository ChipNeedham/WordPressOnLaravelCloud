<?php
/**
 * Route WordPress uploads to S3-compatible object storage, transparently.
 *
 * Loads after 00-laravel-boot (numeric order), so the Laravel container and
 * config() are available. Registers the AWS SDK S3 stream wrapper under the
 * wpcloud:// scheme and points WordPress's uploads directory at it, while
 * rewriting the uploads URL to the public bucket. No-ops when object storage
 * is not configured, so local-disk uploads keep working in plain dev.
 */

use App\Storage\UploadsStorage;
use Aws\LruArrayCache;
use Aws\S3\StreamWrapper;

$uploads = UploadsStorage::fromConfig();

if (! $uploads->isActive()) {
    return;
}

// Per-request stat cache: WordPress stats the same path many times per request.
StreamWrapper::register($uploads->client(), 'wpcloud', new LruArrayCache());

add_filter('upload_dir', function (array $dirs) use ($uploads): array {
    $base = $uploads->streamBaseDir();                            // wpcloud://bucket/uploads
    $url  = $uploads->publicBaseUrl() . '/' . $uploads->prefix(); // https://.../uploads
    $subdir = $dirs['subdir'] ?? '';

    $dirs['basedir'] = $base;
    $dirs['baseurl'] = $url;
    $dirs['path']    = $base . $subdir;
    $dirs['url']     = $url . $subdir;

    return $dirs;
});
