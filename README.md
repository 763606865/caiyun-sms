# Caiyun SMS

适用于 **PHP 8.3+、Laravel 13.x** 的 Composer 多驱动短信包，内置：

- `log`：本地开发和测试
- `aliyun`：阿里云短信
- `tencent`：腾讯云短信
- `huawei`：华为云消息与短信
- `volcengine`：火山引擎短信服务
- `cloopen`：容联云通讯模板短信

## 在其他项目中安装

发布到 Packagist 且存在稳定版本后，在 Laravel 项目中执行：

```bash
composer require caiyun/sms
```

阿里云和腾讯云短信 SDK 会随包自动安装。腾讯云使用 `tencentcloud/sms` 短信模块。华为云、火山引擎、容联云通过随包安装的 Guzzle 调用官方 HTTP API。Laravel 通过 package discovery 自动注册服务提供者和 `Sms` Facade。

安装后执行以下命令，将配置发布到**业务项目的 `config/sms.php`**：

```bash
php artisan vendor:publish --provider="Caiyun\Sms\SmsServiceProvider" --tag=sms-config
```

发布命令默认保留已存在的 `config/sms.php`。升级后请将新增驱动和模板配置同步到已有文件；也可以先备份自己的配置，再给上述命令添加 `--force` 重新发布。

默认驱动为 `log`，可直接调用；发送真实短信前需要填写服务商凭据、已审核签名和模板 ID。首次公开发布请参阅 [发布指南](docs/publishing.md)。

## 配置

```env
SMS_DRIVER=log

SMS_LOG_CHANNEL=

ALIYUN_SMS_ACCESS_KEY_ID=
ALIYUN_SMS_ACCESS_KEY_SECRET=
ALIYUN_SMS_SIGN_NAME=
ALIYUN_SMS_ENDPOINT=dysmsapi.aliyuncs.com
ALIYUN_SMS_VERIFICATION_CODE_TEMPLATE=SMS_你的模板ID

TENCENT_SMS_SECRET_ID=
TENCENT_SMS_SECRET_KEY=
TENCENT_SMS_SDK_APP_ID=
TENCENT_SMS_SIGN_NAME=
TENCENT_SMS_REGION=ap-guangzhou
TENCENT_SMS_VERIFICATION_CODE_TEMPLATE=你的模板ID

SMS_HTTP_TIMEOUT=10
SMS_HTTP_CONNECT_TIMEOUT=5

HUAWEI_SMS_APP_KEY=
HUAWEI_SMS_APP_SECRET=
HUAWEI_SMS_SENDER=csms签名通道号
HUAWEI_SMS_ENDPOINT=https://短信应用的APP接入地址
HUAWEI_SMS_SIGN_NAME=
HUAWEI_SMS_STATUS_CALLBACK=
HUAWEI_SMS_VERIFICATION_CODE_TEMPLATE=你的模板ID

VOLCENGINE_SMS_ACCESS_KEY_ID=
VOLCENGINE_SMS_SECRET_ACCESS_KEY=
VOLCENGINE_SMS_ACCOUNT=消息组ID
VOLCENGINE_SMS_SIGN_NAME=
VOLCENGINE_SMS_REGION=cn-north-1
VOLCENGINE_SMS_ENDPOINT=https://sms.volcengineapi.com
VOLCENGINE_SMS_SECURITY_TOKEN=
VOLCENGINE_SMS_TAG=
VOLCENGINE_SMS_VERIFICATION_CODE_TEMPLATE=ST_你的模板ID

CLOOPEN_SMS_ACCOUNT_SID=
CLOOPEN_SMS_AUTH_TOKEN=
CLOOPEN_SMS_APP_ID=
CLOOPEN_SMS_ENDPOINT=https://app.cloopen.com:8883
CLOOPEN_SMS_VERIFICATION_CODE_TEMPLATE=你的模板ID
```

将 `SMS_DRIVER` 设置为 `aliyun`、`tencent`、`huawei`、`volcengine` 或 `cloopen` 即可切换服务商。`log` 会把手机号、模板和参数写入指定 Laravel 日志通道，仅用于本地开发和测试。修改配置后，如项目启用了配置缓存，请重新执行 `php artisan config:cache`。

业务代码使用模板别名，各服务商的模板 ID 分别在 `config/sms.php` 中配置：

