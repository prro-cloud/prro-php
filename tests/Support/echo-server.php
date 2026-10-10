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

// Заголовки запиту з $_SERVER: getallheaders() має різні сигнатури в PHP 8.2 і 8.4.
$headers = [];
foreach ($_SERVER as $key => $value) {
    if (is_string($key) && str_starts_with($key, 'HTTP_')) {
        $headers[strtolower(str_replace('_', '-', substr($key, 5)))] = $value;
    }
}
if (isset($_SERVER['CONTENT_TYPE'])) {
    $headers['content-type'] = $_SERVER['CONTENT_TYPE'];
}

http_response_code($status);
header('Content-Type: application/json');
header('X-Multi: a', false);
header('X-Multi: b', false);
echo json_encode([
    'method' => $_SERVER['REQUEST_METHOD'] ?? '',
    'uri' => $uri,
    'headers' => $headers,
    'body' => file_get_contents('php://input'),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
