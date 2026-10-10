<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Exception;

/**
 * 403 (код forbidden): обмежений токен звернувся до ресурсу рівня клієнта
 * (білінг, вебхуки).
 */
final class ForbiddenException extends ApiException {}
