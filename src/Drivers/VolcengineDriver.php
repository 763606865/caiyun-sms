<?php

namespace Caiyun\Sms\Drivers;

use Caiyun\Sms\Exceptions\SmsException;
use Caiyun\Sms\SmsResult;
use Caiyun\Sms\Support\VolcengineSigner;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\RequestInterface;

class VolcengineDriver extends HttpDriver
{
    protected const NAME = 'Volcengine';

    protected const REQUIRED_CONFIG = ['access_key_id', 'secret_access_key', 'sms_account', 'sign_name'];

    protected function buildRequest(string $mobile, string $template, array $parameters): RequestInterface
    {
        if ($parameters !== [] && array_is_list($parameters)) {
            throw new SmsException('Volcengine SMS template parameters must use named keys.');
        }

        $body = [
            'SmsAccount' => (string) $this->config['sms_account'],
            'Sign' => (string) $this->config['sign_name'],
            'TemplateID' => $template,
            'PhoneNumbers' => $mobile,
        ];

        if ($parameters !== []) {
            $body['TemplateParam'] = json_encode((object) $this->stringParameters($parameters), JSON_THROW_ON_ERROR);
        }

        if (! empty($this->config['tag'])) {
            $body['Tag'] = (string) $this->config['tag'];
        }

        $request = new Request('POST', $this->endpoint('https://sms.volcengineapi.com')
            .'/?Action=SendSms&Version=2020-01-01', [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ], json_encode($body, JSON_THROW_ON_ERROR));

        return (new VolcengineSigner)->sign(
            $request,
            (string) $this->config['access_key_id'],
            (string) $this->config['secret_access_key'],
            (string) ($this->config['region'] ?? 'cn-north-1'),
            (string) ($this->config['security_token'] ?? ''),
        );
    }

    protected function parseResponse(array $raw): SmsResult
    {
        $metadata = $raw['ResponseMetadata'] ?? [];

        if (isset($metadata['Error'])) {
            throw new SmsException(
                (string) ($metadata['Error']['Message'] ?? 'Volcengine SMS request failed.'),
                isset($metadata['Error']['Code']) ? (string) $metadata['Error']['Code'] : null,
            );
        }

        $messageIds = $raw['Result']['MessageID'] ?? [];
        $messageId = is_array($messageIds) ? ($messageIds[0] ?? null) : null;

        if (! is_string($messageId) || $messageId === '') {
            throw new SmsException('Volcengine SMS response is missing the message ID.');
        }

        return new SmsResult(
            driver: 'volcengine',
            requestId: $metadata['RequestId'] ?? null,
            messageId: $messageId,
            raw: $raw,
        );
    }
}
