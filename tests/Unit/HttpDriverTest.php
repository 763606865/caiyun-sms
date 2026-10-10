<?php

namespace Caiyun\Sms\Tests\Unit;

use Caiyun\Sms\Drivers\CloopenDriver;
use Caiyun\Sms\Drivers\HttpDriver;
use Caiyun\Sms\Drivers\HuaweiDriver;
use Caiyun\Sms\Drivers\VolcengineDriver;
use Caiyun\Sms\Exceptions\SmsException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use JsonException;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;

class HttpDriverTest extends HttpDriverTestCase
{
    /** @return array<string, array{class-string<HttpDriver>, array<string, mixed>, array<string, mixed>}> */
    public static function providers(): array
    {
        return [
            'huawei' => [HuaweiDriver::class, self::HUAWEI_CONFIG, self::HUAWEI_RESPONSE],
            'volcengine' => [VolcengineDriver::class, self::VOLCENGINE_CONFIG, self::VOLCENGINE_RESPONSE],
            'cloopen' => [CloopenDriver::class, self::CLOOPEN_CONFIG, self::CLOOPEN_RESPONSE],
        ];
    }

    /**
     * @param  class-string<HttpDriver>  $class
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $response
     */
    #[DataProvider('providers')]
    public function test_network_exception_is_preserved_without_retrying(string $class, array $config, array $response): void
    {
        $previous = new ConnectException('Connection timed out', new Request('POST', 'https://example.com'));
        $client = $this->client($previous);

        try {
            (new $class($config, $client))->send('13800138000', 'template');
            self::fail('Transport failure must throw.');
        } catch (SmsException $exception) {
            self::assertSame($previous, $exception->getPrevious());
            self::assertCount(1, $this->history);
        }
    }

    /**
     * @param  class-string<HttpDriver>  $class
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $response
     */
    #[DataProvider('providers')]
    public function test_invalid_json_is_wrapped(string $class, array $config, array $response): void
    {
        $client = $this->client('<html>Gateway unavailable</html>', 502);

        try {
            (new $class($config, $client))->send('13800138000', 'template');
            self::fail('Invalid JSON must throw.');
        } catch (SmsException $exception) {
            self::assertInstanceOf(JsonException::class, $exception->getPrevious());
        }
    }

    /**
     * @param  class-string<HttpDriver>  $class
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $response
     */
    #[DataProvider('providers')]
    public function test_non_object_response_is_a_failure(string $class, array $config, array $response): void
    {
        $this->expectException(SmsException::class);
        $this->expectExceptionMessage('invalid response');

        (new $class($config, $this->client('null')))->send('13800138000', 'template');
    }

    /**
     * @param  class-string<HttpDriver>  $class
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $response
     */
    #[DataProvider('providers')]
    public function test_http_failure_cannot_be_reported_as_success(string $class, array $config, array $response): void
    {
        $this->expectException(SmsException::class);
        $this->expectExceptionMessage('HTTP status 503');

        (new $class($config, $this->client($response, 503)))->send('13800138000', 'template');
    }

    /**
     * @param  class-string<HttpDriver>  $class
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $response
     */
    #[DataProvider('providers')]
    public function test_missing_credentials_have_a_clear_error(string $class, array $config, array $response): void
    {
        $this->expectException(SmsException::class);
        $this->expectExceptionMessage('configuration: '.array_key_first($config));
        unset($config[array_key_first($config)]);

        new $class($config);
    }

    /**
     * @param  class-string<HttpDriver>  $class
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $response
     */
    #[DataProvider('providers')]
    public function test_invalid_parameters_fail_before_sending(string $class, array $config, array $response): void
    {
        $client = $this->client($response);

        try {
            // Exercise the runtime guard with values outside the documented parameter contract.
            (new ReflectionMethod($class, 'send'))->invoke(
                new $class($config, $client), '13800138000', 'template', ['code' => ['invalid']],
            );
            self::fail('Nested parameters must throw.');
        } catch (SmsException $exception) {
            self::assertStringContainsString('scalar values', $exception->getMessage());
            self::assertSame([], $this->history);
        }
    }

    /**
     * @param  class-string<HttpDriver>  $class
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $response
     */
    #[DataProvider('providers')]
    public function test_insecure_endpoint_fails_before_sending(string $class, array $config, array $response): void
    {
        $config['endpoint'] = 'http://example.com';
        $client = $this->client($response);

        try {
            (new $class($config, $client))->send('13800138000', 'template');
            self::fail('HTTPS is required.');
        } catch (SmsException $exception) {
            self::assertStringContainsString('HTTPS base URL', $exception->getMessage());
            self::assertSame([], $this->history);
        }
    }

    /**
     * @param  class-string<HttpDriver>  $class
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $response
     */
    #[DataProvider('providers')]
    public function test_transport_uses_configured_timeouts_and_keeps_tls_validation(string $class, array $config, array $response): void
    {
        $config['timeout'] = 3;
        $config['connect_timeout'] = 2;
        (new $class($config, $this->client($response)))->send('13800138000', 'template');
        $options = $this->history[0]['options'];

        self::assertSame(3.0, $options['timeout']);
        self::assertSame(2.0, $options['connect_timeout']);
        self::assertFalse($options['allow_redirects']);
        self::assertTrue($options['verify']);
    }
}
