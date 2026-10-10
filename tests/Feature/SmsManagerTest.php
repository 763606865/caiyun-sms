<?php

namespace Caiyun\Sms\Tests\Feature;

use Caiyun\Sms\Contracts\SmsSender;
use Caiyun\Sms\Exceptions\SmsException;
use Caiyun\Sms\Facades\Sms;
use Caiyun\Sms\SmsManager;
use Caiyun\Sms\SmsResult;
use Caiyun\Sms\SmsServiceProvider;
use Caiyun\Sms\Tests\TestCase;
use Illuminate\Log\Logger;
use Illuminate\Log\LogManager;
use InvalidArgumentException;
use Monolog\Handler\TestHandler;
use Monolog\Logger as MonologLogger;
use PHPUnit\Framework\Attributes\DataProvider;
use stdClass;

class SmsManagerTest extends TestCase
{
    public function test_container_bindings_resolve_to_the_same_manager(): void
    {
        $manager = $this->application()->make(SmsManager::class);

        self::assertSame($manager, $this->application()->make(SmsSender::class));
        self::assertSame($manager, $this->application()->make('sms'));
        self::assertSame($manager, Sms::getFacadeRoot());
    }

    public function test_default_log_driver_records_the_message_without_sending_sms(): void
    {
        $result = Sms::send('13800138000', 'verification_code', ['code' => '123456']);
        $handler = $this->logHandler();

        self::assertSame('log', $result->driver);
        self::assertNotNull($result->messageId);
        self::assertStringStartsWith('log-', $result->messageId);
        self::assertSame('SMS message', $handler->getRecords()[0]->message);
        self::assertSame([
            'mobile' => '13800138000',
            'template' => 'verification_code',
            'parameters' => ['code' => '123456'],
        ], $handler->getRecords()[0]->context);
    }

    public function test_default_driver_resolves_its_own_template_id(): void
    {
        $this->application()['config']->set('sms.default', 'aliyun');
        $this->application()['config']->set('sms.templates.verification_code', [
            'aliyun' => 'SMS_123456',
            'tencent' => '123456',
        ]);
        $this->expectSend('aliyun', 'SMS_123456', ['code' => '123456']);

        self::assertSame('aliyun', Sms::send('13800138000', 'verification_code', ['code' => '123456'])->driver);
    }

    public function test_explicit_driver_also_resolves_template_aliases(): void
    {
        $this->application()['config']->set('sms.templates.verification_code', [
            'aliyun' => 'SMS_123456',
            'tencent' => '123456',
        ]);
        $this->expectSend('tencent', '123456', ['123456']);

        self::assertSame('tencent', Sms::driver('tencent')->send('13800138000', 'verification_code', ['123456'])->driver);
        self::assertSame('log', Sms::getDefaultDriver());
    }

    public function test_string_template_aliases_remain_supported(): void
    {
        $this->application()['config']->set('sms.templates.verification_code', 'SMS_LEGACY');
        $this->expectSend('aliyun', 'SMS_LEGACY', []);

        Sms::driver('aliyun')->send('13800138000', 'verification_code');
    }

    public function test_provider_template_ids_can_be_passed_directly(): void
    {
        $this->expectSend('aliyun', 'SMS_DIRECT', []);

        Sms::driver('aliyun')->send('13800138000', 'SMS_DIRECT');
    }

    public function test_missing_provider_template_fails_before_sending(): void
    {
        $this->application()['config']->set('sms.templates.verification_code', ['log' => 'verification_code']);
        $sender = $this->createMock(SmsSender::class);
        $sender->expects(self::never())->method('send');
        Sms::extend('aliyun', fn () => $sender);

        $this->expectException(SmsException::class);
        $this->expectExceptionMessage('not configured for driver [aliyun]');

        Sms::driver('aliyun')->send('13800138000', 'verification_code');
    }

    public function test_empty_template_fails_before_sending(): void
    {
        $this->expectException(SmsException::class);

        Sms::send('13800138000', '');
    }

    public function test_named_driver_uses_its_configured_type_and_template(): void
    {
        $this->application()['config']->set('sms.drivers.backup', ['driver' => 'log', 'channel' => 'sms-test']);
        $this->application()['config']->set('sms.templates.notice', ['backup' => 'backup-notice', 'log' => 'log-notice']);

        Sms::driver('backup')->send('13800138000', 'notice');
        $handler = $this->logHandler('sms-test');

        self::assertSame('backup-notice', $handler->getRecords()[0]->context['template']);
    }

