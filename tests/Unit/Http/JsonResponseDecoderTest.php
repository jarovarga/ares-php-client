<?php

declare(strict_types=1);

namespace AresApi\Tests\Unit\Http;

use AresApi\Http\JsonResponseDecoder;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(JsonResponseDecoder::class)]
final class JsonResponseDecoderTest extends TestCase
{
    /**
     * @throws JsonException
     */
    public function testItDecodesAJsonObject(): void
    {
        $decoded = new JsonResponseDecoder()->decode(
            '{"ico":"27074358","active":true}',
        );

        self::assertSame([
            'ico' => '27074358',
            'active' => true,
        ], $decoded);
    }

    #[DataProvider('invalidJsonProvider')]
    public function testItRejectsInvalidJsonOrANonObjectRoot(string $json): void
    {
        $this->expectException(JsonException::class);
        $this->expectExceptionMessageIsOrContains('JSON object');

        new JsonResponseDecoder()->decode($json);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidJsonProvider(): iterable
    {
        yield 'empty body' => [''];
        yield 'list root' => ['[]'];
        yield 'string root' => ['"company"'];
    }

    public function testItRejectsMalformedJson(): void
    {
        $this->expectException(JsonException::class);

        new JsonResponseDecoder()->decode('{"ico":');
    }

    public function testItRejectsAnObjectWithNumericPropertyNames(): void
    {
        $this->expectException(JsonException::class);
        $this->expectExceptionMessageIsOrContains('non-numeric property names');

        new JsonResponseDecoder()->decode('{"0":"value"}');
    }
}
