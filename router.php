<?php
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// Route /data.php
if ($uri === '/data.php') {
    require __DIR__ . '/data.php';
    exit;
}

// Route /api/data.php
if ($uri === '/api/data.php') {
    require __DIR__ . '/api/data.php';
    exit;
}

// Serve static files directly
$file = __DIR__ . $uri;
if ($uri !== '/' && file_exists($file) && !is_dir($file)) {
    return false;
}

// Default — serve index.html
require __DIR__ . '/index.html';
