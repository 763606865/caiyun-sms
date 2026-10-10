# 发布 caiyun/sms

目标是在业务项目中只执行 `composer require caiyun/sms` 就能安装本包。代码配置完成后，还需要将 Git 仓库和稳定版本注册到 Packagist。

## 首次发布

1. 在包仓库运行检查：

   ```bash
   composer install
   composer check
   ```

2. 检查并提交本次包文件，然后将 `main` 推送到公开仓库：

   ```bash
   git status
   git add composer.json README.md LICENSE .gitignore .gitattributes .env.example .github config src tests scripts phpunit.xml.dist pint.json phpstan.neon.dist phpstan docs
   git commit -m "Prepare caiyun/sms for its first release"
   git push origin main
   ```

   当前仓库地址为 [763606865/caiyun-sms](https://github.com/763606865/caiyun-sms)。依赖目录、环境密钥、IDE 文件和本地 lock 文件不包含在上述提交中。

3. 创建并推送稳定版本标签：

   ```bash
   git tag -a v1.0.0 -m "Release v1.0.0"
   git push origin v1.0.0
   ```

   Composer 从 Git 标签推断版本；不要在 `composer.json` 中写固定 `version`。只有 `dev-main` 时，默认稳定性设置的项目无法通过不带版本的命令安装。

4. 登录 [Packagist](https://packagist.org/)，在 [Submit](https://packagist.org/packages/submit) 提交 `https://github.com/763606865/caiyun-sms`。确认解析出的包名为 `caiyun/sms`，并确认提交账号有权使用 `caiyun` vendor 名称。若该包名或 vendor 已被他人占用，需要联系相应维护者或调整命名。

5. 在 Packagist 页面启用 GitHub 自动更新，确认包页面显示 `v1.0.0` 后，在新的 Laravel 13 项目中执行：

   ```bash
   composer require caiyun/sms
   php artisan vendor:publish --provider="Caiyun\Sms\SmsServiceProvider" --tag=sms-config
   ```

   配置文件会发布到业务项目的 `config/sms.php`，其中包含 `log`、`aliyun`、`tencent`、`huawei`、`volcengine`、`cloopen` 六个驱动配置。发布命令默认保留已有文件；升级时可以手动同步新增配置，或备份后使用 `--force` 重新发布。

   默认日志驱动可立即调用；发送真实短信需要配置对应服务商凭据、签名和模板，并设置 `SMS_DRIVER`。

## 后续发布

每次发布先运行 `composer check` 并检查 GitHub Actions。按语义化版本创建新的稳定标签：修复使用补丁版本，例如 `v1.0.1`；兼容的新功能使用次版本，例如 `v1.1.0`；不兼容改动使用主版本，例如 `v2.0.0`。不要移动已发布的标签。

`.gitattributes` 将测试、CI 和开发工具从发行归档中排除。`composer.lock` 仅用于本地验证，不决定业务项目安装的依赖版本。

发布流程依据 [Composer library 文档](https://getcomposer.org/doc/02-libraries.md) 和 [版本约束文档](https://getcomposer.org/doc/articles/versions.md)。
