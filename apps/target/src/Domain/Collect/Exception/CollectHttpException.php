<?php

namespace App\Domain\Collect\Exception;

use App\Domain\Shared\DomainException;
use Symfony\Contracts\HttpClient\ResponseInterface;

class CollectHttpException extends DomainException
{
    public function __construct(
        public readonly ResponseInterface $response,
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
