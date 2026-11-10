<?php
declare(strict_types=1);

$uri = urldecode((string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/'));
$arquivo = __DIR__ . str_replace('/', DIRECTORY_SEPARATOR, $uri);
if ($uri !== '/' && is_file($arquivo)) {
    return false;
}
require __DIR__ . '/index.php';
