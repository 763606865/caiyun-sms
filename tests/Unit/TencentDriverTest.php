<?php

namespace Caiyun\Sms\Tests\Unit;

use Caiyun\Sms\Drivers\TencentDriver;
use Caiyun\Sms\Exceptions\SmsException;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use TencentCloud\Sms\V20210111\Models\SendSmsRequest;
use TencentCloud\Sms\V20210111\Models\SendSmsResponse;
use TencentCloud\Sms\V20210111\SmsClient;

class TencentDriverTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private const CONFIG = [
        'secret_id' => 'test-secret-id',
        'secret_key' => 'test-secret-key',
        'sdk_app_id' => 1400000000,
        'sign_name' => '测试签名',
    ];

    #[DataProvider('mobileNumbers')]
    public function test_request_format_and_success_response(string $mobile, string $expected): void
    {
        $client = Mockery::mock(SmsClient::class);
        $client->shouldReceive('SendSms')->once()
            ->with(Mockery::on(function (SendSmsRequest $request) use ($expected): bool {
                self::assertSame([$expected], $request->PhoneNumberSet);
                self::assertSame('1400000000', $request->SmsSdkAppId);
                self::assertSame('测试签名', $request->SignName);
                self::assertSame('123456', $request->TemplateId);
                self::assertSame(['123456', '5'], $request->TemplateParamSet);

                return true;
            }))
            ->andReturn($this->response('Ok', 'Send success', 'tencent-message'));

        $result = (new TencentDriver(self::CONFIG, $client))->send($mobile, '123456', ['code' => '123456', 'minutes' => 5]);

        self::assertSame('tencent', $result->driver);
        self::assertSame('tencent-request', $result->requestId);
        self::assertSame('tencent-message', $result->messageId);
        self::assertSame('Ok', $result->raw['SendStatusSet'][0]['Code']);
    }

    /** @return array<string, array{string, string}> */
    public static function mobileNumbers(): array
    {
        return [
            'mainland' => ['13800138000', '+8613800138000'],
            'international' => ['+14155552671', '+14155552671'],
        ];
    }

    public function test_provider_failure_keeps_the_error_code(): void
    {
        $client = Mockery::mock(SmsClient::class);
        $client->shouldReceive('SendSms')->once()->andReturn($this->response('FailedOperation.SignatureIncorrect', 'Invalid signature'));

        try {
            (new TencentDriver(self::CONFIG, $client))->send('13800138000', '123456');
            self::fail('The provider failure should throw.');
        } catch (SmsException $exception) {
            self::assertSame('FailedOperation.SignatureIncorrect', $exception->providerCode);
            self::assertSame('Invalid signature', $exception->getMessage());
        }
    }

    public function test_empty_send_status_is_a_failure(): void
    {
        $response = new SendSmsResponse;
        $response->fromJsonString('{"SendStatusSet":[],"RequestId":"empty-request"}');
        $client = Mockery::mock(SmsClient::class);
        $client->shouldReceive('SendSms')->once()->andReturn($response);
        $this->expectException(SmsException::class);

        (new TencentDriver(self::CONFIG, $client))->send('13800138000', '123456');
    }

    public function test_sdk_exception_is_wrapped_and_preserved(): void
    {
        $previous = new RuntimeException('Connection failed');
        $client = Mockery::mock(SmsClient::class);
        $client->shouldReceive('SendSms')->once()->andThrow($previous);

        try {
            (new TencentDriver(self::CONFIG, $client))->send('13800138000', '123456');
            self::fail('The SDK exception should throw.');
        } catch (SmsException $exception) {
            self::assertSame($previous, $exception->getPrevious());
        }
    }

    public function test_missing_configuration_has_a_clear_error(): void
    {
        $this->expectException(SmsException::class);
        $this->expectExceptionMessage('Missing Tencent SMS configuration: secret_id');

        new TencentDriver([]);
    }

    public function test_client_can_be_created_from_configuration(): void
    {
        $this->expectNotToPerformAssertions();

        new TencentDriver(self::CONFIG);
    }

    private function response(string $code, string $message, string $serialNo = ''): SendSmsResponse
    {
        $response = new SendSmsResponse;
        $response->fromJsonString(json_encode([
            'SendStatusSet' => [[
                'Code' => $code,
                'Message' => $message,
                'SerialNo' => $serialNo,
                'PhoneNumber' => '+8613800138000',
                'Fee' => 1,
                'SessionContext' => '',
            ]],
            'RequestId' => 'tencent-request',
        ], JSON_THROW_ON_ERROR));

        return $response;
    }
}
