<?php

declare(strict_types=1);

namespace AresApi;

use AresApi\Company\CompanyResource;
use AresApi\Company\Mapper\AddressMapper;
use AresApi\Company\Mapper\CompanyMapper;
use AresApi\Company\Mapper\CompanySearchResultMapper;
use AresApi\Http\JsonResponseDecoder;
use AresApi\Http\Psr18ApiTransport;
use AresApi\Http\RequestFactory;
use Psr\Http\Client\ClientInterface as PsrClientInterface;
use Psr\Http\Message\RequestFactoryInterface as PsrRequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Factory class for creating Client instances.
 */
final readonly class ClientFactory
{
    /**
     * Constructor method.
     *
     * @param PsrClientInterface $httpClient Client used to send HTTP requests.
     * @param PsrRequestFactoryInterface $requestFactory Factory to create HTTP requests.
     * @param StreamFactoryInterface $streamFactory Factory to create stream instances.
     */
    public function __construct(
        private PsrClientInterface $httpClient,
        private PsrRequestFactoryInterface $requestFactory,
        private StreamFactoryInterface $streamFactory,
    ) {
    }

    /**
     * Creates and returns a new Client instance.
     *
     * @param Configuration|null $configuration Optional configuration instance. If null, a default configuration will be used.
     *
     * @return ClientInterface The created client instance.
     */
    public function create(?Configuration $configuration = null): ClientInterface
    {
        $configuration ??= new Configuration();

        $requestFactory = new RequestFactory(
            $this->requestFactory,
            $this->streamFactory,
            $configuration,
        );
        $transport = new Psr18ApiTransport(
            $this->httpClient,
            $requestFactory,
            new JsonResponseDecoder(),
        );

        $companyMapper = new CompanyMapper(new AddressMapper());
        $companies = new CompanyResource(
            $transport,
            $companyMapper,
            new CompanySearchResultMapper($companyMapper),
        );

        return new Client($companies);
    }
}
