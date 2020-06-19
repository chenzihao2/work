<?php
/**
 * 项目常量定义
 * User: WangHui
 * Date: 2018/5/22
 * Time: 11:17
 */
return [
    'JWT-KEY' => "456534153453453434534",
    'PAGE-SIZE' => "30",
    //本地文件存放路径
    // 'FILE-UPLOAD' => '/data/docker-workspace/volume/wwwroot/haoliao/p-api/upload',
    'FILE-UPLOAD' => '/data/wwwroot/haoliao/api/uploads',
    //接口域名
    'DOMAIN_API' => 'https://api.haoliao188.com/',
    //用户端域名
    'DOMAIN_CUSTOMER' => 'https://customer.haoliao188.com/',
    //专家端域名
    'DOMAIN_EXPERT' => 'https://expert.haoliao188.com/',
    //附件域名
    'STATIC_URL' => 'https://hl-static.haoliao188.com/',
    //默认微信登陆渠道标识
    'DEFAULT_WECHATID' => '4',
    //小程序参数
    'WeChatLogin' => [
        1 => [
            //开发环境 公众号名称：精选好料
            'WeChat-Mini-Id' => 'wx144ea638af427946',
            'WeChat-Mini-Key' => '03ad441e9e68b1db5e3ace3b9d0a70f8',
        ],
        2 => [
            //测试环境 公众号名称：新料专家
            'WeChat-Mini-Id' => 'wx7e5cf7c2d3526f09',
            'WeChat-Mini-Key' => '58f64bcc9658346419981df3a8becf01',
        ],
        3 => [
            //预发布环境 公众号名称：给料码
            'WeChat-Mini-Id' => 'wx8d2225dceef93bf6',
            'WeChat-Mini-Key' => '3cdfd60ceb811cdb716a8307c557c73e',
        ],
        4 => [
            //生产环境 公众号名称：好料精选
            'WeChat-Mini-Id' => 'wxe14a6e6d04f394f4',
            'WeChat-Mini-Key' => '80e8a95ad4383cc3d263233cabaaf7a0',
        ]
    ],
    'NewWeChatLogin' => [
        'wx144ea638af427946' => [
            //开发环境 公众号名称：精选好料
            'WeChat-Mini-Id' => 'wx144ea638af427946',
            'WeChat-Mini-Key' => '03ad441e9e68b1db5e3ace3b9d0a70f8',
        ],
        'wx7e5cf7c2d3526f09' => [
            //测试环境 公众号名称：新料专家
            'WeChat-Mini-Id' => 'wx7e5cf7c2d3526f09',
            'WeChat-Mini-Key' => '58f64bcc9658346419981df3a8becf01',
        ],
        'wx8d2225dceef93bf6' => [
            //预发布环境 公众号名称：给料码
            'WeChat-Mini-Id' => 'wx8d2225dceef93bf6',
            'WeChat-Mini-Key' => '03ad441e9e68b1db5e3ace3b9d0a70f8',
        ],
        'wxe14a6e6d04f394f4' => [
            //生产环境 公众号名称：好料精选
            'WeChat-Mini-Id' => 'wxe14a6e6d04f394f4',
            'WeChat-Mini-Key' => '80e8a95ad4383cc3d263233cabaaf7a0',
        ],
        'wx87ad1dd9acf928b0' => [
            // app
            'WeChat-Mini-Id' => 'wx87ad1dd9acf928b0',
            'WeChat-Mini-Key' => '6ea0f38d9b9516e9720b65fe19137f18',
        ]
    ],
    'WeChat-Mini-SessionKey' => '067F7B2C40C4D69F6216FDB5D09CCA8D',
    'WeChat-Customer-Token' => '1C376102DB1CA9856DE32FDB3C2EC277',

    //百度网页
    'BaiDuLogin' => [
        'Api-Key' => '6Ojpsksuh1UiPORGb0klzyGR',
        'Secret-Key' => 'ZK4yGqfVxUNCSl3fyx8Gk2N6RjAric6o'
    ],
    //百度小程序
    'BaiDuSmallRoutineLogin' => [
        'App-Key' => 'eGYzFfVObmVCesEEuOfvfI94jGtAZijK',
        'App-Secret' => 'KubP1eIO4VGDGTHW9hpprwGKAFbzGgEB'
    ],
    'BaiDuSmallRoutineLoginV2' => [
        'App-Key' => 'AgR6c1zc1aLiydvt1vZBu0PaAlQ7aKZV',
        'App-Secret' => 'Uj8XFyoT7UWGdGUsMX29IcGlP9bXmuQW'
    ],

    //QiNiu Config
    //'QiNiu-PUBLIC-KEY' => "fqmz19tQ_yeDyz4Fcc_xODWYcbxfgp48d2aNYXAU",
    //'QiNiu-PRIVATE-KEY' => "jUFsyh_W1gsv_rRmL1FQd9389NgPeIgBcnfumTg5",
    'QiNiu-PUBLIC-KEY' => "tJU18yXLyrHOdamHVfE0o8cC7N2FP60StDOOG-AG",
    'QiNiu-PRIVATE-KEY' => "9FXL9glEr2QfetgGUjltzInsCLWe78SPzGOaaZLo",
    'QiNiu-BUCKET' => "haoliao",
    'QINiu-PATH' => [
        1=>'user',//user
        2=>'resource',//resource
        3=>'complaint',//complaint
        4=>'banner',//banner
        5=>'feedback',//feedback
		6=>'video',//video
    ],
    'APP_NAME' => '好料比分',
    'CheckConfigUrl' => "http://yxapi2.qiudashi.com/api/admin/config/show?",
    'UpdateExpertUrl' => "http://yxapi2.qiudashi.com/api/app/expert/updateExpertExtra?expert_id=",
    'timingGetMatchInformation' => "http://yxapi2.qiudashi.com/api/admin/match/timingGetMatchInformation",
    'addRecord' => "http://yxapi2.qiudashi.com/api/admin/resource/addRecord",
];
