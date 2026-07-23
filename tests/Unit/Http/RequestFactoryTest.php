<?php

declare(strict_types=1);

namespace AresApi\Tests\Unit\Http;

use AresApi\Configuration;
use AresApi\Company\Request\GetCompanyRequest;
use AresApi\Exception\RequestException;
use AresApi\Exception\ValidationException;
use AresApi\Http\ApiRequestInterface;
use AresApi\Http\RequestFactory;
use AresApi\ValueObject\CompanyRegistrationNumber;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\RequestFactoryInterface as PsrRequestFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\StreamFactoryInterface;
use RuntimeException;

#[CoversClass(Configuration::class)]
#[CoversClass(RequestFactory::class)]
#[CoversClass(RequestException::class)]
final class RequestFactoryTest extends TestCase
{
    public function testItBuildsAGetRequestWithConfiguredHeaders(): void
    {
        $psrRequest = $this->createMock(RequestInterface::class);
        $headers = [];
        $psrRequest
            ->expects(self::exactly(3))
            ->method('withHeader')
            ->willReturnCallback(static function (
                string $name,
                mixed $value,
            ) use (&$headers, $psrRequest): RequestInterface {
                $headers[$name] = $value;

                return $psrRequest;
            });

        $psrFactory = $this->createMock(PsrRequestFactoryInterface::class);
        $psrFactory
            ->expects(self::once())
            ->method('createRequest')
            ->with(
                'GET',
                'https://example.test/ares/ekonomicke-subjekty/27074358',
            )
            ->willReturn($psrRequest);

        $streamFactory = $this->createMock(StreamFactoryInterface::class);
        $streamFactory
            ->expects(self::never())
            ->method('createStream');

        $apiRequest = new GetCompanyRequest(
            new CompanyRegistrationNumber('27074358'),
        );

        $factory = new RequestFactory(
            $psrFactory,
            $streamFactory,
            new Configuration(
                baseUri: 'https://example.test/ares/',
                userAgent: 'test-client/1.0',
                headers: ['X-Application' => 'unit-tests'],
            ),
        );

        self::assertSame($psrRequest, $factory->create($apiRequest));
        self::assertSame([
            'Accept' => 'application/json',
            'User-Agent' => 'test-client/1.0',
            'X-Application' => 'unit-tests',
        ], $headers);
    }

    public function testItEncodesAJsonBodyAsUtf8WithoutEscapedSlashes(): void
    {
        $psrRequest = $this->createMock(RequestInterface::class);
        $psrRequest
            ->expects(self::exactly(3))
            ->method('withHeader')
            ->willReturn($psrRequest);

        $stream = $this->createStub(StreamInterface::class);
        $psrRequest
            ->expects(self::once())
            ->method('withBody')
            ->with($stream)
            ->willReturn($psrRequest);

        $psrFactory = $this->createStub(PsrRequestFactoryInterface::class);
        $psrFactory
            ->method('createRequest')
            ->willReturn($psrRequest);

        $streamFactory = $this->createMock(StreamFactoryInterface::class);
        $streamFactory
            ->expects(self::once())
            ->method('createStream')
            ->with('{"name":"Škoda","url":"https://example.test"}')
            ->willReturn($stream);

        $apiRequest = new RequestFactoryTestRequest(
            'POST',
            '/search',
            [
                'name' => 'Škoda',
                'url' => 'https://example.test',
            ],
        );

        $factory = new RequestFactory(
            $psrFactory,
            $streamFactory,
            new Configuration(),
        );

        self::assertSame($psrRequest, $factory->create($apiRequest));
    }

    public function testConfigurationExposesNormalizedDefaults(): void
    {
        $configuration = new Configuration();

        self::assertSame(Configuration::DEFAULT_BASE_URI, $configuration->baseUri());
        self::assertSame(Configuration::DEFAULT_USER_AGENT, $configuration->userAgent());
        self::assertSame([], $configuration->headers());
    }

