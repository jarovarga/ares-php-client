<?php

declare(strict_types=1);

namespace AresApi\Exception;

use Throwable;

/**
 * Thrown when an ARES response cannot be decoded or mapped to the expected structure.
 */
final class InvalidResponseException extends AresException
{
    /**
     * Constructor method.
     *
     * @param string $message The error message.
     * @param string|null $responseBody The optional response body associated with the error.
     * @param Throwable|null $previous The previous throwable used for exception chaining.
     */
    public function __construct(
        string $message,
        private readonly ?string $responseBody = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * Retrieves the response body associated with the instance.
     *
     * @return string|null The response body if available, or null otherwise.
     */
    public function responseBody(): ?string
    {
        return $this->responseBody;
    }
}
