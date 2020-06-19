<?php
/**
 * 支付参数定义
 * User: WangHui
 * Date: 2018/5/22
 * Time: 11:17
 */
return [
	'paymentChannel' => [
		1 => 'WeChat',
		2 => 'AliPay',
        3 => 'BaiDuPay',
	],
    //打款渠道
    //1微信，2支付宝
	'withDrawChannel' => 1,

	'WeChat' => [
        //主体1（元栈）
        1 => [
            1 => [
                //商户平台11
                //商户id
                'mchId' => '1516878131',
                //支付密钥
                'mchSecretKey' => 'SDJy3O9G8pQevPY1Jb5Ayy2LSIzK4yB3',
                'certPath' => APP_ROOT . DIRECTORY_SEPARATOR . 'cert/weChatCert/1516878131/apiclient_cert.pem',
                'keyPath' =>  APP_ROOT . DIRECTORY_SEPARATOR . 'cert/weChatCert/1516878131/apiclient_key.pem',
                'notifyUrl' => "user/1/paynotify/weChatNotify",
                'refundNotifyUrl' => "refundNotify/",
                //1可用，0不可用
                'status' => 1,
            ]
        ],
        2 => [
            20 => [
                //商户平台11
                //商户id
                'mchId' => '1521536081',
                //支付密钥
                'mchSecretKey' => 'SDJy3O9G8pQevPY1Jb5Ayy2LSIzK4yB3',
                'certPath' => APP_ROOT . DIRECTORY_SEPARATOR . 'cert/weChatCert/1521536081/apiclient_cert.pem',
                'keyPath' =>  APP_ROOT . DIRECTORY_SEPARATOR . 'cert/weChatCert/1521536081/apiclient_key.pem',
                'notifyUrl' => "user/1/paynotify/weChatNotify",
                'refundNotifyUrl' => "refundNotify/",
                //1可用，0不可用
                'status' => 1,
            ]
        ],
        3 => [
            30 => [
                //好料精选
                //商户id
                'mchId' => '1520161711',
                //支付密钥
                'mchSecretKey' => 'SDJy3O9G8pQevPY1Jb5Ayy2LSIzK4yB3',
                'certPath' => APP_ROOT . DIRECTORY_SEPARATOR . 'cert/weChatCert/1520161711/apiclient_cert.pem',
                'keyPath' =>  APP_ROOT . DIRECTORY_SEPARATOR . 'cert/weChatCert/1520161711/apiclient_key.pem',
                'notifyUrl' => "user/1/paynotify/weChatNotify",
                'refundNotifyUrl' => "refundNotify/",
                //1可用，0不可用
                'status' => 1,
            ]
        ],
        4 => [
            40 => [
                //好料精选
                //商户id
                'mchId' => '1520161711',
                //支付密钥
                'mchSecretKey' => 'SDJy3O9G8pQevPY1Jb5Ayy2LSIzK4yB3',
                'certPath' => APP_ROOT . DIRECTORY_SEPARATOR . 'cert/weChatCert/1520161711/apiclient_cert.pem',
                'keyPath' =>  APP_ROOT . DIRECTORY_SEPARATOR . 'cert/weChatCert/1520161711/apiclient_key.pem',
                'notifyUrl' => "user/1/paynotify/weChatNotify",
                'refundNotifyUrl' => "refundNotify/",
                //1可用，0不可用
                'status' => 0,
            ],
            41 => [
                //商户id
                'mchId' => '1521536081',
                //支付密钥
                'mchSecretKey' => 'SDJy3O9G8pQevPY1Jb5Ayy2LSIzK4yB3',
                'certPath' => APP_ROOT . DIRECTORY_SEPARATOR . 'cert/weChatCert/1521536081/apiclient_cert.pem',
                'keyPath' =>  APP_ROOT . DIRECTORY_SEPARATOR . 'cert/weChatCert/1521536081/apiclient_key.pem',
                'notifyUrl' => "user/1/paynotify/weChatNotify",
                'refundNotifyUrl' => "refundNotify/",
                //1可用，0不可用
                'status' => 1,
            ]
        ]
	],
    'NewWeChat' => [
        // default
        '1521536081' => [
            //商户id
            'mchId' => '1521536081',
            //支付密钥
            'mchSecretKey' => 'SDJy3O9G8pQevPY1Jb5Ayy2LSIzK4yB3',
            'certPath' => APP_ROOT . DIRECTORY_SEPARATOR . 'cert/weChatCert/1521536081/apiclient_cert.pem',
            'keyPath' =>  APP_ROOT . DIRECTORY_SEPARATOR . 'cert/weChatCert/1521536081/apiclient_key.pem',
            'notifyUrl' => "user/1/paynotify/weChatNotify",
            'refundNotifyUrl' => "refundNotify/"
        ],
        // app
        '1537647631' => [
            //商户id
            'mchId' => '1537647631',
            //支付密钥
            'mchSecretKey' => 'dVlDzYUUkQHB0YXLL9vlCaMFDzlvmLt9',
            'certPath' => APP_ROOT . DIRECTORY_SEPARATOR . 'cert/weChatCert/1537647631/apiclient_cert.pem',
            'keyPath' =>  APP_ROOT . DIRECTORY_SEPARATOR . 'cert/weChatCert/1537647631/apiclient_key.pem',
            'notifyUrl' => "user/1/paynotify/weChatNotify",
            'refundNotifyUrl' => "refundNotify/",
        ],
    ],
	'AliPay' => [
	    //支付宝账号1
        11 => [
            //元栈
            //商户id
            'appId' => '2018112262311097',
            //商户应用私钥
            'merchantPrivateKey' => 'MIIEpAIBAAKCAQEAxNUkwcdpqkfwFSPTA1z34tRT26IbhL6rJQzmJB4maV26AiD1n/cMYKLp9toCKvxmfuTd+7qR8LlPgDOiGV5bMC5Uvx5sadW4uuw03fzVfkcR5PdFg0IPmkookZ2E3E8NR+jN3eXOsxY9mh3hcHZhJX8E0WLXur9tQ8XDO0o9Pl9aJk2+8gXM/hPON5UsFFbhM39vumstKC7MXK/QlqFnHLonVWk9m80ofYAfI545roh/udipMRjo1kWQK/P214NOu0tT9buxdGOJ9+nGPBu8B221CC9lN327mHp/HwDfa1QHz1GODKh797vxL0EmiGQYt9+yWpJSsUh1Rh9qT3YfQwIDAQABAoIBAF5iXqfVOaP/ru5UKWFZeTx52GRfTZbP3z16+/ihxIWN/h37NA0q5/KG7G4EiDmYooWCtbd59XVbRvYQzFAh4NQvw0+KBS6S32wyy/8OuEk/RyNmNx0D1nuDyARbZlRGS/YJen5HLmDN72gxn+LPwTG7wNGu9geEt55h/IY3yTpYo5dOYuNZE8CmnSWOL1vSuljwgap6W7z6B3WWz7mXJA9jcRPHcP4igHaiMAhsLDoo6ejxvunCMqfOZrqOf06AnJLjd4PmAxUzfb5qtOao1GZe2KxOoHS4JKDa33ivy7NiOyY2JCwN+9FfipIGa9OCJbnSvdalbud4ZezskEIIA/ECgYEA4WAB7ThVLgnD4vEsHyLw4gkRLJtqxHRjmJ7OVQz4CfX1yarNps1Or8xuFZs3bc4aen2iBMI7kz+zKPfLAo4Vu+FypljALrOrJzVXTzDboKB8Au4OR6T8k/aXe+rPnBLtYqZW+jlXVWDjAMMoY2W7EbWcg91EIgItv+ReE3r//HUCgYEA35Q/IEGRSC8fyxze3l0GNpg7xIGNnLI/dcLQrLArXM4Tfla2/IGZSJqhd/0/cgGGvDbr2IX9ksP4QgRdZmwQ5QRNvBHRZ1K12lAyaVjq8Fkmlo8MVjFyXeTJaiyntFrgwEyhzEKbp/zLDwGebM2p8ivaS36hi4GCDh1r7PVFldcCgYEAmvQKfxIDJqMaJjdQ8ZtxaMd5ImU81BN9wpo1HK7M/vQ36E7iFDQGTMJOUdalFn7lH6CvO3xbv5LFWH59+qS79rA7xnkwsStgmpWHEPBzoI2WleEcuk+KRST+4/j+gr1Ur0XUeTfhftcdXBHR+/0e0D9AfD0uMf+zesLFl7kytV0CgYEA1Xln+LWCMUkFc1silXB+0TvzvUHx487x9s/HjUs8bAU76aKEX36izAcQ609796/rZOSPthLhtfO1o9slDvlZ/EFqs4rTxXLcvhFawOmskUaeKJ13KwlVaL9dbSosCnHHLPU+e5iRpQkjHTXvfXW1scwbR4AmYNyMQEvpU7ww78sCgYBQY2WdEjCDR+slmh/udOL4haJ8yQYVVNMl1SiAV7Kip6EM+owXZNvaGGyI2pT2Q6HHUb4dT5ftLjlpznPcxFTSMgv965sYfXr1szqqYjIy4C9C9T1nrBTNPFtRy0jv6hDZz3KW9esKYJueqFOKgS73QXghd0+WBlwnZRNphF5bww==',
            //商户应用公钥
            'merchantPublicKey' => 'MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAxNUkwcdpqkfwFSPTA1z34tRT26IbhL6rJQzmJB4maV26AiD1n/cMYKLp9toCKvxmfuTd+7qR8LlPgDOiGV5bMC5Uvx5sadW4uuw03fzVfkcR5PdFg0IPmkookZ2E3E8NR+jN3eXOsxY9mh3hcHZhJX8E0WLXur9tQ8XDO0o9Pl9aJk2+8gXM/hPON5UsFFbhM39vumstKC7MXK/QlqFnHLonVWk9m80ofYAfI545roh/udipMRjo1kWQK/P214NOu0tT9buxdGOJ9+nGPBu8B221CC9lN327mHp/HwDfa1QHz1GODKh797vxL0EmiGQYt9+yWpJSsUh1Rh9qT3YfQwIDAQAB',
            //支付宝公钥
            'aliPayPublicKey' => 'MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAzT8kW4uhXISui60EGl4DgTtqz0GBQlO8t7Gjp10wJ4oQHyYCWTz4x+/UMfNtIUXgRBZ401A7fKaUd9WDHm6Qza9TGPmxPEHzTWSAptdlk9iLgyfbMIvUMK0UjDyVvDl8NNF8rmQN8+3z0wWgfU/4erIUuWXkCsmwz+e93WgrW/fgPkeW3lZmZVr5XTUvxDhMXX6ZRf9X8i3HrkObYCq2+jqVqVkVyfIAjNmUNKoRd46nbNjbKtFPJ5fv/Od8F9o5L8VTZJb5ZeUGkIkEUFKG9kRijDvEbArL19p8MfrN63tlzbGsIs896cbPoO7m/3kEJxItC1bDEAAH3yUtJ0pUswIDAQAB',
            //支付回调地址
            'notifyUrl' => "user/1/paynotify/aliPayNotify",
            //支付完成后跳转地址
            'returnUrl' => "payed.html",
            //1可用，0不可用
            'status' => 1,
        ]
	],
    //百度支付
    'BaiDuPay' => [
        50 => [
            'dealId' => '2416334941',
            'appKey' => 'MMMu5i',
            'publicKeyPath' => APP_ROOT . DIRECTORY_SEPARATOR . 'library' . DIRECTORY_SEPARATOR . 'baiduPay' . DIRECTORY_SEPARATOR . 'rsa' . DIRECTORY_SEPARATOR . 'rsa_public_key.pem',
            'privateKeyPath' => APP_ROOT . DIRECTORY_SEPARATOR . 'library' . DIRECTORY_SEPARATOR . 'baiduPay' . DIRECTORY_SEPARATOR . 'rsa' . DIRECTORY_SEPARATOR . 'rsa_private_key.pem',
            'baiDuPublicKey' => 'MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQDf1k9rAfSOc8eUn8iDe5vbIMz3ad0EH2TwioU+2JFdIL5uTOMsPB4gh4xgcnM44PEUTrZP5B4E1Lke6gbUQA3exK2WitdH3hyZdm+N3Y3lpH3estr9xtLA8QK1tCsizqdRuaSbD3dh/gaEmId+rzmcz9iVOc/0hem59R1+7PEFtwIDAQAB',
            //支付回调地址
            'notifyUrl' => "user/2/paynotify/baiDuPayNotify",
            //1可用，0不可用
            'status' => 1,
        ]
    ],
	//微信打款账号信息
	'WeChatWithDraw' => [
        //商户id
        'MchId' => '1513857091',
        //支付密钥
        'Mch-Secret-Key' => 'SDJy3O9G8pQevPY1Jb5Ayy2LSIzK4yB3',
        //证书路径
        'CertPath' => '',
        //key路径
        'KeyPath' => '',
        //打款服务器ip
        "IP" => "10.10.139.114"

    ],
	//支付宝打款账号信息
	'AliPayWithDraw' => [
        //商户id
        'AppId' => '1513857091',
        //支付密钥
        'Public_Key' => '',
        'Private_Key' => '',
        //打款服务器ip
        "AliPay_Public_Key" => ""

    ],
    //支付商品名称马甲
    'Vest' => [
        "好料精选-咨询服务",
    ],
    'VcRate' => 1.0
];