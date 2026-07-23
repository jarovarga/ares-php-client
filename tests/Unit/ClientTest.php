<?php

declare(strict_types=1);

namespace AresApi\Tests\Unit;

use AresApi\Client;
use AresApi\ClientFactory;
use AresApi\Company\CompanyResourceInterface;
use Psr\Http\Client\ClientInterface as PsrClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Client::class)]
#[CoversClass(ClientFactory::class)]
final class ClientTest extends TestCase
{
    public function testItExposesTheInjectedCompanyResource(): void
    {
        $companies = $this->createStub(CompanyResourceInterface::class);
        $client = new Client($companies);

        self::assertSame($companies, $client->companies());
    }

    public function testFactoryComposesAClientFromPsrDependencies(): void
    {
        $factory = new ClientFactory(
            $this->createStub(PsrClientInterface::class),
            $this->createStub(RequestFactoryInterface::class),
            $this->createStub(StreamFactoryInterface::class),
        );

        $client = $factory->create();

        self::assertInstanceOf(Client::class, $client);
        self::assertInstanceOf(
            CompanyResourceInterface::class,
            $client->companies(),
        );
    }
}
