<?php

declare(strict_types=1);

namespace AresApi\Http;

use AresApi\Exception\ApiException;
use AresApi\Exception\InvalidResponseException;
use AresApi\Exception\NotFoundException;
use AresApi\Exception\RateLimitException;
use AresApi\Exception\RequestException;
use AresApi\Exception\TransportException;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Exception;
use JsonException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Implementation of the ApiTransportInterface that uses PSR-18 HTTP clients.
 */
final readonly class Psr18ApiTransport implements ApiTransportInterface
{
    /**
     * @var list<array{string, string}>
     */
    private const array HTTP_DATE_FORMATS = [
        [
            '/^[A-Z][a-z]{2}, \d{2} [A-Z][a-z]{2} \d{4} \d{2}:\d{2}:\d{2} GMT$/D',
            'D, d M Y H:i:s \G\M\T',
        ],
        [
            '/^[A-Z][a-z]+, \d{2}-[A-Z][a-z]{2}-\d{2} \d{2}:\d{2}:\d{2} GMT$/D',
            'l, d-M-y H:i:s \G\M\T',
        ],
        [
            '/^[A-Z][a-z]{2} [A-Z][a-z]{2}  \d \d{2}:\d{2}:\d{2} \d{4}$/D',
            'D M  j H:i:s Y',
        ],
        [
            '/^[A-Z][a-z]{2} [A-Z][a-z]{2} \d{2} \d{2}:\d{2}:\d{2} \d{4}$/D',
            'D M d H:i:s Y',
        ],
    ];

    /**
     * @param ClientInterface $httpClient The HTTP client instance used for making requests.
     * @param RequestFactoryInterface $requestFactory The factory instance for creating HTTP requests.
     * @param JsonResponseDecoderInterface $responseDecoder The decoder instance for handling JSON responses.
     */
    public function __construct(
        private ClientInterface $httpClient,
        private RequestFactoryInterface $requestFactory,
        private JsonResponseDecoderInterface $responseDecoder,
    ) {
    }

    /**
     * Executes the given API request and processes the response.
     *
     * @param ApiRequestInterface $request The API request to execute.
     *
     * @return array<string, mixed> The decoded response body as an associative array.
     *
     * @throws RequestException If the HTTP request cannot be constructed.
     * @throws TransportException If the request could not be completed due to a transport error.
     * @throws ApiException If ARES returns a non-successful HTTP response.
     * @throws InvalidResponseException If the response is not a valid JSON format or is invalid for other reasons.
     */
    public function execute(ApiRequestInterface $request): array
    {
        try {
            $psrRequest = $this->requestFactory->create($request);
        } catch (Exception $exception) {
            throw RequestException::fromFailure(
                null,
                $exception,
            );
        }

        try {
            $response = $this->httpClient->sendRequest($psrRequest);
        } catch (ClientExceptionInterface $exception) {
            $path = $psrRequest->getUri()->getPath();

            throw new TransportException(
                sprintf(
                    'The request to ARES endpoint "%s" could not be completed.',
                    $path === '' ? '<unknown>' : $path,
                ),
                $exception,
            );
        }

        $statusCode = $response->getStatusCode();
        $body = (string) $response->getBody();

        if ($statusCode < 200 || $statusCode >= 300) {
            $this->throwApiException($response, $body);
        }

        try {
            return $this->responseDecoder->decode($body);
        } catch (JsonException $exception) {
            throw new InvalidResponseException(
                sprintf(
                    'ARES returned an invalid JSON response with HTTP status %d.',
                    $statusCode,
                ),
                $body,
                $exception,
            );
        }
    }

    /**
     * Throws an appropriate API exception based on the given response and body.
     *
     * @param ResponseInterface $response The HTTP response instance.
     * @param string $body The raw response body to extract error details.
     *
     * @throws NotFoundException For HTTP 404.
     * @throws RateLimitException For HTTP 429.
     * @throws ApiException For every other non-successful HTTP response.
     */
    private function throwApiException(
        ResponseInterface $response,
        string $body,
    ): never {
        $details = $this->decodeErrorDetails($body);
        $statusCode = $response->getStatusCode();
        $apiCode = $this->stringValue($details, 'kod');
        $apiSubCode = $this->stringValue($details, 'subKod');
        $message = $this->stringValue($details, 'popis')
            ?? $this->fallbackErrorMessage($response);

        if ($statusCode === 404) {
            throw new NotFoundException(
                $statusCode,
                $message,
                $apiCode,
                $apiSubCode,
                $body,
            );
        }

        if ($statusCode === 429) {
            throw new RateLimitException(
                $statusCode,
                $message,
                $apiCode,
                $apiSubCode,
                $body,
                $this->retryAfterSeconds($response),
            );
        }

        throw new ApiException(
            $statusCode,
            $message,
            $apiCode,
            $apiSubCode,
            $body,
        );
    }

    /**
     * Decodes error details from the provided response body.
     *
     * @param string $body The response body to decode.
     *
     * @return array<string, mixed> The decoded error details, or an empty array if decoding fails.
     */
    private function decodeErrorDetails(string $body): array
    {
        try {
            return $this->responseDecoder->decode($body);
        } catch (JsonException) {
            return [];
        }
    }

    /**
     * Retrieves a string value associated with the specified key from the provided array.
     *
     * @param array<string, mixed> $values The values to inspect.
     * @param string $key The key whose associated value is to be retrieved.
     *
     * @return string|null The trimmed string value if the key exists and its value is a non-empty string, or null otherwise.
     */
    private function stringValue(array $values, string $key): ?string
    {
        $value = $values[$key] ?? null;
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * Generates a fallback error message based on the HTTP response.
     *
     * @param ResponseInterface $response The HTTP response object containing the status code and reason phrase.
     *
     * @return string A formatted error message including the status code and, if available, the reason phrase.
     */
    private function fallbackErrorMessage(ResponseInterface $response): string
    {
        $reasonPhrase = trim($response->getReasonPhrase());

        if ($reasonPhrase === '') {
            return sprintf(
                'ARES returned HTTP status %d.',
                $response->getStatusCode(),
            );
        }

        return sprintf(
            'ARES returned HTTP status %d: %s.',
            $response->getStatusCode(),
            $reasonPhrase,
        );
    }

    /**
     * Determines the delay represented by a numeric or HTTP-date Retry-After header.
     *
     * @param ResponseInterface $response The HTTP response containing the 'Retry-After' header.
     *
     * @return int|null The retry duration in seconds, or null if the header is invalid or absent.
     */
    private function retryAfterSeconds(ResponseInterface $response): ?int
    {
        $retryAfter = trim($response->getHeaderLine('Retry-After'));
        if ($retryAfter === '') {
            return null;
        }

        if (preg_match('/^\d+$/D', $retryAfter) === 1) {
            $seconds = filter_var(
                $retryAfter,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 0]],
            );

            return is_int($seconds) ? $seconds : null;
        }

        $retryAt = $this->httpDate($retryAfter);
        if ($retryAt === null) {
            return null;
        }

        return max(0, $retryAt->getTimestamp() - time());
    }

    /**
     * Parses one of the three HTTP-date formats accepted by HTTP recipients.
     *
     * @param string $value The Retry-After header value.
     *
     * @return DateTimeImmutable|null The parsed GMT date, or null when invalid.
     */
    private function httpDate(string $value): ?DateTimeImmutable
    {
        foreach (self::HTTP_DATE_FORMATS as [$pattern, $format]) {
            if (preg_match($pattern, $value) !== 1) {
                continue;
            }

            $leapSecond = preg_match(
                '/60(?= GMT$| \d{4}$)/D',
                $value,
            ) === 1;
            $parseValue = $leapSecond
                ? preg_replace(
                    '/60(?= GMT$| \d{4}$)/D',
                    '59',
                    $value,
                    1,
                )
                : $value;

            if ($parseValue === null) {
                return null;
            }

            $date = DateTimeImmutable::createFromFormat(
                '!' . $format,
                $parseValue,
                new DateTimeZone('GMT'),
            );

            if ($date !== false && $date->format($format) === $parseValue) {
                return $leapSecond
                    ? $date->add(new DateInterval('PT1S'))
                    : $date;
            }
        }

        return null;
    }
}
