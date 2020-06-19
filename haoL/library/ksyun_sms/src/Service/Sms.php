<?php

namespace Ksyun\Service;

use Ksyun\Base\V4Curl;
class Sms extends V4Curl 
{
    protected function getConfig()
    {
        return [
            'host' => 'https://ksms.api.ksyun.com',
            'config' => [
                'timeout' => 5.0,
                'verify'  => false,
                'headers' => [
                    'Accept' => 'application/json'
                ],
                'v4_credentials' => [
                    'region' => 'cn-beijing-6',
                    'service' => 'ksms',
                ],
            ],
        ];
    }

    

    protected $apiList = [
        'SendSms' => [
            'url' => '/',
            'method' => 'post',
        ],
    ];
}

