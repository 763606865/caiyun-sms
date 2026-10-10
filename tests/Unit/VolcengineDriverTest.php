<?php

namespace Caiyun\Sms\Tests\Unit;

use Caiyun\Sms\Drivers\VolcengineDriver;
use Caiyun\Sms\Exceptions\SmsException;

class VolcengineDriverTest extends HttpDriverTestCase
{
    public function test_request_parameters_signature_headers_and_success_response(): void
    {
        $config = self::VOLCENGINE_CONFIG + ['security_token' => 'test-security-token', 'tag' => 'order-123'];
        $result = (new VolcengineDriver($config, $this->client(self::VOLCENGINE_RESPONSE)))
            ->send('13800138000', 'ST_123', ['code' => 123456, 'minutes' => 5]);
        $request = $this->history[0]['request'];

        self::assertSame('POST', $request->getMethod());
        self::assertSame('https://sms.volcengineapi.com/?Action=SendSms&Version=2020-01-01', (string) $request->getUri());
        self::assertSame('application/json', $request->getHeaderLine('Content-Type'));
        self::assertSame([
            'SmsAccount' => 'sms-account', 'Sign' => '测试签名', 'TemplateID' => 'ST_123',
            'PhoneNumbers' => '13800138000', 'TemplateParam' => '{"code":"123456","minutes":"5"}', 'Tag' => 'order-123',
        ], json_decode((string) $request->getBody(), true, 512, JSON_THROW_ON_ERROR));
        self::assertSame('test-security-token', $request->getHeaderLine('X-Security-Token'));
        self::assertSame(hash('sha256', (string) $request->getBody()), $request->getHeaderLine('X-Content-Sha256'));
        self::assertMatchesRegularExpression('/^\d{8}T\d{6}Z$/', $request->getHeaderLine('X-Date'));
        $shortDate = substr($request->getHeaderLine('X-Date'), 0, 8);
        self::assertStringStartsWith('HMAC-SHA256 Credential=test-access-key/'.$shortDate.'/cn-north-1/volcSMS/request, ',
            $request->getHeaderLine('Authorization'));
        self::assertStringContainsString('SignedHeaders=host;x-content-sha256;x-date;x-security-token, Signature=',
            $request->getHeaderLine('Authorization'));
        self::assertSame('volcengine', $result->driver);
        self::assertSame('volcengine-request', $result->requestId);
        self::assertSame('volcengine-message', $result->messageId);
        self::assertSame(self::VOLCENGINE_RESPONSE, $result->raw);
    }

    public function test_template_without_variables_omits_optional_fields(): void
    {
        (new VolcengineDriver(self::VOLCENGINE_CONFIG, $this->client(self::VOLCENGINE_RESPONSE)))->send('13800138000', 'ST_123');
        $body = json_decode((string) $this->history[0]['request']->getBody(), true, 512, JSON_THROW_ON_ERROR);

        self::assertArrayNotHasKey('TemplateParam', $body);
        self::assertArrayNotHasKey('Tag', $body);
        self::assertFalse($this->history[0]['request']->hasHeader('X-Security-Token'));
    }

    public function test_provider_error_is_checked_even_when_message_ids_are_present(): void
    {
        $body = self::VOLCENGINE_RESPONSE;
        $body['ResponseMetadata']['Error'] = ['Code' => 'RE:0003', 'Message' => 'SmsAccount not exist.'];

        try {
            (new VolcengineDriver(self::VOLCENGINE_CONFIG, $this->client($body)))->send('13800138000', 'ST_123');
            self::fail('Provider failure must throw.');
        } catch (SmsException $exception) {
            self::assertSame('RE:0003', $exception->providerCode);
            self::assertSame('SmsAccount not exist.', $exception->getMessage());
        }
    }

    public function test_positional_parameters_fail_before_the_http_request(): void
    {
        $client = $this->client(self::VOLCENGINE_RESPONSE);

        try {
            (new VolcengineDriver(self::VOLCENGINE_CONFIG, $client))->send('13800138000', 'ST_123', ['123456']);
            self::fail('Named parameters are required.');
        } catch (SmsException $exception) {
            self::assertStringContainsString('named keys', $exception->getMessage());
            self::assertSame([], $this->history);
        }
    }

    public function test_empty_message_id_list_is_a_failure(): void
    {
        $this->expectException(SmsException::class);
        $this->expectExceptionMessage('missing the message ID');
        $body = self::VOLCENGINE_RESPONSE;
        $body['Result']['MessageID'] = [];

        (new VolcengineDriver(self::VOLCENGINE_CONFIG, $this->client($body)))->send('13800138000', 'ST_123');
    }

    public function test_malformed_message_id_list_is_a_failure(): void
    {
        $this->expectException(SmsException::class);
        $this->expectExceptionMessage('missing the message ID');
        $body = self::VOLCENGINE_RESPONSE;
        $body['Result']['MessageID'] = 'not-an-array';

        (new VolcengineDriver(self::VOLCENGINE_CONFIG, $this->client($body)))->send('13800138000', 'ST_123');
    }
}
