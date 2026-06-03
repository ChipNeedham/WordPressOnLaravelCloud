<?php
/**
 * Code-overlay smoke test (WordPress-first subprocess + MinIO). Simulates a runtime
 * plugin install: writes a plugin locally, syncs it to the bucket, then wipes the
 * local copy and hydrates it back (cold-replica simulation), then removes it.
 */

$_SERVER['HTTP_HOST'] = 'wordpressonlaravelcloud.test';
$_SERVER['REQUEST_URI'] = '/';
define('WP_USE_THEMES', false);

$root = dirname(__DIR__);
require $root . '/public/wp/wp-load.php';

$fail = static function (string $m): void { fwrite(STDERR, "FAIL: {$m}\n"); exit(1); };

$overlay = App\Overlay\CodeOverlay::fromConfig();
if (! $overlay->isActive()) {
    $fail('object storage not configured');
}

$slug = 'overlay-smoke';
$dir = WP_CONTENT_DIR . '/plugins/' . $slug;
$file = $dir . '/overlay-smoke.php';

// 1. "Install": write a plugin to local disk, then sync to the bucket.
@mkdir($dir, 0775, true);
file_put_contents($file, "<?php /* Plugin Name: Overlay Smoke */\n");
$overlay->syncItem('plugins/' . $slug);

$versionAfterSync = $overlay->version();
if ($versionAfterSync < 1) {
    $fail("version not bumped after sync: {$versionAfterSync}");
}

// 2. Wipe local copy + reset sentinel → simulate a cold replica, then hydrate.
$rm = static function (string $p) use (&$rm) {
    if (is_dir($p)) { foreach (scandir($p) as $f) { if ($f !== '.' && $f !== '..') { $rm("$p/$f"); } } @rmdir($p); }
    elseif (is_file($p)) { @unlink($p); }
};
$rm($dir);
@unlink(WP_CONTENT_DIR . '/.overlay-state.json');
if (is_file($file)) { $fail('local plugin not removed before hydrate'); }

$overlay->hydrate();
if (! is_file($file)) { $fail('plugin not restored by hydrate'); }

// 3. Remove the item; assert it leaves the manifest.
$overlay->removeItem('plugins/' . $slug);
$manifest = $overlay->readManifest();
if (isset($manifest['items']['plugins/' . $slug])) { $fail('item still in manifest after remove'); }

$rm($dir);

fwrite(STDOUT, "PASS install->sync->hydrate->remove (version reached {$versionAfterSync})\n");
exit(0);
