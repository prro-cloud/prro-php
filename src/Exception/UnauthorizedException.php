<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Exception;

/**
 * 401 (код unauthorized): токена немає, він недійсний, протермінований чи
 * відкликаний.
 */
final class UnauthorizedException extends ApiException {}
