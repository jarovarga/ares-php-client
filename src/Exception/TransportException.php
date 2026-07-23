<?php

declare(strict_types=1);

namespace AresApi\Exception;

use Throwable;

/**
 * Represents an error during the transport of a request.
 */
final class TransportException extends AresException
{
    /**
     * Constructor method for the class.
     *
     * @param string $message The error message.
     * @param Throwable $previous The previous throwable used for exception chaining.
     */
    public function __construct(string $message, Throwable $previous)
    {
        parent::__construct($message, 0, $previous);
    }
}
