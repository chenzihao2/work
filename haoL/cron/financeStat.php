<?php
/**
 * 财务数据统计
 * User: WangHui
 * Date: 2018/11/26
 * Time: 下午3:54
 */

require(__DIR__ . "/cron.php");

//
//`id` int(11) NOT NULL AUTO_INCREMENT,
//  `date` date NOT NULL DEFAULT '0000-00-00',
//  `account_flow` int(11) NOT NULL DEFAULT '0' COMMENT '流水总额',
//  `order_total` int(11) NOT NULL DEFAULT '0' COMMENT '交易单数',
//  `buy_user_total` int(11) NOT NULL DEFAULT '0' COMMENT '购买人数',
//  `subscribe_money_total` int(11) NOT NULL DEFAULT '0' COMMENT '订阅总额',
//  `subscribe_total` int(11) NOT NULL DEFAULT '0' COMMENT '专栏订阅数',
//  `refund_money` int(11) NOT NULL DEFAULT '0' COMMENT '退款总额',
//  `refund_total` int(11) NOT NULL DEFAULT '0' COMMENT '退款单数',
//  `refund_user_total` int(11) NOT NULL DEFAULT '0' COMMENT '退款人数',
//  `original_service_fee` int(11) NOT NULL DEFAULT '0' COMMENT '原始服务费',
//  `discount_fee` int(11) NOT NULL DEFAULT '0' COMMENT '优惠发放服务费',
//  `provider_fee` int(11) NOT NULL DEFAULT '0' COMMENT '腾讯服务费',
//  `profit` int(11) NOT NULL DEFAULT '0' COMMENT '毛利',
//  `create_time` int(10) NOT NULL,

/*
后台财务统计各项数据
流水总额=所有料订单总额 - 不中退款订单退款总额+当天订阅专栏订单总额
毛利润=（所有料订单总额 - 不中退款订单退款总额+当天订阅专栏订单总额）*（1-专家分成比例-分销商比例）
成交单数=当天0-24点所有完成的订单数，订阅专栏算1单
购买人数=当天0-24点完成购买的所有用户数
专栏订阅总额=当天0-24点所有完成的订阅专栏订单总额
专栏订阅数=当天0-24点所有完成的订阅专栏订单数量
退款总额=当天不中退款订单退款总额
退款单数=当天不中退款订单数
退款人数=当天不中退款人数
*/


use QK\WSF\Settings\AppSetting;
use QK\HaoLiao\DAL\DALOrder;


$appSetting = AppSetting::newInstance(AppRoot);



use QK\HaoLiao\Model\StatModel;

$totalAmount = "";//总金额
$orderNumber = "";//交易单数
$orderUserNumber = "";//购买人数
$totalAmount = "";//总金额
$statModel = new StatModel();
$statModel->getFinancialStat(time());
