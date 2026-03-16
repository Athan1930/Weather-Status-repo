<?php
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

if ($uri === '/data.php' || $uri === '/api/data.php') {
    $file = $uri === '/data.php' 
        ? __DIR__ . '/data.php' 
        : __DIR__ . '/api/data.php';
    if (file_exists($file)) {
        require $file;
        exit;
    }
}

if ($uri !== '/' && file_exists(__DIR__ . $uri)) {
    return false;
}

require __DIR__ . '/index.html';
