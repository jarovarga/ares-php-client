<?php

declare(strict_types=1);

namespace AresApi;

use AresApi\Company\CompanyResourceInterface;

/**
 * Interface for the client.
 */
interface ClientInterface
{
    /**
     * Retrieves the company resource interface instance.
     *
     * @return CompanyResourceInterface The company resource interface.
     */
    public function companies(): CompanyResourceInterface;
}
