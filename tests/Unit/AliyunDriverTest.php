<?php

namespace Caiyun\Sms\Tests\Unit;

use AlibabaCloud\SDK\Dysmsapi\V20170525\Dysmsapi;
use AlibabaCloud\SDK\Dysmsapi\V20170525\Models\SendSmsRequest;
use AlibabaCloud\SDK\Dysmsapi\V20170525\Models\SendSmsResponse;
use AlibabaCloud\SDK\Dysmsapi\V20170525\Models\SendSmsResponseBody;
use Caiyun\Sms\Drivers\AliyunDriver;
use Caiyun\Sms\Exceptions\SmsException;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class AliyunDriverTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private const CONFIG = [
        'access_key_id' => 'test-access-key',
        'access_key_secret' => 'test-secret',
        'sign_name' => '测试签名',
    ];

    public function test_request_format_and_success_response(): void
    {
        $client = Mockery::mock(Dysmsapi::class);
        $client->shouldReceive('sendSms')->once()
            ->with(Mockery::on(function (SendSmsRequest $request): bool {
                self::assertSame('13800138000', $request->phoneNumbers);
                self::assertSame('测试签名', $request->signName);
                self::assertSame('SMS_123456', $request->templateCode);
                self::assertSame(['code' => '123456'], json_decode($request->templateParam, true));

                return true;
            }))
            ->andReturn(new SendSmsResponse([
                'body' => new SendSmsResponseBody([
                    'code' => 'OK',
                    'message' => 'OK',
                    'requestId' => 'aliyun-request',
                    'bizId' => 'aliyun-message',
                ]),
            ]));

        $result = (new AliyunDriver(self::CONFIG, $client))->send('13800138000', 'SMS_123456', ['code' => '123456']);

        self::assertSame('aliyun', $result->driver);
        self::assertSame('aliyun-request', $result->requestId);
        self::assertSame('aliyun-message', $result->messageId);
        self::assertSame('OK', $result->raw['Code']);
    }

    public function test_provider_failure_keeps_the_error_code(): void
    {
        $client = Mockery::mock(Dysmsapi::class);
        $client->shouldReceive('sendSms')->once()->andReturn(new SendSmsResponse([
            'body' => new SendSmsResponseBody([
                'code' => 'isv.MOBILE_NUMBER_ILLEGAL',
                'message' => 'Invalid mobile number',
            ]),
        ]));

        try {
            (new AliyunDriver(self::CONFIG, $client))->send('13800138000', 'SMS_123456');
            self::fail('The provider failure should throw.');
        } catch (SmsException $exception) {
            self::assertSame('isv.MOBILE_NUMBER_ILLEGAL', $exception->providerCode);
            self::assertSame('Invalid mobile number', $exception->getMessage());
        }
    }

    public function test_sdk_exception_is_wrapped_and_preserved(): void
    {
        $previous = new RuntimeException('Connection failed');
        $client = Mockery::mock(Dysmsapi::class);
        $client->shouldReceive('sendSms')->once()->andThrow($previous);

        try {
            (new AliyunDriver(self::CONFIG, $client))->send('13800138000', 'SMS_123456');
            self::fail('The SDK exception should throw.');
        } catch (SmsException $exception) {
            self::assertSame($previous, $exception->getPrevious());
        }
    }

    public function test_missing_configuration_has_a_clear_error(): void
    {
        $this->expectException(SmsException::class);
        $this->expectExceptionMessage('Missing Aliyun SMS configuration: access_key_id');

        new AliyunDriver([]);
    }

    public function test_client_can_be_created_from_configuration(): void
    {
        $this->expectNotToPerformAssertions();

        new AliyunDriver(self::CONFIG);
    }
}
