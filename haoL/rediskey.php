<?php
/**
 * redis key 键名前缀统一配置中心
 * User: YangChao
 * Date: 2018/9/29
 */

//用户相关redisKey--user(1)
define('SMS_CODE','sms:code:');//手机号对应的验证码
define('USER_INFO','user:info:');//用户信息
define('USER_EXTRA_INFO','user:extra:info:');//用户扩展信息
define('USER_WECHAT_INFO','user:wechat:info:');
define('USER_FOLLOW_EXPERT','user:follow:expert:');
define('USER_FOLLOW_SCHEDULE','user:follow:schedule:');
define('USER_SUBSCRIBE_EXPERT','user:subscribe:expert:');
define('USER_SUBSCRIBE_EXPERT_LIST','user:subscribe:expert:list:');
define('USER_SUBSCRIBE_EXPERT_ALL_LIST','user:subscribe:expert:all:list');
define('USER_BALANCE_INFO','user:balance:info:');  // 用户余额信息

//专家相关redisKey--expert(2)
define('EXPERT_LIST','expert:list:');//专家列表
define('EXPERT_INFO','expert:info:');//专家信息
define('EXPERT_EXTRA_INFO','expert:extra:info:');//专家扩展信息
define('EXPERT_SUBACCOUNT_INFO','expert:subaccount:info:');
define('EXPERT_PRESENT_ACCOUNT_INFO','expert:present:account:info:');
define('EXPERT_PRESENT_ACCOUNT_LIST','expert:present:account:list:');
define('EXPERT_RECOMMOND_TOP','expert:recommond:top');
define('EXPERT_SUBSCRIBE_INFO','expert:subscribe:info:');
define('EXPERT_RATE_INFO','expert:rate:info:');
define('EXPERT_LAST_CHANGE_INFO','expert:Last:change:info:');
define('EXPERT_ALL_LIST', 'expert:all:list:');  // 全部专家列表
define('EXPERT_BET_3', 'expert:bet:3:');  // 三天命中率集合
define('EXPERT_BET_7', 'expert:bet:7:');  // 七天命中率集合
define('EXPERT_BET_30', 'expert:bet:30:');  // 十一天命中率集合
define('EXPERT_BET_11', 'expert:bet:30:');  // 十一天命中率集合
define('EXPERT_HOT_1', 'expert:hot:1:');  // 热门专家命中率最高得集合
define('EXPERT_ALL_LIST_V2', 'expert:sort:list:');  // 全部专家列表
define('EXPERT_BET_RECORD', 'expert:betrecord:');   //近N场命中率
define('EXPERT_L_RED', 'expert:l_red:');   //近几中几

define('EXPERT_HOT_1', 'expert:hot:1:');  // 热门专家得集合
//经销商详情
define('DIST_INFO', 'dist:info:');
define('DIST_EXTRA_INFO', 'dist:extra:info:');

//料相关--resource(3)
define('RESOURCE_LIST','resource:list:');
define('RESOURCE_RECOMMEND_LIST','resource:recommend:list:');
define('RESOURCE_EXPERT_LIST','resource:expert:list:');
define('RESOURCE_EXPERT_TOTAL','resource:expert:total:');
define('RESOURCE_INFO','resource:info:');//料信息
define('RESOURCE_EXTRA_INFO','resource:extra:info:');
define('RESOURCE_DETAIL_INFO','resource:detail:info:');
define('RESOURCE_STATIC_INFO','resource:static:info:');
define('RESOURCE_GROUP_INFO','resource:group:info:');
define('RESOURCE_SCHEDULE_INFO','resource:schedule:info:');
define('RESOURCE_CONTINUITY_RED_NUM','resource:continuity:red:num:');
define('RESOURCE_NOTICE_LIST','resource:notice:list');
define('RESOURCE_UPDATE_NOTICE_LIST','resource:update:notice:list');
define('RESOURCE_VIEW','resource:view');
define('RESOURCE_CRONSOLDNUM_LAST','resource:cronsoldnum:last');  // 料出售数量最后更新时间
define('RESOURCE_CRONSOLDNUM_DATA','resource:cronsoldnum:data');  // 料出售数量需要执行的数据列表
define('RESOURCE_EXPERT_LIST_V2', 'resource:expert:list:v2:');  // 资源列表二

define('RESOURCE_GROUP_NOTICE_SUCCESS', 'resource:group:notice:success');  // 料合买成功通知队列
//红单列表，给分销商加钱用
define('RESOURCE_RED_LIST','resource:red:list');

//订单相关--order(4)
define('ORDER_USER_LIST','order:user:list:');
define('ORDER_EXPERT_LIST','order:expert:list:');
define('ORDER_RESOURCE_LIST','order:resource:list:');
define('ORDER_RESOURCE_SOLD_NUM','order:resource:sold:num:');
define('ORDER_RESOURCE_SOLD_MONEY','order:resource:sold:money:');
define('ORDER_INFO','order:info:');
define('ORDER_EXTRA_INFO','order:extra:info:');
define('ORDER_PAYMENT_CHANNEL','order:payment:channel:');


//赛事相关redisKey--match(5)
define('MATCH_LEAGUE','match:league:');
define('MATCH_LEAGUE_INFO','match:league:info:');
define('MATCH_SCHEDULE_INFO','match:schedule:info:');
define('MATCH_SCHEDULE_RESOURCE_LIST','match:schedule:resource:list:');


//提现相关redisKey--withdraw(6)
define('WITHDRAW_INFO','withdraw:info');
define('WITHDRAW_LIST','withdraw:list');
define('WITHDRAW_CHANGE_LIST','withdraw:change:list:');
define('WITHDRAW_CHANGE_INFO','withdraw:change:info:');

//战绩相关redisKey--betRecord(7)
define('BETRECORD_DATE_INFO','betRecord:date:info:');
define('BETRECORD_DATE_INFO_DESC','betRecord:date:info:desc:');
define('BETRECORD_DATE_EXPERT_LIST','betRecord:date:expert:list:stat:');
define('BETRECORD_EXPERT_INFO','betRecord:expert:info:');
define('BETRECORD_STAT_NEAR_TEN_SCORE','betRecord:stat:near:ten:score:');
define('BETRECORD_STAT_NEAR_TEN_RECORD','betRecord:stat:near:ten:record:');  // 近十战战绩列表

//微信相关redisKey--wechat(8)
define('WECHAT_JS_TICKET','wechat:js:ticket:');
//专家 关注/订阅 通知数
define('WECHAT_NOTICE_EXPERT','wechat:notice:expert:');
//赛事关注发送通知数
define('WECHAT_NOTICE_MATCH','wechat:notice:match:');

//统计相关rediskey--stat(9)

// 其他相关
define('OTHER_BANNER_SORTMAX', 'other:banner:sortmax');
define('OTHER_BANNER_LIST', 'other:banner:list');
