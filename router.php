<?php
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$file = __DIR__ . $uri;

// If the file exists, serve it directly
if ($uri !== '/' && file_exists($file)) {
    return false;
}

// Route /api/data.php
if (strpos($uri, '/api/') === 0) {
    $phpFile = __DIR__ . $uri;
    if (file_exists($phpFile)) {
        require $phpFile;
        exit;
    }
}

// Default — serve index.html
require __DIR__ . '/index.html';