    public function testItWrapsAJsonEncodingFailure(): void
    {
        $psrRequest = $this->createStub(RequestInterface::class);
        $psrRequest->method('withHeader')->willReturn($psrRequest);

        $psrFactory = $this->createStub(PsrRequestFactoryInterface::class);
        $psrFactory
            ->method('createRequest')
            ->willReturn($psrRequest);

        $factory = new RequestFactory(
            $psrFactory,
            $this->createStub(StreamFactoryInterface::class),
            new Configuration(),
        );

        try {
            $factory->create(new RequestFactoryTestRequest(
                'POST',
                '/search',
                ['invalidUtf8' => "\xB1\x31"],
            ));
            self::fail('A request exception was not thrown.');
        } catch (RequestException $exception) {
            self::assertInstanceOf(JsonException::class, $exception->getPrevious());
            self::assertStringContainsString('/search', $exception->getMessage());
        }
    }

    public function testItDoesNotCallAThrowingRequestPathTwice(): void
    {
        $psrFactory = $this->createMock(PsrRequestFactoryInterface::class);
        $psrFactory
            ->expects(self::never())
            ->method('createRequest');

        $apiRequest = new class () implements ApiRequestInterface {
            public int $pathCalls = 0;

            public function method(): string
            {
                return 'GET';
            }

            public function path(): string
            {
                ++$this->pathCalls;

                throw new RuntimeException('Unable to resolve path');
            }

            public function json(): ?array
            {
                return null;
            }
        };

        $factory = new RequestFactory(
            $psrFactory,
            $this->createStub(StreamFactoryInterface::class),
            new Configuration(),
        );

        try {
            $factory->create($apiRequest);
            self::fail('A request exception was not thrown.');
        } catch (RequestException $exception) {
            self::assertInstanceOf(
                RuntimeException::class,
                $exception->getPrevious(),
            );
            self::assertStringContainsString(
                '<unknown>',
                $exception->getMessage(),
            );
            self::assertSame(1, $apiRequest->pathCalls);
        }
    }

    #[DataProvider('invalidConfigurationProvider')]
    public function testConfigurationRejectsInvalidValues(
        string $baseUri,
        string $userAgent,
        array $headers,
    ): void {
        $this->expectException(ValidationException::class);

        new Configuration($baseUri, $userAgent, $headers);
    }

    /**
     * @return iterable<string, array{string, string, array}>
     */
    public static function invalidConfigurationProvider(): iterable
    {
        yield 'relative base URI' => [
            '/ares',
            'client',
            [],
        ];
        yield 'base URI query' => [
            'https://example.test/ares?version=1',
            'client',
            [],
        ];
        yield 'empty user agent' => [
            'https://example.test',
            ' ',
            [],
        ];
        yield 'user agent with null byte' => [
            'https://example.test',
            "client\0",
            [],
        ];
        yield 'header injection' => [
            'https://example.test',
            'client',
            ['X-Test' => "valid\r\nInjected: yes"],
        ];
        yield 'header with null byte' => [
            'https://example.test',
            'client',
            ['X-Test' => "value\0"],
        ];
        yield 'header with delete character' => [
            'https://example.test',
            'client',
            ['X-Test' => "value\x7F"],
        ];
        yield 'invalid header name' => [
            'https://example.test',
            'client',
            ['Invalid Header' => 'value'],
        ];
        yield 'managed Accept header' => [
            'https://example.test',
            'client',
            ['Accept' => 'text/plain'],
        ];
        yield 'managed Content-Length header' => [
            'https://example.test',
            'client',
            ['Content-Length' => '0'],
        ];
        yield 'managed Content-Type header' => [
            'https://example.test',
            'client',
            ['Content-Type' => 'text/plain'],
        ];
        yield 'managed Host header is case insensitive' => [
            'https://example.test',
            'client',
            ['hOsT' => 'other.test'],
        ];
        yield 'managed Transfer-Encoding header' => [
            'https://example.test',
            'client',
            ['Transfer-Encoding' => 'chunked'],
        ];
        yield 'managed User-Agent header' => [
            'https://example.test',
            'client',
            ['User-Agent' => 'other-client'],
        ];
    }
}

final readonly class RequestFactoryTestRequest implements ApiRequestInterface
{
    /**
     * @param array<string, mixed>|null $json
     */
    public function __construct(
        private string $method,
        private string $path,
        private ?array $json,
    ) {
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function json(): ?array
    {
        return $this->json;
    }
}
