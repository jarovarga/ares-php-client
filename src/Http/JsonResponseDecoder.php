<?php

declare(strict_types=1);

namespace AresApi\Http;

use JsonException;

/**
 * Decodes JSON responses into associative arrays.
 */
final class JsonResponseDecoder implements JsonResponseDecoderInterface
{
    /**
     * Decodes a JSON string into an associative array.
     *
     * @param string $body The JSON-encoded string to decode. It must represent a JSON object.
     *
     * @return array<string, mixed> The decoded associative array.
     *
     * @throws JsonException If the input cannot be decoded, is not a JSON object, or contains a numeric top-level property name.
     */
    public function decode(string $body): array
    {
        if (!str_starts_with(ltrim($body), '{')) {
            throw new JsonException('The response body must contain a JSON object.');
        }

        $decoded = json_decode(
            $body,
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        if (!is_array($decoded)) {
            throw new JsonException('The response body must contain a JSON object.');
        }

        foreach (array_keys($decoded) as $key) {
            if (!is_string($key)) {
                throw new JsonException(
                    'The response body must contain a JSON object with non-numeric property names.',
                );
            }
        }

        return $decoded;
    }
}
