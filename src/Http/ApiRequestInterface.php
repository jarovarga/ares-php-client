<?php

declare(strict_types=1);

namespace AresApi\Http;

/**
 * Represents an API request.
 */
interface ApiRequestInterface
{
    /**
     * @return string
     */
    public function method(): string;

    /**
     * @return string
     */
    public function path(): string;

    /**
     * Converts the current instance or data into a JSON-serializable array.
     *
     * @return array<string, mixed>|null The JSON-serializable payload, or null when the request has no body.
     */
    public function json(): ?array;
}
