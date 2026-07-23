<?php

declare(strict_types=1);

namespace AresApi\Http;

use AresApi\Configuration;
use AresApi\Exception\RequestException;
use InvalidArgumentException;
use JsonException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\RequestFactoryInterface as PsrRequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use RuntimeException;

/**
 * Creates PSR-7 requests from ARES API request definitions.
 */
final readonly class RequestFactory implements RequestFactoryInterface
{
    /**
     * Constructor method.
     *
     * @param PsrRequestFactoryInterface $requestFactory An instance of the PSR request factory.
     * @param StreamFactoryInterface $streamFactory An instance of the stream factory.
     * @param Configuration $configuration An instance of the configuration class.
     */
    public function __construct(
        private PsrRequestFactoryInterface $requestFactory,
        private StreamFactoryInterface $streamFactory,
        private Configuration $configuration,
    ) {
    }

    /**
     * Creates and returns a PSR-7 request instance based on the provided API request.
     *
     * @param ApiRequestInterface $request The ARES API request definition.
     *
     * @return RequestInterface The resulting PSR-7 request.
     *
     * @throws RequestException If the request cannot be constructed or its JSON payload cannot be encoded.
     */
    public function create(ApiRequestInterface $request): RequestInterface
    {
        $path = null;

        try {
            $path = $request->path();
            $psrRequest = $this->requestFactory
                ->createRequest($request->method(), $this->uri($path))
                ->withHeader('Accept', 'application/json')
                ->withHeader('User-Agent', $this->configuration->userAgent());

            foreach ($this->configuration->headers() as $name => $value) {
                $psrRequest = $psrRequest->withHeader($name, $value);
            }

            $payload = $request->json();
            if ($payload === null) {
                return $psrRequest;
            }

            $body = json_encode(
                $payload,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            );

            return $psrRequest
                ->withHeader('Content-Type', 'application/json')
                ->withBody($this->streamFactory->createStream($body));
        } catch (
            JsonException | InvalidArgumentException | RuntimeException $exception
        ) {
            throw RequestException::fromFailure(
                $path,
                $exception,
            );
        }
    }

    /**
     * Constructs and returns a complete URI by combining the base URI with the provided path.
     *
     * @param string $path The relative path to be appended to the base URI.
     *
     * @return string The complete URI.
     */
    private function uri(string $path): string
    {
        return rtrim($this->configuration->baseUri(), '/')
            . '/'
            . ltrim($path, '/');
    }
}
