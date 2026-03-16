<?php

namespace FQL\Client\Exception;

class ValidationException extends ClientException
{
    /** @var array<string, mixed> */
    private array $errors;

    /**
     * @param string $message
     * @param array<string, mixed> $errors
     */
    public function __construct(string $message, array $errors = [])
    {
        parent::__construct($message);
        $this->errors = $errors;
    }

    /**
     * @return array<string, mixed>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
