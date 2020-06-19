<?php
/**
 * mysql配置文件
 * User: YangChao
 * Date: 2018/9/11
 */
return [
    "read" => [
//		"dns" => [
//			"mysql:host=10.10.151.91;dbname=minicode;port=3306",
//			"mysql:host=10.10.151.92;dbname=minicode;port=3306",
//			"mysql:host=10.10.151.93;dbname=minicode;port=3306",
//			"mysql:host=10.10.151.94;dbname=minicode;port=3306"
//		],
        "dns" => "mysql:host=slave.haoliao.com;dbname=haoliao;port=3306",
        "username" => "haoliaoapp",
        "password" => "9cDarbIvmChjxRmFJgD",
        "options"=> [
            \PDO::ATTR_STRINGIFY_FETCHES => false,
            \PDO::ATTR_EMULATE_PREPARES => false,
            \PDO::ATTR_PERSISTENT => false
        ]
    ],
    "write" => [
        "dns" => "mysql:host=master.haoliao.com;dbname=haoliao;port=3306",
        "username" => "haoliaoapp",
        "password" => "9cDarbIvmChjxRmFJgD",
        "options"=> [
            \PDO::ATTR_STRINGIFY_FETCHES => false,
            \PDO::ATTR_EMULATE_PREPARES => false,
            \PDO::ATTR_PERSISTENT => false
        ]
    ]
];
