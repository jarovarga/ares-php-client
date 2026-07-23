<?php

declare(strict_types=1);

namespace AresApi\Exception;

use Throwable;

/**
 * Represents an error response from the ARES API.
 */
class ApiException extends AresException
{
    /**
     * Constructor for the class.
     *
     * @param int $statusCode The HTTP status code related to the exception.
     * @param string $message The error message describing the exception.
     * @param string|null $apiCode An optional API-specific error code.
     * @param string|null $apiSubCode An optional API-specific sub-error code.
     * @param string|null $responseBody The optional response body associated with the error.
     * @param Throwable|null $previous The previous throwable used for exception chaining.
     */
    public function __construct(
        private readonly int $statusCode,
        string $message,
        private readonly ?string $apiCode = null,
        private readonly ?string $apiSubCode = null,
        private readonly ?string $responseBody = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode, $previous);
    }

    /**
     * Retrieves the HTTP status code associated with the instance.
     *
     * @return int The HTTP status code.
     */
    public function statusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Retrieves the API-specific error code.
     *
     * @return string|null The API code if available, or null if not set.
     */
    public function apiCode(): ?string
    {
        return $this->apiCode;
    }

    /**
     * Retrieves the API-specific sub-error code.
     *
     * @return string|null The API sub-error code if available, or null if not set.
     */
    public function apiSubCode(): ?string
    {
        return $this->apiSubCode;
    }

    /**
     * Retrieves the response body associated with the object.
     *
     * @return string|null The response body, or null if not set.
     */
    public function responseBody(): ?string
    {
        return $this->responseBody;
    }
}
