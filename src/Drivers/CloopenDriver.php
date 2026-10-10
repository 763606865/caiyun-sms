<?php

namespace Caiyun\Sms\Drivers;

use Caiyun\Sms\Exceptions\SmsException;
use Caiyun\Sms\SmsResult;
use DateTimeImmutable;
use DateTimeZone;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\RequestInterface;

class CloopenDriver extends HttpDriver
{
    protected const NAME = 'Cloopen';

    protected const REQUIRED_CONFIG = ['account_sid', 'auth_token', 'app_id'];

    protected function buildRequest(string $mobile, string $template, array $parameters): RequestInterface
    {
        $timestamp = (new DateTimeImmutable('now', new DateTimeZone('Asia/Shanghai')))->format('YmdHis');
        $accountSid = (string) $this->config['account_sid'];
        $signature = strtoupper(md5($accountSid.$this->config['auth_token'].$timestamp));
        $url = $this->endpoint('https://app.cloopen.com:8883')
            .'/2013-12-26/Accounts/'.rawurlencode($accountSid).'/SMS/TemplateSMS?sig='.$signature;
        $body = [
            'to' => $mobile,
            'appId' => (string) $this->config['app_id'],
            'templateId' => $template,
        ];

        if ($parameters !== []) {
            $body['datas'] = array_values($this->stringParameters($parameters));
        }

        return new Request('POST', $url, [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json;charset=utf-8',
            'Authorization' => base64_encode($accountSid.':'.$timestamp),
        ], json_encode($body, JSON_THROW_ON_ERROR));
    }

    protected function parseResponse(array $raw): SmsResult
    {
        $code = (string) ($raw['statusCode'] ?? '');

        if ($code !== '000000') {
            throw new SmsException((string) ($raw['statusMsg'] ?? 'Cloopen SMS request failed.'), $code ?: null);
        }

        // The REST documentation and SDKs use both spellings for this object.
        $message = $raw['templateSMS'] ?? $raw['TemplateSMS'] ?? [];

        if (empty($message['smsMessageSid']) || ! is_string($message['smsMessageSid'])) {
            throw new SmsException('Cloopen SMS response is missing the message ID.');
        }

        return new SmsResult(driver: 'cloopen', messageId: $message['smsMessageSid'], raw: $raw);
    }
}
