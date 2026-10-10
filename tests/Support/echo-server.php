<?php

declare(strict_types=1);

// Роутер вбудованого сервера PHP для TransportTest: повертає запит у JSON.

$uri = is_string($_SERVER['REQUEST_URI'] ?? null) ? $_SERVER['REQUEST_URI'] : '/';
$path = parse_url($uri, PHP_URL_PATH);

if ($path === '/redirect') {
    header('Location: /elsewhere', true, 302);

    return;
}
if ($path === '/sleep') {
    usleep(1_000_000);
}

$status = 200;
if (is_string($path) && preg_match('#^/status/(\d{3})$#', $path, $m) === 1) {
    $status = (int) $m[1];
    header('Retry-After: 7');
}

http_response_code($status);
header('Content-Type: application/json');
header('X-Multi: a', false);
header('X-Multi: b', false);
echo json_encode([
    'method' => $_SERVER['REQUEST_METHOD'] ?? '',
    'uri' => $uri,
    'headers' => array_change_key_case((array) getallheaders(), CASE_LOWER),
    'body' => file_get_contents('php://input'),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
