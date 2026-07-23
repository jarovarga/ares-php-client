<?php

declare(strict_types=1);

namespace AresApi\Company\Request;

use AresApi\Company\Query\CompanySearchQuery;
use AresApi\Http\ApiRequestInterface;
use AresApi\Http\Endpoint;
use AresApi\ValueObject\CompanyRegistrationNumber;

/**
 * Represents a request to search for companies based on various criteria.
 */
final readonly class SearchCompaniesRequest implements ApiRequestInterface
{
    /**
     * Constructor method for initialising the class with a CompanySearchQuery object.
     *
     * @param CompanySearchQuery $query An instance of the CompanySearchQuery class.
     */
    public function __construct(
        private CompanySearchQuery $query,
    ) {
    }

    /**
     * Retrieves the HTTP request method.
     *
     * @return string The HTTP request method, e.g., 'POST'.
     */
    public function method(): string
    {
        return 'POST';
    }

    /**
     * Retrieves the API endpoint path for searching companies.
     *
     * @return string The endpoint path for the search companies functionality.
     */
    public function path(): string
    {
        return Endpoint::SearchCompanies->value;
    }

    /**
     * Generates a JSON-serializable array representation of the query parameters.
     *
     * @return array<string, mixed> The structured JSON request payload.
     */
    public function json(): array
    {
        $payload = [
            'start' => $this->query->page()->offset(),
            'pocet' => $this->query->page()->size(),
        ];

        $registrationNumbers = $this->query->registrationNumbers();
        if ($registrationNumbers !== []) {
            $payload['ico'] = array_map(
                static fn (CompanyRegistrationNumber $number): string => (string) $number,
                $registrationNumbers,
            );
        }

        $businessName = $this->query->businessName();
        if ($businessName !== null) {
            $payload['obchodniJmeno'] = $businessName;
        }

        $address = $this->query->addressText();
        if ($address !== null) {
            $payload['sidlo'] = [
                'textovaAdresa' => $address,
            ];
        }

        $this->addList($payload, 'pravniForma', $this->query->legalFormCodes());
        $this->addList($payload, 'pravniFormaRos', $this->query->rosLegalFormCodes());
        $this->addList($payload, 'financniUrad', $this->query->taxOfficeCodes());
        $this->addList($payload, 'czNace', $this->query->czNaceCodes());

        return $payload;
    }

    /**
     * Adds a list of values to the payload under the specified key if the list is not empty.
     *
     * @param array<string, mixed> $payload The payload to which the list will be added, passed by reference.
     * @param string $key The key under which the list of values will be added.
     * @param list<string> $values The list of values to add to the payload.
     *
     * @return void
     */
    private function addList(array &$payload, string $key, array $values): void
    {
        if ($values !== []) {
            $payload[$key] = $values;
        }
    }
}
