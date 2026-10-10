<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Exception;

/**
 * 404 (код not_found): ресурсу немає або токен не має до нього доступу.
 */
final class NotFoundException extends ApiException {}
