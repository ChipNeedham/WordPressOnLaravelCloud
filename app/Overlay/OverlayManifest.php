<?php

namespace App\Overlay;

class OverlayManifest
{
    /** @return array{version:int,items:array<string,array{v:int}>} */
    public static function decode(string $json): array
    {
        $data = json_decode($json, true);
        if (! is_array($data) || ! isset($data['items']) || ! is_array($data['items'])) {
            return ['version' => 0, 'items' => []];
        }

        return ['version' => (int) ($data['version'] ?? 0), 'items' => $data['items']];
    }

    /** @param array{version:int,items:array} $manifest */
    public static function encode(array $manifest): string
    {
        return json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /** @param array{version:int,items:array} $manifest */
    public static function withItem(array $manifest, string $key): array
    {
        $current = (int) ($manifest['items'][$key]['v'] ?? 0);
        $manifest['items'][$key] = ['v' => $current + 1];
        $manifest['version'] = (int) ($manifest['version'] ?? 0) + 1;

        return $manifest;
    }

    /** @param array{version:int,items:array} $manifest */
    public static function withoutItem(array $manifest, string $key): array
    {
        unset($manifest['items'][$key]);
        $manifest['version'] = (int) ($manifest['version'] ?? 0) + 1;

        return $manifest;
    }

    /**
     * @return array{pull:string[],delete:string[]}
     */
    public static function diff(array $remote, array $local): array
    {
        $remoteItems = $remote['items'] ?? [];
        $localItems = $local['items'] ?? [];

        $pull = [];
        foreach ($remoteItems as $key => $info) {
            $localVersion = (int) ($localItems[$key]['v'] ?? 0);
            if ((int) ($info['v'] ?? 0) > $localVersion) {
                $pull[] = $key;
            }
        }

        $delete = [];
        foreach ($localItems as $key => $info) {
            if (! isset($remoteItems[$key])) {
                $delete[] = $key;
            }
        }

        return ['pull' => $pull, 'delete' => $delete];
    }
}
