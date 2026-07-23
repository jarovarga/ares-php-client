<?php

declare(strict_types=1);

namespace AresApi\Http;

use AresApi\Exception\ApiException;
use AresApi\Exception\InvalidResponseException;
use AresApi\Exception\RequestException;
use AresApi\Exception\TransportException;

/**
 * Represents an API transport.
 */
interface ApiTransportInterface
{
    /**
     * Executes the given API request and processes the response.
     *
     * @param ApiRequestInterface $request The API request to be executed.
     *
     * @return array<string, mixed> The decoded response object.
     *
     * @throws RequestException If the HTTP request cannot be constructed.
     * @throws TransportException If the HTTP request cannot be completed.
     * @throws ApiException If ARES returns a non-successful HTTP response.
     * @throws InvalidResponseException If a successful response cannot be decoded.
     */
    public function execute(ApiRequestInterface $request): array;
}
