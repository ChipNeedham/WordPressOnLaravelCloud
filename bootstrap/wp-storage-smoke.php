<?php
/**
 * Object-storage smoke test, run in its own PHP process (WordPress-first, so no
 * Laravel/__() collision). Uploads bytes through WordPress, asserts the object
 * landed in the bucket and is publicly fetchable, then cleans up. Prints PASS/FAIL.
 */

$_SERVER['HTTP_HOST'] = 'wordpressonlaravelcloud.test';
$_SERVER['REQUEST_URI'] = '/';
define('WP_USE_THEMES', false);

$root = dirname(__DIR__);
require $root . '/public/wp/wp-load.php';

$fail = static function (string $msg): void {
    fwrite(STDERR, "FAIL: {$msg}\n");
    exit(1);
};

// 1x1 transparent PNG.
$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+M8AAAMCAQDJ/iZ1AAAAAElFTkSuQmCC');

$upload = wp_upload_bits('smoke-test.png', null, $png);
if (! empty($upload['error'])) {
    $fail('wp_upload_bits error: ' . $upload['error']);
}

$file = $upload['file'];   // wpcloud://bucket/uploads/YYYY/MM/smoke-test.png
$url  = $upload['url'];     // http://localhost:9000/bucket/uploads/YYYY/MM/smoke-test.png

if (strpos($file, 'wpcloud://') !== 0) {
    $fail("upload path is not on the wrapper: {$file}");
}
if (strpos($url, 'http://localhost:9000/wordpressonlaravelcloud/uploads/') !== 0) {
    $fail("upload URL is not the bucket URL: {$url}");
}

// Object exists via the stream wrapper?
if (! file_exists($file)) {
    $fail("object not found via wrapper: {$file}");
}

// No local copy leaked into public/wp-content/uploads?
$relative = ltrim(str_replace('wpcloud://wordpressonlaravelcloud/uploads', '', $file), '/');
$localPath = $root . '/public/wp-content/uploads/' . $relative;
if (file_exists($localPath)) {
    $fail("a local copy leaked at {$localPath}");
}

// Publicly fetchable?
$ctx = stream_context_create(['http' => ['timeout' => 10]]);
$body = @file_get_contents($url, false, $ctx);
if ($body === false || strlen($body) !== strlen($png)) {
    $fail("public URL did not return the file: {$url}");
}

// Cleanup.
@unlink($file);
if (file_exists($file)) {
    $fail("cleanup failed, object still present: {$file}");
}

fwrite(STDOUT, "PASS uploaded+served+deleted via bucket: {$url}\n");
exit(0);
