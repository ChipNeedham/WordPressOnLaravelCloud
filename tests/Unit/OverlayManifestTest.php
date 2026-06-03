<?php

namespace Tests\Unit;

use App\Overlay\OverlayManifest;
use PHPUnit\Framework\TestCase;

class OverlayManifestTest extends TestCase
{
    public function test_decode_empty_or_invalid_returns_zero_manifest(): void
    {
        $this->assertSame(['version' => 0, 'items' => []], OverlayManifest::decode(''));
        $this->assertSame(['version' => 0, 'items' => []], OverlayManifest::decode('not json'));
    }

    public function test_with_item_adds_and_bumps_versions(): void
    {
        $m = OverlayManifest::withItem(['version' => 0, 'items' => []], 'plugins/akismet');
        $this->assertSame(1, $m['version']);
        $this->assertSame(1, $m['items']['plugins/akismet']['v']);

        $m = OverlayManifest::withItem($m, 'plugins/akismet');
        $this->assertSame(2, $m['version']);
        $this->assertSame(2, $m['items']['plugins/akismet']['v']);
    }

    public function test_without_item_removes_and_bumps_version(): void
    {
        $m = ['version' => 3, 'items' => ['plugins/a' => ['v' => 2], 'themes/b' => ['v' => 1]]];
        $m = OverlayManifest::withoutItem($m, 'plugins/a');
        $this->assertSame(4, $m['version']);
        $this->assertArrayNotHasKey('plugins/a', $m['items']);
        $this->assertArrayHasKey('themes/b', $m['items']);
    }

    public function test_diff_returns_pull_and_delete_sets(): void
    {
        $remote = ['version' => 5, 'items' => [
            'plugins/a' => ['v' => 2],
            'plugins/b' => ['v' => 1],
            'themes/c'  => ['v' => 1],
        ]];
        $local = ['version' => 4, 'items' => [
            'plugins/a' => ['v' => 1],
            'themes/c'  => ['v' => 1],
            'plugins/old' => ['v' => 1],
        ]];

        $diff = OverlayManifest::diff($remote, $local);
        sort($diff['pull']);
        $this->assertSame(['plugins/a', 'plugins/b'], $diff['pull']);
        $this->assertSame(['plugins/old'], $diff['delete']);
    }
}
