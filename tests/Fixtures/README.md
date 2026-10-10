# 火山引擎签名测试样例

`volcengine-signatures.json` 中的期望签名由火山引擎官方 SDK 的 `SignatureV4::signOnly()` 生成，未使用本包签名实现生成期望值。

参考版本：[volcengine/volc-sdk-php，提交 2c6ab5d](https://github.com/volcengine/volc-sdk-php/blob/2c6ab5d200be3a69b2b7c22c8cf0920c2e2150fc/src/Base/SignatureV4.php)。短信服务名 `volcSMS` 和区域 `cn-north-1` 来自同一版本的 [Sms 服务配置](https://github.com/volcengine/volc-sdk-php/blob/2c6ab5d200be3a69b2b7c22c8cf0920c2e2150fc/src/Service/Sms.php)。

生成时使用固定 UTC 时间 `2026-10-10T08:00:00Z`、AK `test-access-key`、SK `test-secret-key`、服务名 `volcSMS`。将样例的请求方法、路径、Query、请求头以及请求体的 SHA256 摘要填入 `SignParam`，再调用 `signOnly()`。三个样例分别覆盖普通凭据、STS Token，以及自定义端口、区域与不同 Query 顺序；全部为测试凭据。
