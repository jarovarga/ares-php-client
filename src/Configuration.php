<?php

declare(strict_types=1);

namespace AresApi;

use AresApi\Exception\ValidationException;

/**
 * Represents the configuration settings for the ARES API client.
 */
final readonly class Configuration
{
    public const string DEFAULT_BASE_URI = 'https://ares.gov.cz/ekonomicke-subjekty-v-be/rest';
    public const string DEFAULT_USER_AGENT = 'ares-php-client';

    /**
     * @var list<string>
     */
    private const array MANAGED_HEADERS = [
        'accept',
        'content-length',
        'content-type',
        'host',
        'transfer-encoding',
        'user-agent',
    ];

    /**
     * @var string
     */
    private string $baseUri;

    /**
     * @var string
     */
    private string $userAgent;

    /**
     * @var array<string, string>
     */
    private array $headers;

    /**
     * Constructor for initialising the configuration of the client.
     *
     * @param string $baseUri The base URI used for making requests.
     * @param string $userAgent The user agent string to be sent with requests.
     * @param array<string, string> $headers Additional HTTP headers. Headers managed by the client are not allowed.
     *
     * @throws ValidationException If the base URI, user agent, or headers are invalid.
     */
    public function __construct(
        string $baseUri = self::DEFAULT_BASE_URI,
        string $userAgent = self::DEFAULT_USER_AGENT,
        array $headers = [],
    ) {
        $baseUri = rtrim($baseUri, '/');
        $parts = parse_url($baseUri);

        if (
            filter_var($baseUri, FILTER_VALIDATE_URL) === false
            || !is_array($parts)
            || !isset($parts['scheme'], $parts['host'])
            || !in_array(strtolower($parts['scheme']), ['http', 'https'], true)
            || isset($parts['query'])
            || isset($parts['fragment'])
        ) {
            throw new ValidationException(
                'The ARES base URI must be an absolute HTTP(S) URL without a query or fragment.',
            );
        }

        if (self::containsInvalidHeaderValueCharacter($userAgent)) {
            throw new ValidationException(
                'The user agent must be non-empty and contain only valid HTTP header-value characters.',
            );
        }

        $userAgent = trim($userAgent);
        if ($userAgent === '') {
            throw new ValidationException(
                'The user agent must be non-empty and contain only valid HTTP header-value characters.',
            );
        }

        foreach ($headers as $name => $value) {
            if (
                !is_string($name)
                || preg_match("/^[!#$%&'*+.^_`|~0-9A-Za-z-]+$/D", $name) !== 1
            ) {
                throw new ValidationException(sprintf(
                    'Invalid HTTP header name "%s".',
                    is_scalar($name) ? (string) $name : get_debug_type($name),
                ));
            }

            if (in_array(strtolower($name), self::MANAGED_HEADERS, true)) {
                throw new ValidationException(sprintf(
                    'HTTP header "%s" is managed by the ARES client and cannot be configured explicitly.',
                    $name,
                ));
            }

            if (
                !is_string($value)
                || self::containsInvalidHeaderValueCharacter($value)
            ) {
                throw new ValidationException(sprintf(
                    'The value of HTTP header "%s" must be a string containing only valid HTTP header-value characters.',
                    $name,
                ));
            }
        }

        $this->baseUri = $baseUri;
        $this->userAgent = $userAgent;
        $this->headers = $headers;
    }

    /**
     * Determines whether an HTTP header value contains a forbidden control character.
     *
     * The horizontal tab is permitted by the HTTP field-value grammar. Other control
     * characters, including DEL, are rejected.
     *
     * @param string $value The HTTP header value to inspect.
     *
     * @return bool True when the value contains a forbidden character.
     */
    private static function containsInvalidHeaderValueCharacter(
        string $value,
    ): bool {
        return preg_match('/[\x00-\x08\x0A-\x1F\x7F]/', $value) === 1;
    }

    /**
     * Retrieves the base URI used for making requests.
     *
     * @return string The base URI as an absolute HTTP(S) URL.
     */
    public function baseUri(): string
    {
        return $this->baseUri;
    }

    /**
     * Retrieves the user agent string used by the client.
     *
     * @return string The user agent string configured for the client.
     */
    public function userAgent(): string
    {
        return $this->userAgent;
    }

    /**
     * Retrieves the HTTP headers configured for the client.
     *
     * @return array<string, string> The additional HTTP headers configured for the client.
     */
    public function headers(): array
    {
        return $this->headers;
    }
}
