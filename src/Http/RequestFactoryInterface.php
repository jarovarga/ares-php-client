<?php

declare(strict_types=1);

namespace AresApi\Http;

use AresApi\Exception\RequestException;
use Psr\Http\Message\RequestInterface;

/**
 * Interface for classes that create PSR-7 requests.
 */
interface RequestFactoryInterface
{
    /**
     * Creates and returns a new request interface instance based on the provided API request.
     *
     * @param ApiRequestInterface $request The API request object used to create the new request.
     *
     * @return RequestInterface The newly created request interface instance.
     *
     * @throws RequestException If the request cannot be constructed.
     */
    public function create(ApiRequestInterface $request): RequestInterface;
}
