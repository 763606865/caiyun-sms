<?php

namespace Caiyun\Sms\Tests\Unit;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Throwable;

abstract class HttpDriverTestCase extends TestCase
{
    protected const HUAWEI_CONFIG = [
        'app_key' => 'test-app-key', 'app_secret' => 'test-app-secret',
        'sender' => 'csms100000001', 'endpoint' => 'https://smsapi.cn-north-4.myhuaweicloud.com:443',
    ];

    protected const VOLCENGINE_CONFIG = [
        'access_key_id' => 'test-access-key', 'secret_access_key' => 'test-secret-key',
        'sms_account' => 'sms-account', 'sign_name' => '测试签名',
    ];

    protected const CLOOPEN_CONFIG = [
        'account_sid' => 'test-account-sid', 'auth_token' => 'test-auth-token', 'app_id' => 'test-app-id',
    ];

    protected const HUAWEI_RESPONSE = [
        'code' => '000000', 'description' => 'Success',
        'result' => [['status' => '000000', 'smsMsgId' => 'huawei-message', 'originTo' => '13800138000']],
    ];

    protected const VOLCENGINE_RESPONSE = [
        'ResponseMetadata' => ['RequestId' => 'volcengine-request'],
        'Result' => ['MessageID' => ['volcengine-message']],
    ];

    protected const CLOOPEN_RESPONSE = [
        'statusCode' => '000000',
        'templateSMS' => ['smsMessageSid' => 'cloopen-message', 'dateCreated' => '20261010160000'],
    ];

    /** @var list<array{request: RequestInterface, options: array<string, mixed>}> */
    protected array $history = [];

    /** @param array<string, mixed>|string|Throwable $body */
    protected function client(array|string|Throwable $body, int $status = 200): Client
    {
        $this->history = [];
        $response = $body instanceof Throwable ? $body : new Response($status, [],
            is_array($body) ? json_encode($body, JSON_THROW_ON_ERROR) : $body,
        );
        $stack = HandlerStack::create(new MockHandler([$response]));
        $stack->push(Middleware::tap($this->recordRequest(...)));

        return new Client(['handler' => $stack]);
    }

    /** @param array<string, mixed> $options */
    private function recordRequest(RequestInterface $request, array $options): void
    {
        $this->history[] = ['request' => $request, 'options' => $options];
    }
}
