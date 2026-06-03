<?php

namespace App\Overlay;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CodeOverlay
{
    public function __construct(
        private string $disk,
        private string $prefix,
        private string $contentDir,
    ) {}

    public static function fromConfig(): self
    {
        $contentDir = defined('WP_CONTENT_DIR')
            ? WP_CONTENT_DIR
            : dirname(__DIR__, 2) . '/public/wp-content';

        return new self(
            (string) config('wordpress.uploads.disk', 's3'),
            trim((string) config('wordpress.overlay.prefix', 'code'), '/'),
            $contentDir,
        );
    }

    public function isActive(): bool
    {
        $d = (array) config("filesystems.disks.{$this->disk}", []);

        return ($d['driver'] ?? null) === 's3'
            && ! empty($d['key']) && ! empty($d['secret']) && ! empty($d['bucket']);
    }

    /** The DB-backed cross-replica version marker (mirrors manifest version). */
    public function version(): int
    {
        return (int) get_option('wpcloud_overlay_version', 0);
    }

    public function readManifest(): array
    {
        $disk = Storage::disk($this->disk);
        $path = $this->prefix . '/manifest.json';

        return $disk->exists($path)
            ? OverlayManifest::decode((string) $disk->get($path))
            : ['version' => 0, 'items' => []];
    }

    /** Write-through: mirror a local item to S3, update the manifest + DB version. */
    public function syncItem(string $key): void
    {
        $this->withLock(function () use ($key) {
            $this->mirrorToS3($key);
            $manifest = OverlayManifest::withItem($this->readManifest(), $key);
            $this->writeManifest($manifest);
        });
    }

    /** Remove an item from S3 + manifest + bump the DB version. */
    public function removeItem(string $key): void
    {
        $this->withLock(function () use ($key) {
            $disk = Storage::disk($this->disk);
            foreach ($disk->allFiles($this->prefix . '/' . $key) as $file) {
                $disk->delete($file);
            }
            $manifest = OverlayManifest::withoutItem($this->readManifest(), $key);
            $this->writeManifest($manifest);
        });
    }

    /** Pull the runtime delta onto local disk if this replica is behind. */
    public function hydrate(): void
    {
        $state = $this->localState();
        if ((int) ($state['version'] ?? 0) === $this->version()) {
            return;
        }

        $manifest = $this->readManifest();
        $plan = OverlayManifest::diff($manifest, $state);

        foreach ($plan['pull'] as $key) {
            $this->pullItem($key);
        }
        foreach ($plan['delete'] as $key) {
            $this->deleteLocalItem($key);
        }

        $this->writeLocalState($manifest);
    }

    // --- internals ---

    private function writeManifest(array $manifest): void
    {
        Storage::disk($this->disk)->put(
            $this->prefix . '/manifest.json',
            OverlayManifest::encode($manifest)
        );
        update_option('wpcloud_overlay_version', $manifest['version']);
    }

    private function mirrorToS3(string $key): void
    {
        $disk = Storage::disk($this->disk);
        $localBase = $this->contentDir . '/' . $key;
        $s3Base = $this->prefix . '/' . $key;

        $localFiles = [];
        foreach ($this->localFiles($localBase) as $absolute) {
            $rel = ltrim(substr($absolute, strlen($localBase)), '/');
            $s3Key = $rel === '' ? $s3Base : $s3Base . '/' . $rel;
            $disk->put($s3Key, (string) file_get_contents($absolute));
            $localFiles[$s3Key] = true;
        }

        foreach ($disk->allFiles($s3Base) as $existing) {
            if (! isset($localFiles[$existing])) {
                $disk->delete($existing);
            }
        }
    }

    private function pullItem(string $key): void
    {
        $disk = Storage::disk($this->disk);
        $s3Base = $this->prefix . '/' . $key;
        $finalBase = $this->contentDir . '/' . $key;
        $tmpBase = $this->contentDir . '/.overlay-tmp/' . $key;

        $this->rmrf($tmpBase);

        foreach ($disk->allFiles($s3Base) as $s3Key) {
            $rel = ltrim(substr($s3Key, strlen($s3Base)), '/');
            $dest = $rel === '' ? $tmpBase : $tmpBase . '/' . $rel;
            @mkdir(dirname($dest), 0775, true);
            file_put_contents($dest, (string) $disk->get($s3Key));
        }

        @mkdir(dirname($finalBase), 0775, true);
        $this->rmrf($finalBase);
        rename($tmpBase, $finalBase);
    }

    private function deleteLocalItem(string $key): void
    {
        $this->rmrf($this->contentDir . '/' . $key);
    }

    /** @return string[] absolute paths of files under $base (or [$base] if it is a file) */
    private function localFiles(string $base): array
    {
        if (is_file($base)) {
            return [$base];
        }
        if (! is_dir($base)) {
            return [];
        }

        $files = [];
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($it as $file) {
            if ($file->isFile()) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    private function rmrf(string $path): void
    {
        if (is_file($path)) {
            @unlink($path);

            return;
        }
        if (! is_dir($path)) {
            return;
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $file) {
            $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
        }
        @rmdir($path);
    }

    private function localState(): array
    {
        $path = $this->contentDir . '/.overlay-state.json';
        if (! is_file($path)) {
            return ['version' => 0, 'items' => []];
        }

        return OverlayManifest::decode((string) file_get_contents($path));
    }

    private function writeLocalState(array $manifest): void
    {
        file_put_contents(
            $this->contentDir . '/.overlay-state.json',
            OverlayManifest::encode($manifest)
        );
    }

    /** Serialize manifest read-modify-write across replicas via a MySQL named lock. */
    private function withLock(callable $callback): void
    {
        DB::selectOne('SELECT GET_LOCK(?, ?) AS l', ['wpcloud_overlay', 10]);
        try {
            $callback();
        } finally {
            DB::selectOne('SELECT RELEASE_LOCK(?) AS l', ['wpcloud_overlay']);
        }
    }
}
