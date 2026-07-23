<?php

declare(strict_types=1);

namespace AresApi\Exception;

use Throwable;

/**
 * Thrown when an HTTP request for ARES cannot be constructed.
 */
final class RequestException extends AresException
{
    /**
     * Creates an idempotent package exception for a request-construction failure.
     *
     * @param string|null $endpoint The endpoint path, or null when it could not be resolved.
     * @param Throwable $previous The request-construction failure.
     *
     * @return self The existing request exception or a newly constructed one.
     */
    public static function fromFailure(
        ?string $endpoint,
        Throwable $previous,
    ): self {
        if ($previous instanceof self) {
            return $previous;
        }

        return new self(
            sprintf(
                'The request to ARES endpoint "%s" could not be constructed.',
                $endpoint === null || $endpoint === ''
                    ? '<unknown>'
                    : $endpoint,
            ),
            $previous,
        );
    }

    /**
     * Initialises a request-construction exception.
     *
     * @param string $message The error message.
     * @param Throwable $previous The request-construction failure.
     */
    public function __construct(string $message, Throwable $previous)
    {
        parent::__construct($message, 0, $previous);
    }
}
