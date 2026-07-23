<?php

declare(strict_types=1);

namespace AresApi\Http;

use JsonException;

/**
 * Interface for classes that decode JSON responses into associative arrays.
 */
interface JsonResponseDecoderInterface
{
    /**
     * Decodes the provided string into an associative array.
     *
     * @param string $body The input string to decode.
     *
     * @return array<string, mixed> The decoded associative array.
     *
     * @throws JsonException If the input cannot be decoded, is not a JSON object, or contains a numeric top-level property name.
     */
    public function decode(string $body): array;
}
