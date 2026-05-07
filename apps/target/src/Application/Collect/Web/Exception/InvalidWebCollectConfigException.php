<?php

declare(strict_types=1);

namespace App\Application\Collect\Web\Exception;

/**
 * Thrown when a {@see App\Application\Collect\Web\WebCollectConfig} is built
 * from a CollectTask configuration array that violates its invariants
 * (no URL nor raw HTML, type mismatches, etc.). Callers wrap this as
 * `CollectException` (handler) or as a 4xx HTTP exception (API processor).
 */
class InvalidWebCollectConfigException extends \DomainException
{
}
