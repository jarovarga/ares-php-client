<?php

declare(strict_types=1);

namespace AresApi\Company\Request;

use AresApi\Http\ApiRequestInterface;
use AresApi\Http\Endpoint;
use AresApi\ValueObject\CompanyRegistrationNumber;

/**
 * Represents a request to retrieve a company by its registration number.
 */
final readonly class GetCompanyRequest implements ApiRequestInterface
{
    /**
     * Constructor method.
     *
     * @param CompanyRegistrationNumber $registrationNumber The company registration number instance.
     */
    public function __construct(
        private CompanyRegistrationNumber $registrationNumber,
    ) {
    }

    /**
     * Returns the HTTP method as a string.
     *
     * @return string The HTTP method.
     */
    public function method(): string
    {
        return 'GET';
    }

    /**
     * Constructs and returns the endpoint path as a string.
     *
     * @return string The constructed endpoint path.
     */
    public function path(): string
    {
        return Endpoint::Companies->value
            . '/'
            . rawurlencode((string) $this->registrationNumber);
    }

    /**
     * Indicates that the GET request has no JSON body.
     *
     * @return null This request has no JSON body.
     */
    public function json(): null
    {
        return null;
    }
}
