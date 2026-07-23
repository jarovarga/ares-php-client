<?php

declare(strict_types=1);

namespace AresApi\Exception;

use Throwable;

/**
 * Thrown when ARES returns HTTP 429 Too Many Requests.
 */
final class RateLimitException extends ApiException
{
    /**
     * Initialises a new instance of the class.
     *
     * @param int $statusCode The HTTP status code associated with the response.
     * @param string $message A message describing the response or error.
     * @param string|null $apiCode An optional code provided by the API to identify the type of error or response.
     * @param string|null $apiSubCode An optional sub-code provided by the API for more detailed identification.
     * @param string|null $responseBody The raw response body provided by the API.
     * @param int|null $retryAfterSeconds An optional value indicating the number of seconds to wait before retrying.
     * @param Throwable|null $previous An optional previous exception for exception chaining.
     */
    public function __construct(
        int $statusCode,
        string $message,
        ?string $apiCode = null,
        ?string $apiSubCode = null,
        ?string $responseBody = null,
        private readonly ?int $retryAfterSeconds = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            $statusCode,
            $message,
            $apiCode,
            $apiSubCode,
            $responseBody,
            $previous,
        );
    }

    /**
     * Retrieves the number of seconds to wait before retrying.
     *
     * @return int|null The number of seconds to retry after, or null if not set.
     */
    public function retryAfterSeconds(): ?int
    {
        return $this->retryAfterSeconds;
    }
}