```php
'templates' => [
    'verification_code' => [
        'log' => 'verification_code',
        'aliyun' => env('ALIYUN_SMS_VERIFICATION_CODE_TEMPLATE'),
        'tencent' => env('TENCENT_SMS_VERIFICATION_CODE_TEMPLATE'),
        'huawei' => env('HUAWEI_SMS_VERIFICATION_CODE_TEMPLATE'),
        'volcengine' => env('VOLCENGINE_SMS_VERIFICATION_CODE_TEMPLATE'),
        'cloopen' => env('CLOOPEN_SMS_VERIFICATION_CODE_TEMPLATE'),
    ],
    'order_paid' => [
        'aliyun' => 'SMS_123456789',
        'tencent' => '1234567',
    ],
],
```

也支持旧的字符串映射，例如 `'verification_code' => 'SMS_123456789'`；未出现在映射中的字符串会作为服务商模板 ID 直接传递。已配置的别名缺少当前驱动的模板 ID 时，会抛出 `SmsException`。

原有 `SMS_VERIFICATION_CODE_TEMPLATE` 环境变量仍可作为阿里云、腾讯云模板配置的默认值；新项目建议分别配置服务商模板。

## 使用

通过依赖注入调用：

```php
use Caiyun\Sms\Contracts\SmsSender;

public function sendCode(SmsSender $sms): void
{
    $sms->send('13800138000', 'verification_code', [
        'code' => '123456',
    ]);
}
```

也可以使用 Facade：

```php
use Caiyun\Sms\Facades\Sms;

$result = Sms::send('13800138000', 'verification_code', ['code' => '123456']);

$result->driver;     // 当前服务商驱动名，例如 huawei
$result->requestId;  // 服务商请求 ID，可能为 null
$result->messageId;  // 服务商消息 ID，可能为 null
$result->raw;        // 服务商原始响应数组
```

成功返回表示服务商接受发送请求。终端送达状态需通过服务商的回执或查询接口确认。

临时指定驱动也会解析模板别名，且不会改变全局默认驱动：

```php
Sms::driver('tencent')->send(
    '13800138000',
    'verification_code',
    ['123456'],
);
```

模板参数按服务商要求传入：

| 驱动 | 参数格式 | 示例 |
| --- | --- | --- |
| `aliyun`、`volcengine` | 关联数组，键对应模板变量名 | `['code' => '123456', 'minutes' => '5']` |
| `tencent`、`huawei`、`cloopen` | 按模板变量顺序传入，建议使用数字索引数组 | `['123456', '5']` |

后三个驱动会按数组顺序提取参数值，不使用键名。火山引擎对非空的数字索引数组会抛出 `SmsException`，避免生成错误的模板变量格式。腾讯云驱动会为未带国际区号的大陆手机号加上 `+86`；国际手机号请传入完整的 `+国家码` 格式。

例如调用新增驱动：

```php
Sms::driver('huawei')->send('13800138000', 'verification_code', ['123456']);
Sms::driver('volcengine')->send('13800138000', 'verification_code', ['code' => '123456']);
Sms::driver('cloopen')->send('13800138000', 'verification_code', ['123456']);
```

### 新增服务商的凭据

