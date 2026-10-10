<?php

namespace Caiyun\Sms\Tests\Unit;

use Caiyun\Sms\Drivers\CloopenDriver;
use Caiyun\Sms\Exceptions\SmsException;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;

class CloopenDriverTest extends HttpDriverTestCase
{
    #[DataProvider('responseSpellings')]
    public function test_request_authentication_parameters_and_success_response(string $field): void
    {
        $raw = ['statusCode' => '000000', $field => self::CLOOPEN_RESPONSE['templateSMS']];
        $result = (new CloopenDriver(self::CLOOPEN_CONFIG, $this->client($raw)))
            ->send('13800138000', '123', ['code' => 123456, 'minutes' => 5]);
        $request = $this->history[0]['request'];
        $authorization = base64_decode($request->getHeaderLine('Authorization'), true);

        self::assertIsString($authorization);
        self::assertMatchesRegularExpression('/^test-account-sid:\d{14}$/', $authorization);
        $timestamp = substr($authorization, strlen('test-account-sid:'));
        $time = DateTimeImmutable::createFromFormat('YmdHis', $timestamp, new DateTimeZone('Asia/Shanghai'));
        self::assertInstanceOf(DateTimeImmutable::class, $time);
        self::assertLessThan(10, abs(time() - $time->getTimestamp()));
        parse_str($request->getUri()->getQuery(), $query);
        self::assertSame(strtoupper(md5('test-account-sidtest-auth-token'.$timestamp)), $query['sig']);
        self::assertSame('/2013-12-26/Accounts/test-account-sid/SMS/TemplateSMS', $request->getUri()->getPath());
        self::assertSame('app.cloopen.com', $request->getUri()->getHost());
        self::assertSame(8883, $request->getUri()->getPort());
        self::assertSame('application/json', $request->getHeaderLine('Accept'));
        self::assertSame('application/json;charset=utf-8', $request->getHeaderLine('Content-Type'));
        self::assertSame([
            'to' => '13800138000', 'appId' => 'test-app-id', 'templateId' => '123', 'datas' => ['123456', '5'],
        ], json_decode((string) $request->getBody(), true, 512, JSON_THROW_ON_ERROR));
        self::assertSame('cloopen', $result->driver);
        self::assertSame('cloopen-message', $result->messageId);
        self::assertNull($result->requestId);
        self::assertSame($raw, $result->raw);
    }

    /** @return array<string, array{string}> */
    public static function responseSpellings(): array
    {
        return ['REST JSON example' => ['templateSMS'], 'SDK spelling' => ['TemplateSMS']];
    }

    public function test_template_without_variables_omits_datas(): void
    {
        (new CloopenDriver(self::CLOOPEN_CONFIG, $this->client(self::CLOOPEN_RESPONSE)))->send('13800138000', '123');
        $body = json_decode((string) $this->history[0]['request']->getBody(), true, 512, JSON_THROW_ON_ERROR);

        self::assertArrayNotHasKey('datas', $body);
    }

    public function test_provider_failure_keeps_the_error_code(): void
    {
        $client = $this->client(['statusCode' => '160031', 'statusMsg' => 'Invalid template']);

        try {
            (new CloopenDriver(self::CLOOPEN_CONFIG, $client))->send('13800138000', '123');
            self::fail('Provider failure must throw.');
        } catch (SmsException $exception) {
            self::assertSame('160031', $exception->providerCode);
            self::assertSame('Invalid template', $exception->getMessage());
        }
    }

    public function test_response_without_message_id_is_a_failure(): void
    {
        $this->expectException(SmsException::class);
        $this->expectExceptionMessage('missing the message ID');

        (new CloopenDriver(self::CLOOPEN_CONFIG, $this->client(['statusCode' => '000000'])))
            ->send('13800138000', '123');
    }
}
