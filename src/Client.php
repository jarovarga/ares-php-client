<?php

declare(strict_types=1);

namespace AresApi;

use AresApi\Company\CompanyResourceInterface;

/**
 * Client class.
 */
final readonly class Client implements ClientInterface
{
    /**
     * Constructor method.
     *
     * @param CompanyResourceInterface $companies Instance of CompanyResourceInterface.
     */
    public function __construct(
        private CompanyResourceInterface $companies,
    ) {
    }

    /**
     * Retrieves the companies' resource.
     *
     * @return CompanyResourceInterface Instance of CompanyResourceInterface.
     */
    public function companies(): CompanyResourceInterface
    {
        return $this->companies;
    }
}