- **华为云**：使用短信应用的 APP Key、APP Secret，以及已审核签名的通道号 `sender`。`endpoint` 填写控制台中的 APP 接入地址（HTTPS 基础地址，不包含 `/sms/batchSendSms/v1`）；通用模板还需要填写 `sign_name`。可通过 `status_callback` 配置服务商状态回执地址，回执处理由业务项目实现。参见 [华为云发送短信接口](https://support.huaweicloud.com/api-msgsms/sms_05_0001.html) 和 [PHP 认证示例](https://support.huaweicloud.com/devg-msgsms/sms_04_0003.html)。
- **火山引擎**：使用 AccessKey ID、Secret AccessKey、消息组 ID `sms_account`、已审核签名。使用临时 STS 凭据时填写 `security_token`；`tag` 可作为回执透传字段。签名使用 `volcSMS` 服务名，默认区域为 `cn-north-1`。参见 [SendSms 接口](https://www.volcengine.com/docs/6361/67380?lang=zh) 和 [官方 SDK 短信配置](https://github.com/volcengine/volc-sdk-php/blob/2c6ab5d200be3a69b2b7c22c8cf0920c2e2150fc/src/Service/Sms.php)。
- **容联云**：使用主账户 Account SID、Auth Token、应用 App ID 和模板 ID，签名在服务商控制台的模板中配置。接口通过账户令牌和同一时间戳生成 URL 签名及 Authorization。参见 [容联云模板短信接口](https://console.yuntongxun.com/doc/rest/sms/3_2_2_2.html)。

三个 HTTP 驱动支持 `timeout` 和 `connect_timeout`（秒），默认分别为 10 秒和 5 秒，可通过 `SMS_HTTP_TIMEOUT`、`SMS_HTTP_CONNECT_TIMEOUT` 设置。`endpoint` 必须为 HTTPS 基础地址。

### 异常处理

```php
use Caiyun\Sms\Exceptions\SmsException;
use Caiyun\Sms\Facades\Sms;

try {
    $result = Sms::send('13800138000', 'verification_code', ['code' => '123456']);
} catch (SmsException $exception) {
    report($exception);
    // $exception->providerCode 为服务商业务错误码，可能为 null。
    // 网络或 SDK 错误保存在 $exception->getPrevious() 中。
}
```

## 自定义驱动

在业务项目的服务提供者 `boot()` 中扩展 Manager：

```php
use Caiyun\Sms\SmsManager;
use App\Sms\CustomSmsDriver;

app(SmsManager::class)->extend('custom', function ($app) {
    $config = $app['config']->get('sms.drivers.custom', []);

    return new CustomSmsDriver($config);
});
```

自定义驱动需要实现 `Caiyun\Sms\Contracts\SmsSender`，成功返回 `SmsResult`，失败抛出 `SmsException`。在 `config/sms.php` 的 `drivers.custom` 增加配置，在 `templates` 中增加 `custom` 对应的模板 ID，然后设置 `SMS_DRIVER=custom` 或调用 `Sms::driver('custom')->send(...)`。

内置驱动也支持多个命名配置，例如新增 `'aliyun_backup' => ['driver' => 'aliyun', /* 凭据和签名 */]`。模板优先使用命名配置对应的 ID，未配置时使用驱动类型对应的 ID。

## 其他短信服务商

以下服务商可作为后续扩展方向，**当前包尚未内置这些驱动**：

| 服务商 | 官方产品或文档 | 方向 |
| --- | --- | --- |
| 云片 | [云片短信](https://www.yunpian.com/) | 国内及国际短信 |
| 创蓝云智 | [创蓝](https://www.chuanglan.com/) | 国内及国际短信 |
| Twilio | [SMS API](https://www.twilio.com/en-us/messaging/channels/sms) | 国际短信 |
| Vonage | [SMS API](https://developer.vonage.com/en/messaging/sms/overview) | 国际短信 |
| AWS | [End User Messaging SMS](https://docs.aws.amazon.com/sms-voice/latest/userguide/what-is-sms-mms.html) | 国际短信 |

后续可以按业务客户实际使用的服务商扩展驱动。

## 本地开发

```bash
composer install
composer check
```

`composer check` 依次执行 Composer 配置校验、PHP 语法检查、Pint 风格检查、PHPStan 静态分析和 PHPUnit 测试，GitHub Actions 也执行同一入口。

可以单独运行或修复风格：

```bash
composer pint       # 仅检查风格
composer pint:fix   # 自动修复风格
composer phpstan    # level 8 静态分析
composer test       # PHPUnit 测试
```

[Pint](https://laravel.com/framework/docs/pint) 使用 Laravel preset；[PHPStan](https://phpstan.org/user-guide/rule-levels) 使用 Level 8，检查 `src`、`config`、`scripts`、`tests`，目标 PHP 版本为 8.3，并加载 Larastan、PHPUnit 和 Mockery 类型扩展。`phpstan/stubs` 修正云服务 SDK 中实际可为空的响应字段声明，分析缓存保存在 `.phpstan.cache`。

测试涵盖 Laravel 注册、配置文件实际发布、已有配置保留、日志驱动、模板别名、驱动扩展，以及五家服务商的请求格式与错误处理。火山引擎签名与官方 SDK 生成的固定样例进行比对。SDK 和 HTTP 客户端在测试中使用替身，不会发送真实短信。

目前腾讯云 `common` SDK 的模型序列化在 PHP 8.5 下会触发 `ReflectionProperty::setAccessible()` 弃用提示，需等待上游 SDK 更新。最低依赖组合在 PHP 8.5 下还可能出现旧版 Symfony 的隐式可空参数弃用提示。两种依赖组合的测试断言均通过，这些提示由上游依赖产生。

发布前在其他项目中测试本地包：

```bash
composer config repositories.caiyun-sms path /绝对路径/caiyun-sms
composer require caiyun/sms:dev-main
```

开发分支可通过 `dev-main` 安装；不带版本的公开安装需要 Packagist 中有稳定标签，详见 [发布指南](docs/publishing.md)。

## 许可证

[MIT](LICENSE)