    public function test_named_driver_can_fall_back_to_a_template_for_its_type(): void
    {
        $this->application()['config']->set('sms.drivers.backup', ['driver' => 'log']);
        $this->application()['config']->set('sms.templates.notice', ['log' => 'log-notice']);

        Sms::driver('backup')->send('13800138000', 'notice');
        $handler = $this->logHandler();

        self::assertSame('log-notice', $handler->getRecords()[0]->context['template']);
    }

    public function test_custom_driver_type_can_be_used_by_a_named_driver(): void
    {
        $this->application()['config']->set('sms.drivers.backup', ['driver' => 'custom']);
        $this->application()['config']->set('sms.templates.notice', ['custom' => 'custom-notice']);
        $this->expectSend('custom', 'custom-notice', []);

        self::assertSame('custom', Sms::driver('backup')->send('13800138000', 'notice')->driver);
    }

    public function test_driver_instances_are_cached(): void
    {
        self::assertSame(Sms::driver('log'), Sms::driver('log'));
    }

    public function test_invalid_custom_driver_has_a_clear_error(): void
    {
        Sms::extend('invalid', fn () => new stdClass);
        $this->expectException(SmsException::class);
        $this->expectExceptionMessage('must implement');

        Sms::driver('invalid');
    }

    public function test_unknown_driver_has_a_clear_error(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('SMS driver [unknown] is not supported');

        Sms::driver('unknown');
    }

    public function test_configuration_can_be_published(): void
    {
        $source = dirname(__DIR__, 2).'/config/sms.php';
        $paths = SmsServiceProvider::pathsToPublish(SmsServiceProvider::class, 'sms-config');

        self::assertArrayHasKey($source, $paths);
        self::assertSame($this->application()->configPath('sms.php'), $paths[$source]);
    }

    /** @param array<string, mixed> $config */
    #[DataProvider('newProviders')]
    public function test_new_provider_can_be_selected_from_configuration(string $driver, array $config): void
    {
        $this->application()['config']->set('sms.default', $driver);
        $this->application()['config']->set("sms.drivers.{$driver}", ['driver' => $driver] + $config);

        self::assertSame(Sms::driver($driver), Sms::driver());
    }

    /** @param array<string, mixed> $config */
    #[DataProvider('newProviders')]
    public function test_new_provider_resolves_its_template_alias(string $driver, array $config): void
    {
        $this->application()['config']->set('sms.default', $driver);
        $this->application()['config']->set('sms.templates.verification_code', [$driver => $driver.'-template']);
        $this->expectSend($driver, $driver.'-template', ['code' => '123456']);

        self::assertSame($driver, Sms::send('13800138000', 'verification_code', ['code' => '123456'])->driver);
    }

    /** @return array<string, array{string, array<string, mixed>}> */
    public static function newProviders(): array
    {
        return [
            'huawei' => ['huawei', [
                'app_key' => 'test-app-key', 'app_secret' => 'test-app-secret',
                'sender' => 'csms100000001', 'endpoint' => 'https://smsapi.cn-north-4.myhuaweicloud.com',
            ]],
            'volcengine' => ['volcengine', [
                'access_key_id' => 'test-access-key', 'secret_access_key' => 'test-secret-key',
                'sms_account' => 'sms-account', 'sign_name' => '测试签名',
            ]],
            'cloopen' => ['cloopen', [
                'account_sid' => 'test-account-sid', 'auth_token' => 'test-auth-token', 'app_id' => 'test-app-id',
            ]],
        ];
    }

    private function logHandler(?string $channel = null): TestHandler
    {
        $logger = $this->application()->make(LogManager::class)->channel($channel);
        self::assertInstanceOf(Logger::class, $logger);

        $monolog = $logger->getLogger();
        self::assertInstanceOf(MonologLogger::class, $monolog);

        $handler = $monolog->getHandlers()[0];
        self::assertInstanceOf(TestHandler::class, $handler);

        return $handler;
    }

    /** @param array<array-key, scalar|null> $parameters */
    private function expectSend(string $driver, string $template, array $parameters): void
    {
        $sender = $this->createMock(SmsSender::class);
        $sender->expects(self::once())
            ->method('send')
            ->with('13800138000', $template, $parameters)
            ->willReturn(new SmsResult(driver: $driver, messageId: 'test-message'));

        Sms::extend($driver, fn () => $sender);
    }
}
