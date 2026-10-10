<?php

namespace Caiyun\Sms\Drivers;

use Caiyun\Sms\Exceptions\SmsException;
use Caiyun\Sms\SmsResult;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\RequestInterface;

class HuaweiDriver extends HttpDriver
{
    protected const NAME = 'Huawei';

    protected const REQUIRED_CONFIG = ['app_key', 'app_secret', 'sender', 'endpoint'];

    protected function buildRequest(string $mobile, string $template, array $parameters): RequestInterface
    {
        $created = gmdate('Y-m-d\TH:i:s\Z');
        $nonce = bin2hex(random_bytes(16));
        // Huawei's X-WSSE protocol Base64-encodes the SHA256 hexadecimal string.
        $digest = base64_encode(hash('sha256', $nonce.$created.$this->config['app_secret']));
        $body = [
            'from' => (string) $this->config['sender'],
            'to' => $mobile,
            'templateId' => $template,
        ];

        if ($parameters !== []) {
            $body['templateParas'] = json_encode(array_values($this->stringParameters($parameters)), JSON_THROW_ON_ERROR);
        }

        foreach (['sign_name' => 'signature', 'status_callback' => 'statusCallback'] as $key => $field) {
            if (! empty($this->config[$key])) {
                $body[$field] = (string) $this->config[$key];
            }
        }

        return new Request('POST', $this->endpoint().'/sms/batchSendSms/v1', [
            'Accept' => 'application/json',
            'Content-Type' => 'application/x-www-form-urlencoded',
            'Authorization' => 'WSSE realm="SDP",profile="UsernameToken",type="Appkey"',
            'X-WSSE' => sprintf(
                'UsernameToken Username="%s",PasswordDigest="%s",Nonce="%s",Created="%s"',
                $this->config['app_key'], $digest, $nonce, $created,
            ),
        ], http_build_query($body, '', '&', PHP_QUERY_RFC3986));
    }

    protected function parseResponse(array $raw): SmsResult
    {
        $code = (string) ($raw['code'] ?? '');

        if ($code !== '000000') {
            throw new SmsException((string) ($raw['description'] ?? 'Huawei SMS request failed.'), $code ?: null);
        }

        $message = $raw['result'][0] ?? [];
        $status = (string) ($message['status'] ?? '');

        if ($status !== '000000') {
            throw new SmsException('Huawei SMS message was rejected.', $status ?: null);
        }

        if (empty($message['smsMsgId']) || ! is_string($message['smsMsgId'])) {
            throw new SmsException('Huawei SMS response is missing the message ID.');
        }

        return new SmsResult(driver: 'huawei', messageId: $message['smsMsgId'], raw: $raw);
    }
}
