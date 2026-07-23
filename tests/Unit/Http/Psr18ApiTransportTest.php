<?php

declare(strict_types=1);

namespace AresApi\Tests\Unit\Http;

use AresApi\Configuration;
use AresApi\Company\Request\GetCompanyRequest;
use AresApi\Exception\ApiException;
use AresApi\Exception\InvalidResponseException;
use AresApi\Exception\NotFoundException;
use AresApi\Exception\RateLimitException;
use AresApi\Exception\RequestException;
use AresApi\Exception\TransportException;
use AresApi\Http\ApiRequestInterface;
use AresApi\Http\JsonResponseDecoder;
use AresApi\Http\Psr18ApiTransport;
use AresApi\Http\RequestFactory;
use AresApi\Http\RequestFactoryInterface;
use AresApi\ValueObject\CompanyRegistrationNumber;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\RequestFactoryInterface as PsrRequestFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UriInterface;
use RuntimeException;

#[CoversClass(Psr18ApiTransport::class)]
#[CoversClass(RequestException::class)]
final class Psr18ApiTransportTest extends TestCase
{
    public function testItReturnsADecodedSuccessfulResponse(): void
    {
        $transport = $this->transportReturning(
            $this->response(200, '{"ico":"27074358"}'),
        );

        self::assertSame(
            ['ico' => '27074358'],
            $transport->execute($this->apiRequest()),
        );
    }

    public function testItWrapsAnInvalidSuccessfulResponse(): void
    {
        $body = '<html lang="en">temporary proxy response</html>';
        $transport = $this->transportReturning($this->response(200, $body));

        try {
            $transport->execute($this->apiRequest());
            self::fail('An invalid response exception was not thrown.');
        } catch (InvalidResponseException $exception) {
            self::assertSame($body, $exception->responseBody());
            self::assertStringContainsString('HTTP status 200', $exception->getMessage());
        }
    }

    /**
     * @throws JsonException
     */
    public function testItMapsA404ResponseToNotFound(): void
    {
        $body = json_encode([
            'kod' => 'NENALEZENO',
            'popis' => 'The company was not found.',
            'subKod' => 'VYSTUP_SUBJEKT_NENALEZEN',
        ], JSON_THROW_ON_ERROR);
        $transport = $this->transportReturning($this->response(404, $body));

        try {
            $transport->execute($this->apiRequest());
            self::fail('A not-found exception was not thrown.');
        } catch (NotFoundException $exception) {
            self::assertSame(404, $exception->statusCode());
            self::assertSame('NENALEZENO', $exception->apiCode());
            self::assertSame(
                'VYSTUP_SUBJEKT_NENALEZEN',
                $exception->apiSubCode(),
            );
            self::assertSame('The company was not found.', $exception->getMessage());
            self::assertSame($body, $exception->responseBody());
        }
    }

    public function testItMapsA429ResponseAndNumericRetryAfter(): void
    {
        $transport = $this->transportReturning($this->response(
            429,
            '{"kod":"PRILIS_MNOHO_POZADAVKU","popis":"Slow down."}',
            retryAfter: '15',
        ));

        try {
            $transport->execute($this->apiRequest());
            self::fail('A rate-limit exception was not thrown.');
        } catch (RateLimitException $exception) {
            self::assertSame(429, $exception->statusCode());
            self::assertSame('PRILIS_MNOHO_POZADAVKU', $exception->apiCode());
            self::assertSame(15, $exception->retryAfterSeconds());
        }
    }

    public function testItRejectsANaturalLanguageRetryAfterValue(): void
    {
        $transport = $this->transportReturning($this->response(
            429,
            '{"kod":"PRILIS_MNOHO_POZADAVKU","popis":"Slow down."}',
            retryAfter: 'tomorrow',
        ));

        try {
            $transport->execute($this->apiRequest());
            self::fail('A rate-limit exception was not thrown.');
        } catch (RateLimitException $exception) {
            self::assertNull($exception->retryAfterSeconds());
        }
    }

