<?php
/**
 * 应用配置定义
 * User: YangChao
 * Date: 2018/9/11
 */

return [
	'appId' => 'HaoLiao',
	'desc' => 'HaoLiao Program API',
	'dirChecked' => 1,
	'dirs' => [
		'cache' => [
			'iswrite' => 1,
			'path' => 'cache',
		],
		'config' => [
			'iswrite' => 1,
			'path' => 'configs',
		],
		'data' => [
			'iswrite' => 1,
			'path' => 'data',
		],
		'logs' => [
			'iswrite' => 1,
			'path' => 'logs',
		],
	],
	'isCrossDomain' => 1,
	'isOriginDebug' => 1,
	'isDebug' => 1,
	'origin' => '*',
	'originDebug' => '',
];
