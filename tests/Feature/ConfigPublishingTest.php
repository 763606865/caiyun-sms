<?php

namespace Caiyun\Sms\Tests\Feature;

use Caiyun\Sms\SmsServiceProvider;
use Caiyun\Sms\Tests\TestCase;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Testing\PendingCommand;

class ConfigPublishingTest extends TestCase
{
    private string $configDirectory;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $this->configDirectory = sys_get_temp_dir().'/caiyun-sms-config-'.bin2hex(random_bytes(8));
        (new Filesystem)->makeDirectory($this->configDirectory, 0755, true);
        $app->useConfigPath($this->configDirectory);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->beforeApplicationDestroyed(function (): void {
            (new Filesystem)->deleteDirectory($this->configDirectory);
        });
    }

    public function test_artisan_publishes_all_provider_configuration_to_the_application_config_directory(): void
    {
        $this->publishConfiguration();
        $path = $this->application()->configPath('sms.php');

        self::assertFileExists($path);
        self::assertSame(file_get_contents(dirname(__DIR__, 2).'/config/sms.php'), file_get_contents($path));
        $config = require $path;
        self::assertSame('log', $config['default']);
        self::assertSame(['log', 'aliyun', 'tencent', 'huawei', 'volcengine', 'cloopen'], array_keys($config['drivers']));
        self::assertSame(array_keys($config['drivers']), array_keys($config['templates']['verification_code']));
    }

    public function test_existing_application_configuration_is_preserved_unless_force_is_requested(): void
    {
        $path = $this->application()->configPath('sms.php');
        $existing = "<?php\nreturn ['default' => 'custom'];\n";
        file_put_contents($path, $existing);
        $this->publishConfiguration();

        self::assertSame($existing, file_get_contents($path));

        $this->publishConfiguration(force: true);

        self::assertSame(file_get_contents(dirname(__DIR__, 2).'/config/sms.php'), file_get_contents($path));
    }

    private function publishConfiguration(bool $force = false): void
    {
        $arguments = ['--provider' => SmsServiceProvider::class, '--tag' => 'sms-config'];

        if ($force) {
            $arguments['--force'] = true;
        }

        $command = $this->artisan('vendor:publish', $arguments);
        self::assertInstanceOf(PendingCommand::class, $command);
        $command->assertExitCode(0);
    }
}
