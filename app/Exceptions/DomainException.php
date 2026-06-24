<?php

namespace App\Exceptions;

use Exception;

class DomainException extends Exception
{
    public readonly string $errorCode;

    public function __construct(
        string $message,
        public readonly int $statusCode,
        string $code,
        public readonly ?array $details = null,
    ) {
        parent::__construct($message);
        $this->errorCode = $code;
    }
}

class ConflictException extends DomainException
{
    public function __construct(string $message, string $code, ?array $details = null)
    {
        parent::__construct($message, 409, $code, $details);
    }
}

class NotFoundException extends DomainException
{
    public function __construct(string $message, string $code, ?array $details = null)
    {
        parent::__construct($message, 404, $code, $details);
    }
}

class ValidationException extends DomainException
{
    public function __construct(string $message, string $code, ?array $details = null)
    {
        parent::__construct($message, 422, $code, $details);
    }
}
