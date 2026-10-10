<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Http;

/**
 * HTTP-запит, який клієнт передає транспорту.
 */
final readonly class Request
{
    /**
     * @param string                $method  HTTP-метод: GET, POST, PUT, PATCH, DELETE
     * @param string                $url     повна адреса разом із рядком запиту
     * @param array<string, string> $headers заголовки: назва → значення
     * @param string|null           $body    тіло запиту; null — без тіла
     */
    public function __construct(
        public string $method,
        public string $url,
        public array $headers = [],
        public ?string $body = null,
    ) {}

    /**
     * Повертає значення заголовка name без урахування регістру або null.
     */
    public function header(string $name): ?string
    {
        foreach ($this->headers as $key => $value) {
            if (strcasecmp($key, $name) === 0) {
                return $value;
            }
        }

        return null;
    }

    /**
     * Повертає шлях адреси без рядка запиту, з екрануванням як є.
     */
    public function path(): string
    {
        $path = parse_url($this->url, PHP_URL_PATH);

        return is_string($path) ? $path : '';
    }

    /**
     * Повертає рядок запиту адреси без знака «?»; порожній, якщо його немає.
     */
    public function query(): string
    {
        $query = parse_url($this->url, PHP_URL_QUERY);

        return is_string($query) ? $query : '';
    }
}
