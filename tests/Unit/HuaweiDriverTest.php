<?php

namespace Caiyun\Sms\Tests\Unit;

use Caiyun\Sms\Drivers\HuaweiDriver;
use Caiyun\Sms\Exceptions\SmsException;

class HuaweiDriverTest extends HttpDriverTestCase
{
    public function test_request_authentication_parameters_and_success_response(): void
    {
        $config = self::HUAWEI_CONFIG + ['sign_name' => '测试签名', 'status_callback' => 'https://example.com/sms-status'];
        $result = (new HuaweiDriver($config, $this->client(self::HUAWEI_RESPONSE)))
            ->send('13800138000', 'huawei-template', ['code' => 123456, 'minutes' => 5]);
        $request = $this->history[0]['request'];
        parse_str((string) $request->getBody(), $body);

        self::assertSame('POST', $request->getMethod());
        self::assertSame('/sms/batchSendSms/v1', $request->getUri()->getPath());
        self::assertSame('smsapi.cn-north-4.myhuaweicloud.com', $request->getUri()->getHost());
        self::assertSame('application/x-www-form-urlencoded', $request->getHeaderLine('Content-Type'));
        self::assertSame('WSSE realm="SDP",profile="UsernameToken",type="Appkey"', $request->getHeaderLine('Authorization'));
        self::assertSame([
            'from' => 'csms100000001', 'to' => '13800138000', 'templateId' => 'huawei-template',
            'templateParas' => '["123456","5"]', 'signature' => '测试签名',
            'statusCallback' => 'https://example.com/sms-status',
        ], $body);
        self::assertSame(1, preg_match('/^UsernameToken Username="([^"]+)",PasswordDigest="([^"]+)",Nonce="([a-f0-9]{32})",Created="([^"]+)"$/',
            $request->getHeaderLine('X-WSSE'), $matches));
        self::assertSame('test-app-key', $matches[1]);
        self::assertSame(base64_encode(hash('sha256', $matches[3].$matches[4].'test-app-secret')), $matches[2]);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $matches[4]);
        self::assertLessThan(10, abs(time() - strtotime($matches[4])));
        self::assertSame('huawei', $result->driver);
        self::assertSame('huawei-message', $result->messageId);
        self::assertNull($result->requestId);
        self::assertSame(self::HUAWEI_RESPONSE, $result->raw);
    }

    public function test_template_without_variables_omits_optional_fields(): void
    {
        (new HuaweiDriver(self::HUAWEI_CONFIG, $this->client(self::HUAWEI_RESPONSE)))->send('13800138000', 'no-variables');
        parse_str((string) $this->history[0]['request']->getBody(), $body);

        self::assertArrayNotHasKey('templateParas', $body);
        self::assertArrayNotHasKey('signature', $body);
        self::assertArrayNotHasKey('statusCallback', $body);
    }

    public function test_authentication_error_keeps_provider_code_on_http_401(): void
    {
        $client = $this->client(['code' => 'E000104', 'description' => 'Invalid app_secret.'], 401);

        try {
            (new HuaweiDriver(self::HUAWEI_CONFIG, $client))->send('13800138000', 'template');
            self::fail('Authentication failure must throw.');
        } catch (SmsException $exception) {
            self::assertSame('E000104', $exception->providerCode);
            self::assertSame('Invalid app_secret.', $exception->getMessage());
        }
    }

    public function test_rejected_recipient_is_not_reported_as_success(): void
    {
        $body = self::HUAWEI_RESPONSE;
        $body['result'][0]['status'] = 'E200030';

        try {
            (new HuaweiDriver(self::HUAWEI_CONFIG, $this->client($body)))->send('13800138000', 'template');
            self::fail('Recipient failure must throw.');
        } catch (SmsException $exception) {
            self::assertSame('E200030', $exception->providerCode);
        }
    }

    public function test_response_without_recipient_status_is_a_failure(): void
    {
        $this->expectException(SmsException::class);

        (new HuaweiDriver(self::HUAWEI_CONFIG, $this->client(['code' => '000000', 'result' => []])))
            ->send('13800138000', 'template');
    }

    public function test_response_without_message_id_is_a_failure(): void
    {
        $this->expectException(SmsException::class);
        $this->expectExceptionMessage('missing the message ID');
        $body = self::HUAWEI_RESPONSE;
        unset($body['result'][0]['smsMsgId']);

        (new HuaweiDriver(self::HUAWEI_CONFIG, $this->client($body)))->send('13800138000', 'template');
    }
}
