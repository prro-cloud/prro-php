<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Http;

/**
 * HTTP-відповідь, яку транспорт повертає клієнту.
 */
final readonly class Response
{
    /**
     * Заголовки з назвами в нижньому регістрі.
     *
     * @var array<string, string>
     */
    public array $headers;

    /**
     * @param int                                 $status  HTTP-статус
     * @param array<string, string|list<string>> $headers заголовки: назва → значення
     * @param string                              $body    тіло відповіді
     */
    public function __construct(
        public int $status,
        array $headers = [],
        public string $body = '',
    ) {
        $normalized = [];
        foreach ($headers as $name => $value) {
            $normalized[strtolower($name)] = is_array($value) ? implode(', ', $value) : $value;
        }
        $this->headers = $normalized;
    }

    /**
     * Повертає значення заголовка name без урахування регістру або null.
     */
    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }
}
