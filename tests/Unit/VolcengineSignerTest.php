<?php

namespace Caiyun\Sms\Tests\Unit;

use Caiyun\Sms\Support\VolcengineSigner;
use DateTimeImmutable;
use GuzzleHttp\Psr7\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/** @phpstan-type SignatureVector array{url: string, body: string, region: string, security_token: string, date: string, authorization: string, payload_hash: string} */
class VolcengineSignerTest extends TestCase
{
    /** @param SignatureVector $vector */
    #[DataProvider('officialSdkVectors')]
    public function test_signature_matches_the_official_sdk(array $vector): void
    {
        $request = new Request('POST', $vector['url'], ['Content-Type' => 'application/json'], $vector['body']);
        $signed = (new VolcengineSigner)->sign($request, 'test-access-key', 'test-secret-key',
            $vector['region'], $vector['security_token'], new DateTimeImmutable($vector['date']),
        );

        self::assertSame($vector['authorization'], $signed->getHeaderLine('Authorization'));
        self::assertSame($vector['payload_hash'], $signed->getHeaderLine('X-Content-Sha256'));
        self::assertSame('20261010T080000Z', $signed->getHeaderLine('X-Date'));
        self::assertSame($vector['security_token'], $signed->getHeaderLine('X-Security-Token'));
        self::assertSame($vector['body'], (string) $signed->getBody());
    }

    /** @return array<string, array{SignatureVector}> */
    public static function officialSdkVectors(): array
    {
        $json = file_get_contents(dirname(__DIR__).'/Fixtures/volcengine-signatures.json');

        if ($json === false) {
            throw new RuntimeException('Unable to read the official SDK signature fixtures.');
        }

        /** @var array<string, SignatureVector> $vectors */
        $vectors = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        return array_map(static fn (array $vector) => [$vector], $vectors);
    }

    public function test_signing_preserves_the_body_stream_position(): void
    {
        $request = new Request('POST', 'https://sms.volcengineapi.com/?Action=SendSms&Version=2020-01-01', [], '{"code":"123456"}');
        $request->getBody()->seek(4);
        $signed = (new VolcengineSigner)->sign($request, 'test-access-key', 'test-secret-key', 'cn-north-1');

        self::assertSame(4, $signed->getBody()->tell());
        self::assertSame(hash('sha256', '{"code":"123456"}'), $signed->getHeaderLine('X-Content-Sha256'));
    }
}
