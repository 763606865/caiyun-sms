<?php

namespace Caiyun\Sms\Drivers;

use AlibabaCloud\SDK\Dysmsapi\V20170525\Dysmsapi;
use AlibabaCloud\SDK\Dysmsapi\V20170525\Models\SendSmsRequest;
use Caiyun\Sms\Contracts\SmsSender;
use Caiyun\Sms\Exceptions\SmsException;
use Caiyun\Sms\SmsResult;
use Darabonba\OpenApi\Models\Config;
use Throwable;

class AliyunDriver implements SmsSender
{
    private Dysmsapi $client;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(private readonly array $config, ?Dysmsapi $client = null)
    {
        if (! class_exists(Dysmsapi::class)) {
            throw new SmsException(
                'Aliyun SMS SDK is not installed. Run: composer require alibabacloud/dysmsapi-20170525',
            );
        }

        $this->guardConfig(['access_key_id', 'access_key_secret', 'sign_name']);

        if ($client !== null) {
            $this->client = $client;

            return;
        }

        $sdkConfig = new Config([
            'accessKeyId' => $config['access_key_id'],
            'accessKeySecret' => $config['access_key_secret'],
            'endpoint' => $config['endpoint'] ?? 'dysmsapi.aliyuncs.com',
        ]);

        $this->client = new Dysmsapi($sdkConfig);
    }

    public function send(string $mobile, string $template, array $parameters = []): SmsResult
    {
        try {
            $response = $this->client->sendSms(new SendSmsRequest([
                'phoneNumbers' => $mobile,
                'signName' => $this->config['sign_name'],
                'templateCode' => $template,
                'templateParam' => json_encode($parameters, JSON_THROW_ON_ERROR),
            ]));

            $body = $response->body;
            $code = (string) ($body->code ?? '');

            if ($code !== 'OK') {
                throw new SmsException(
                    (string) ($body->message ?? 'Aliyun SMS request failed.'),
                    $code,
                );
            }

            return new SmsResult(
                driver: 'aliyun',
                requestId: $body->requestId ?? null,
                messageId: $body->bizId ?? null,
                raw: $body->toMap(),
            );
        } catch (SmsException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new SmsException('Aliyun SMS request failed.', previous: $exception);
        }
    }

    /**
     * @param  list<string>  $keys
     */
    private function guardConfig(array $keys): void
    {
        foreach ($keys as $key) {
            if (empty($this->config[$key])) {
                throw new SmsException("Missing Aliyun SMS configuration: {$key}.");
            }
        }
    }
}
