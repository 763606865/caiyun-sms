<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default SMS Driver
    |--------------------------------------------------------------------------
    */
    'default' => env('SMS_DRIVER', 'log'),

    /*
    |--------------------------------------------------------------------------
    | Template Aliases
    |--------------------------------------------------------------------------
    |
    | Business code should use aliases instead of provider template IDs.
    */
    'templates' => [
        'verification_code' => [
            'log' => 'verification_code',
            'aliyun' => env('ALIYUN_SMS_VERIFICATION_CODE_TEMPLATE', env('SMS_VERIFICATION_CODE_TEMPLATE')),
            'tencent' => env('TENCENT_SMS_VERIFICATION_CODE_TEMPLATE', env('SMS_VERIFICATION_CODE_TEMPLATE')),
            'huawei' => env('HUAWEI_SMS_VERIFICATION_CODE_TEMPLATE'),
            'volcengine' => env('VOLCENGINE_SMS_VERIFICATION_CODE_TEMPLATE'),
            'cloopen' => env('CLOOPEN_SMS_VERIFICATION_CODE_TEMPLATE'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | SMS Drivers
    |--------------------------------------------------------------------------
    */
    'drivers' => [
        'log' => [
            'driver' => 'log',
            'channel' => env('SMS_LOG_CHANNEL'),
        ],

        'aliyun' => [
            'driver' => 'aliyun',
            'access_key_id' => env('ALIYUN_SMS_ACCESS_KEY_ID'),
            'access_key_secret' => env('ALIYUN_SMS_ACCESS_KEY_SECRET'),
            'sign_name' => env('ALIYUN_SMS_SIGN_NAME'),
            'endpoint' => env('ALIYUN_SMS_ENDPOINT', 'dysmsapi.aliyuncs.com'),
        ],

        'tencent' => [
            'driver' => 'tencent',
            'secret_id' => env('TENCENT_SMS_SECRET_ID'),
            'secret_key' => env('TENCENT_SMS_SECRET_KEY'),
            'sdk_app_id' => env('TENCENT_SMS_SDK_APP_ID'),
            'sign_name' => env('TENCENT_SMS_SIGN_NAME'),
            'region' => env('TENCENT_SMS_REGION', 'ap-guangzhou'),
        ],

        'huawei' => [
            'driver' => 'huawei',
            'app_key' => env('HUAWEI_SMS_APP_KEY'),
            'app_secret' => env('HUAWEI_SMS_APP_SECRET'),
            'sender' => env('HUAWEI_SMS_SENDER'),
            // Use the application's access address from the Huawei SMS console.
            'endpoint' => env('HUAWEI_SMS_ENDPOINT'),
            // Required when using a general-purpose template.
            'sign_name' => env('HUAWEI_SMS_SIGN_NAME'),
            'status_callback' => env('HUAWEI_SMS_STATUS_CALLBACK'),
            'timeout' => env('SMS_HTTP_TIMEOUT', 10),
            'connect_timeout' => env('SMS_HTTP_CONNECT_TIMEOUT', 5),
        ],

        'volcengine' => [
            'driver' => 'volcengine',
            'access_key_id' => env('VOLCENGINE_SMS_ACCESS_KEY_ID'),
            'secret_access_key' => env('VOLCENGINE_SMS_SECRET_ACCESS_KEY'),
            'sms_account' => env('VOLCENGINE_SMS_ACCOUNT'),
            'sign_name' => env('VOLCENGINE_SMS_SIGN_NAME'),
            'region' => env('VOLCENGINE_SMS_REGION', 'cn-north-1'),
            'endpoint' => env('VOLCENGINE_SMS_ENDPOINT', 'https://sms.volcengineapi.com'),
            'security_token' => env('VOLCENGINE_SMS_SECURITY_TOKEN'),
            'tag' => env('VOLCENGINE_SMS_TAG'),
            'timeout' => env('SMS_HTTP_TIMEOUT', 10),
            'connect_timeout' => env('SMS_HTTP_CONNECT_TIMEOUT', 5),
        ],

        'cloopen' => [
            'driver' => 'cloopen',
            'account_sid' => env('CLOOPEN_SMS_ACCOUNT_SID'),
            'auth_token' => env('CLOOPEN_SMS_AUTH_TOKEN'),
            'app_id' => env('CLOOPEN_SMS_APP_ID'),
            'endpoint' => env('CLOOPEN_SMS_ENDPOINT', 'https://app.cloopen.com:8883'),
            'timeout' => env('SMS_HTTP_TIMEOUT', 10),
            'connect_timeout' => env('SMS_HTTP_CONNECT_TIMEOUT', 5),
        ],
    ],
];