    #[DataProvider('httpDateProvider')]
    public function testItAcceptsAnHttpDateRetryAfterValue(
        string $retryAfter,
    ): void {
        $transport = $this->transportReturning($this->response(
            429,
            '{"kod":"PRILIS_MNOHO_POZADAVKU","popis":"Slow down."}',
            retryAfter: $retryAfter,
        ));

        try {
            $transport->execute($this->apiRequest());
            self::fail('A rate-limit exception was not thrown.');
        } catch (RateLimitException $exception) {
            self::assertSame(0, $exception->retryAfterSeconds());
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function httpDateProvider(): iterable
    {
        yield 'IMF-fixdate' => ['Sun, 06 Nov 1994 08:49:37 GMT'];
        yield 'obsolete RFC 850 date' => [
            'Sunday, 06-Nov-94 08:49:37 GMT',
        ];
        yield 'obsolete asctime date' => ['Sun Nov  6 08:49:37 1994'];
        yield 'HTTP leap second' => ['Sat, 31 Dec 2016 23:59:60 GMT'];
    }

    public function testItUsesTheHttpReasonForANonJsonApiFailure(): void
    {
        $body = 'Service unavailable';
        $transport = $this->transportReturning(
            $this->response(503, $body, 'Service Unavailable'),
        );

        try {
            $transport->execute($this->apiRequest());
            self::fail('An API exception was not thrown.');
        } catch (ApiException $exception) {
            self::assertSame(503, $exception->statusCode());
            self::assertSame(
                'ARES returned HTTP status 503: Service Unavailable.',
                $exception->getMessage(),
            );
            self::assertNull($exception->apiCode());
            self::assertSame($body, $exception->responseBody());
        }
    }

    public function testItWrapsAPsr18ClientFailure(): void
    {
        $client = $this->createMock(ClientInterface::class);
        $failure = new PsrClientFailure('Network unavailable');
        $client
            ->expects(self::once())
            ->method('sendRequest')
            ->willThrowException($failure);

        try {
            $this->transport($client)->execute($this->apiRequest());
            self::fail('A transport exception was not thrown.');
        } catch (TransportException $exception) {
            self::assertSame($failure, $exception->getPrevious());
            self::assertStringContainsString(
                '/ekonomicke-subjekty/27074358',
                $exception->getMessage(),
            );
        }
    }

    public function testItWrapsAThirdPartyRequestFactoryFailure(): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client
            ->expects(self::never())
            ->method('sendRequest');

        $failure = new RuntimeException('Unable to build request');
        $requestFactory = $this->createStub(RequestFactoryInterface::class);
        $requestFactory
            ->method('create')
            ->willThrowException($failure);

        $transport = new Psr18ApiTransport(
            $client,
            $requestFactory,
            new JsonResponseDecoder(),
        );

        try {
            $transport->execute($this->apiRequest());
            self::fail('A request exception was not thrown.');
        } catch (RequestException $exception) {
            self::assertSame($failure, $exception->getPrevious());
            self::assertStringContainsString('<unknown>', $exception->getMessage());
        }
    }

    public function testItDoesNotWrapARequestExceptionTwice(): void
    {
        $client = $this->createMock(ClientInterface::class);
        $client
            ->expects(self::never())
            ->method('sendRequest');

        $failure = new RequestException(
            'The request is already wrapped.',
            new RuntimeException('Original failure'),
        );
        $requestFactory = $this->createStub(RequestFactoryInterface::class);
        $requestFactory
            ->method('create')
            ->willThrowException($failure);

        $transport = new Psr18ApiTransport(
            $client,
            $requestFactory,
            new JsonResponseDecoder(),
        );

        try {
            $transport->execute($this->apiRequest());
            self::fail('A request exception was not thrown.');
        } catch (RequestException $exception) {
            self::assertSame($failure, $exception);
        }
    }

    private function transportReturning(
        ResponseInterface $response,
    ): Psr18ApiTransport {
        $client = $this->createMock(ClientInterface::class);
        $client
            ->expects(self::once())
            ->method('sendRequest')
            ->willReturn($response);

        return $this->transport($client);
    }

    private function transport(ClientInterface $client): Psr18ApiTransport
    {
        $request = $this->createStub(RequestInterface::class);
        $request->method('withHeader')->willReturn($request);

        $uri = $this->createStub(UriInterface::class);
        $uri
            ->method('getPath')
            ->willReturn('/ekonomicke-subjekty/27074358');
        $request
            ->method('getUri')
            ->willReturn($uri);

        $psrRequestFactory = $this->createStub(PsrRequestFactoryInterface::class);
        $psrRequestFactory
            ->method('createRequest')
            ->willReturn($request);

        return new Psr18ApiTransport(
            $client,
            new RequestFactory(
                $psrRequestFactory,
                $this->createStub(StreamFactoryInterface::class),
                new Configuration(),
            ),
            new JsonResponseDecoder(),
        );
    }

    private function apiRequest(): ApiRequestInterface
    {
        return new GetCompanyRequest(
            new CompanyRegistrationNumber('27074358'),
        );
    }

    private function response(
        int $statusCode,
        string $body,
        string $reasonPhrase = '',
        string $retryAfter = '',
    ): ResponseInterface {
        $stream = $this->createStub(StreamInterface::class);
        $stream->method('__toString')->willReturn($body);

        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn($statusCode);
        $response->method('getBody')->willReturn($stream);
        $response->method('getReasonPhrase')->willReturn($reasonPhrase);
        $response
            ->method('getHeaderLine')
            ->willReturn($retryAfter);

        return $response;
    }
}

final class PsrClientFailure extends RuntimeException implements ClientExceptionInterface
{
}
