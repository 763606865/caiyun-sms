<?php

namespace Caiyun\Sms\Drivers;

use Caiyun\Sms\Contracts\SmsSender;
use Caiyun\Sms\Exceptions\SmsException;
use Caiyun\Sms\SmsResult;
use TencentCloud\Common\Credential;
use TencentCloud\Sms\V20210111\Models\SendSmsRequest;
use TencentCloud\Sms\V20210111\SmsClient;
use Throwable;

class TencentDriver implements SmsSender
{
    private SmsClient $client;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(private readonly array $config, ?SmsClient $client = null)
    {
        if (! class_exists(SmsClient::class)) {
            throw new SmsException(
                'Tencent SMS SDK is not installed. Run: composer require tencentcloud/sms',
            );
        }

        $this->guardConfig(['secret_id', 'secret_key', 'sdk_app_id', 'sign_name']);

        if ($client !== null) {
            $this->client = $client;

            return;
        }

        $credential = new Credential(
            (string) $config['secret_id'],
            (string) $config['secret_key'],
        );

        $this->client = new SmsClient(
            $credential,
            (string) ($config['region'] ?? 'ap-guangzhou'),
        );
    }

    public function send(string $mobile, string $template, array $parameters = []): SmsResult
    {
        try {
            $request = new SendSmsRequest;
            $request->fromJsonString(json_encode([
                'PhoneNumberSet' => [$this->normalizeMobile($mobile)],
                'SmsSdkAppId' => (string) $this->config['sdk_app_id'],
                'SignName' => $this->config['sign_name'],
                'TemplateId' => $template,
                'TemplateParamSet' => array_map(
                    static fn (mixed $value): string => (string) $value,
                    array_values($parameters),
                ),
            ], JSON_THROW_ON_ERROR));

            $response = $this->client->SendSms($request);
            $status = $response->SendStatusSet[0] ?? null;

            if ($status === null || $status->Code !== 'Ok') {
                throw new SmsException(
                    $status->Message ?? 'Tencent SMS request failed.',
                    $status?->Code,
                );
            }

            return new SmsResult(
                driver: 'tencent',
                requestId: $response->RequestId ?? null,
                messageId: $status->SerialNo ?? null,
                raw: json_decode($response->toJsonString(), true, flags: JSON_THROW_ON_ERROR),
            );
        } catch (SmsException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new SmsException('Tencent SMS request failed.', previous: $exception);
        }
    }

    private function normalizeMobile(string $mobile): string
    {
        return str_starts_with($mobile, '+') ? $mobile : '+86'.$mobile;
    }

    /**
     * @param  list<string>  $keys
     */
    private function guardConfig(array $keys): void
    {
        foreach ($keys as $key) {
            if (empty($this->config[$key])) {
                throw new SmsException("Missing Tencent SMS configuration: {$key}.");
            }
        }
    }
}
