<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Exception;

/**
 * 422 (код validation_failed): запит не пройшов валідацію; подробиці — у
 * {@see ApiException::$details}.
 */
final class ValidationException extends ApiException {}
