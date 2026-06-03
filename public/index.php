<?php
/**
 * Front controller for WordPress on Laravel Cloud.
 *
 * 1. Answer Cloud health checks with a dependency-free 200 (before anything
 *    else loads, so a DB/cache outage can never fail the health check).
 * 2. Otherwise hand the request to WordPress.
 */

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = rtrim($path, '/');

if ($path === '/up' || $path === '/health') {
    http_response_code(200);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'OK';
    exit;
}

require __DIR__ . '/wp/index.php';
