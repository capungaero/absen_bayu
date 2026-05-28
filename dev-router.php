<?php

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = __DIR__ . $path;

if ($path !== '/' && is_file($file)) {
    return false;
}

if (strpos($path, '/assets/') === 0) {
    http_response_code(404);
    return true;
}

require __DIR__ . '/index.php';
