#数据库修改记录
###2020-03-05
/* 3:47:43 PM haoliao haoliao */ ALTER TABLE `hl_news` CHANGE `target` `target` VARCHAR(32)  CHARACTER SET utf8  COLLATE utf8_general_ci  NOT NULL  DEFAULT ''  COMMENT '作者';

/* 3:49:58 PM haoliao haoliao */ ALTER TABLE `hl_news` ADD `is_recommend` TINYINT(1)  NOT NULL  DEFAULT '0'  COMMENT '0普通  1推荐  2置顶'  AFTER `article_source`;

/* 3:51:57 PM haoliao haoliao */ ALTER TABLE `hl_news` ADD `comment` TINYINT(1)  NULL  DEFAULT '1'  COMMENT '0关闭评论 1开启评论'  AFTER `is_recommend`;

/* 6:26:47 PM haoliao haoliao */ ALTER TABLE `hl_order` CHANGE `order_param` `order_param` INT(11)  NOT NULL  COMMENT '购买参数 订单类型为 1 时: 订阅专家ID 2 时 料ID 3 时 资讯id 4 视频id';

/* 3:51:57 PM haoliao haoliao */ ALTER TABLE `hl_news_video` ADD `comment` TINYINT(1)  NULL  DEFAULT '1'  COMMENT '0关闭评论 1开启评论'  AFTER `is_recommend`;

###2020-03-12
CREATE TABLE `check_config` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '自增ID',
  `channel` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '渠道名称',
  `version` smallint(4) NOT NULL DEFAULT 000 COMMENT '版本号',
  `bottom` CHAR(20) NOT NULL DEFAULT '' COMMENT '底栏',
  `toptab` varCHAR(128) NOT NULL DEFAULT '' COMMENT '顶栏',
  `pay` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '支付',
  `match` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '比赛',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`)
) ENGINE=INNODB DEFAULT CHARSET=utf8 COMMENT='过审配置';


###2020-03-17
/* 3:58:43 PM haoliao haoliao */ ALTER TABLE `hl_news` ADD `fabulous` INT  NOT NULL  DEFAULT '0'  COMMENT '点赞数'  AFTER `comment`;


###2020-03-20
/* 3:57:56 PM haoliao haoliao */ ALTER TABLE `hl_user_expert` CHANGE `is_recommend` `is_recommend` TINYINT(1)  NOT NULL  DEFAULT '0'  COMMENT 'app 推荐 0:未推荐  1:置顶推荐1 2:置顶推荐2 3:置顶推荐3';
/* 3:58:13 PM haoliao haoliao */ ALTER TABLE `hl_user_expert` CHANGE `is_placement` `is_placement` TINYINT(2)  NOT NULL  DEFAULT '0'  COMMENT 'app 置顶专家 全部专家排序';
/* 3:58:22 PM haoliao haoliao */ ALTER TABLE `hl_user_expert` CHANGE `is_wx_recommend` `is_wx_recommend` TINYINT(1)  UNSIGNED  NOT NULL  DEFAULT '0'  COMMENT 'h5 微信推荐';
/* 3:58:28 PM haoliao haoliao */ ALTER TABLE `hl_user_expert` CHANGE `is_wx_placement` `is_wx_placement` TINYINT(1)  UNSIGNED  NOT NULL  DEFAULT '0'  COMMENT 'h5 微信置顶';


/* 4:01:16 PM haoliao haoliao */ ALTER TABLE `hl_user_expert` ADD `bs_recommend` TINYINT(1)  NULL  DEFAULT '0'  COMMENT '足球篮球推荐'  AFTER `reply_content`;
/* 4:01:19 PM haoliao haoliao */ ALTER TABLE `hl_user_expert` CHANGE `bs_recommend` `bs_recommend` TINYINT(1)  NOT NULL  DEFAULT '0'  COMMENT '足球篮球推荐';

/* 4:55:15 PM haoliao haoliao */ ALTER TABLE `hl_user_expert` CHANGE `bs_recommend` `bs_recommend` CHAR(2)  NOT NULL  DEFAULT '0'  COMMENT '足球篮球推荐';


##2020-03-23
/* 2:52:43 PM haoliao haoliao */ ALTER TABLE `hl_user_expert_extra` ADD `recent_red` TINYINT(1)  NOT NULL  DEFAULT '0'  COMMENT '近期连红'  AFTER `max_bet_record_v2`;
/* 3:26:48 PM haoliao haoliao */ ALTER TABLE `hl_user_expert_extra` ADD `recent_record` VARCHAR(128)  NULL  DEFAULT ''  AFTER `recent_red`;
/* 3:27:09 PM haoliao haoliao */ ALTER TABLE `hl_user_expert_extra` CHANGE `recent_record` `recent_record` VARCHAR(128)  CHARACTER SET utf8  COLLATE utf8_general_ci  NOT NULL  DEFAULT ''  COMMENT '近期战绩';


##2020-03-24
/* 11:22:49 AM haoliao haoliao */ ALTER TABLE `hl_user_expert` ADD `red_top_level` TINYINT  NOT NULL  DEFAULT '21'  COMMENT '红人榜置顶 默认21  '  AFTER `bs_recommend`;
/* 2:37:47 PM haoliao haoliao */ ALTER TABLE `hl_user_expert_extra` ADD `red_man_show` TINYINT  NOT NULL  DEFAULT '0'  COMMENT '0:近期连红   1， 2 ，3 近期战绩'  AFTER `recent_record`;
/* 3:02:10 PM haoliao haoliao */ ALTER TABLE `hl_user_expert_extra` ADD `recent_ten` VARCHAR(20)  NULL  DEFAULT ''  AFTER `red_man_show`;
/* 3:02:21 PM haoliao haoliao */ ALTER TABLE `hl_user_expert_extra` CHANGE `recent_ten` `recent_ten` VARCHAR(20)  CHARACTER SET utf8  COLLATE utf8_general_ci  NOT NULL  DEFAULT ''  COMMENT '近十战绩';
/* 6:20:34 PM haoliao haoliao */ ALTER TABLE `hl_news` ADD `dry_top_level` TINYINT  NOT NULL  DEFAULT '10'  COMMENT '专家干货置顶 默认10'  AFTER `fabulous`;


###2020-03-25
/* 2:22:45 PM haoliao haoliao */ ALTER TABLE `hl_user_expert` CHANGE `bs_recommend` `bs_recommend` TINYINT(2)  NOT NULL  DEFAULT '0'  COMMENT '足球篮球推荐 1-7足球 8-14篮球';

###2020-03-26
/* 5:44:53 PM haoliao haoliao */ ALTER TABLE `hl_user_expert_extra` CHANGE `profit_all` `profit_all` INT(11)  NOT NULL  DEFAULT '0'  COMMENT '回报率';

###2020-03-27
/* 4:26:57 PM haoliao haoliao */ ALTER TABLE `hl_user_expert_extra` ADD `bet_rate` VARCHAR(20)  NOT NULL  DEFAULT ''  COMMENT '近7，10，20，30场命中'  AFTER `recent_ten`;

###2020-03-31
/* 10:19:05 AM haoliao haoliao */ ALTER TABLE `hl_resource` ADD `match_type` TINYINT  NOT NULL  DEFAULT '0'  COMMENT '1足球  2篮球 0其他'  AFTER `remarks`;
/* 10:20:28 AM haoliao haoliao */ ALTER TABLE `hl_resource` CHANGE `is_schedule_over` `is_schedule_over` TINYINT(1)  NOT NULL  DEFAULT '0'  COMMENT '是否完赛  1:已完赛  0:未完赛  2未关联比赛';


#评论表

CREATE TABLE `hl_comment` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'id，自增长',
  `user_id` int(11) NOT NULL DEFAULT 0 COMMENT '评论者id',
  `topic_id` int(10) NOT NULL  COMMENT '主题id（文章/视频）',
  `topic_type` tinyint(1) NOT NULL DEFAULT 1 COMMENT '主题类型 1：文章；2：视频',
  `topic_title` varchar(150) NOT NULL DEFAULT '' COMMENT '主题标题：文章标题/视频标题',
  `author_id` int(10) NOT NULL DEFAULT 0 COMMENT '作者id',
  `nick_name` varchar(128) NOT NULL DEFAULT ''  COMMENT '昵称',
  `headimgurl` varchar(150) NOT NULL DEFAULT ''  COMMENT '头像',
  `content_type` tinyint(1) NOT NULL DEFAULT 1 COMMENT '内容类型1：文字；2：图片；3：emoji',
  `content` text COMMENT '内容',
  `image` varchar(255) NOT NULL DEFAULT '' COMMENT '评论图片，多个以,号隔开',
  `prase_count` int(10) NOT NULL DEFAULT 0 COMMENT "点赞数量",
  `status` tinyint(1) DEFAULT 0 COMMENT '审核状态 0：未审核；1：通过，2：拒绝',
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '消息发送时间',
  `update_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  `is_del` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否删除 0：未删；1：已删',
  PRIMARY KEY (`id`),
  KEY `in_topic_id` (`topic_id`),
  KEY `in_topic_type` (`topic_type`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COMMENT='评论表';





#回复表
CREATE TABLE `hl_comment_reply` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'id，自增长',
  `comment_id` int(10) NOT NULL  COMMENT '评论id',
  `topic_id` int(10) NOT NULL  COMMENT '主题id（文章/视频）方便统计',
  `topic_type` tinyint(1) NOT NULL DEFAULT 1 COMMENT '主题类型 1：文章；2：视频',
  `reply_id` int(10) NOT NULL  COMMENT '回复目标id',
  `nick_name` varchar(128) NOT NULL DEFAULT ''  COMMENT '昵称',
  `to_nick_name` varchar(128) NOT NULL DEFAULT ''  COMMENT '目标用户昵称',
  `headimgurl` varchar(150) NOT NULL DEFAULT ''  COMMENT '头像',
  `content_type` tinyint(1) NOT NULL DEFAULT 1 COMMENT '内容类型1：文字；2：图片；3：emoji',
  `content` text COMMENT '内容',
  `image` varchar(255) NOT NULL DEFAULT '' COMMENT '评论图片，多个以,号隔开',
  `prase_count` int(10) NOT NULL DEFAULT 0 COMMENT "点赞数量",
  `from_uid` int(11) NOT NULL DEFAULT 0 COMMENT '回复用户id',
  `to_uid` int(11) NOT NULL DEFAULT 0 COMMENT '目标用户id',
  `status` tinyint(1) DEFAULT 0 COMMENT '审核状态 0：未审核；1：通过，2：拒绝',
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `update_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  `is_del` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否删除 0：未删；1：已删',
  PRIMARY KEY (`id`),
  KEY `in_topic_id` (`topic_id`),
  KEY `in_from_uid` (`from_uid`),
  KEY `in_to_uid` (`to_uid`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COMMENT='回复评论表';

#点赞记录表
CREATE TABLE `hl_fabulous` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '主键',
  `user_id` int(11) NOT NULL  COMMENT '用户id',
  `type` tinyint(1) NOT NULL DEFAULT 1 COMMENT '类型 1：文章；2：视频；3：评论，4：回复',
  `comment_type` tinyint(1) NOT NULL DEFAULT 0 COMMENT '评论类型 1：文章；2：视频；(类型为评论时有效)',
  `topic` int(11) NOT NULL DEFAULT 0 COMMENT '主题id（文章id,视频id，评论id，被回复id）',
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '点赞时间',
  `update_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `in_user_id` (`user_id`),
  KEY `in_type` (`type`),
  KEY `in_topic` (`topic`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COMMENT='点赞记录表';




#通知表
CREATE TABLE `hl_interact_notice` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'id，自增长',
  `user_id` int(11) NOT NULL DEFAULT 0 COMMENT '互动人id',
  `to_uid` int(11) NOT NULL DEFAULT 0 COMMENT '接收人id',
  `nick_name` varchar(128) NOT NULL DEFAULT ''  COMMENT '互动人昵称',
  `headimgurl` varchar(150) NOT NULL DEFAULT ''  COMMENT '互动人头像',
  `author_id` int(11) NOT NULL DEFAULT 0 COMMENT '作者id',
  `type` tinyint(1) DEFAULT 0 COMMENT '类型 1：点赞；2：评论/回复',
  `topic_id` int(11) NOT NULL DEFAULT 0 COMMENT '主题id(文章id/视频id/评论id/回复id)',
  `topic_type` tinyint(1) DEFAULT 0 COMMENT '主题类型 1：文章；2：视频，3：评论，4回复',
  `status` tinyint(1) DEFAULT 0 COMMENT '审核状态 0：未查看；1：查看',
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `update_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `in_topic_id` (`topic_id`),
  KEY `in_author_id` (`author_id`),
  KEY `in_user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COMMENT='互动通知表';




#举报表


CREATE TABLE `hl_report` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'id，自增长',
  `user_id` int(11) NOT NULL DEFAULT 0 COMMENT '举报人id',
  `nick_name` varchar(128) NOT NULL DEFAULT ''  COMMENT '举报人昵称',
  `to_user_id` int(11) NOT NULL DEFAULT 0 COMMENT '被举报人id',
  `to_nick_name` varchar(128) NOT NULL DEFAULT ''  COMMENT '被举报人昵称',
  `author_id` int(11) NOT NULL DEFAULT 0 COMMENT '作者id',
  `report_type` int(5) NOT NULL DEFAULT 0 COMMENT '举报类型',
  `reason` varchar(128) NOT NULL DEFAULT '' COMMENT '举报原因',
  `topic_id` int(11) NOT NULL DEFAULT 0 COMMENT '主题id(文章id/视频id/评论id/回复id)',
  `topic_type` tinyint(1) DEFAULT 0 COMMENT '主题类型 1：文章；2：视频，3：评论，4回复',
  `results` varchar(60) DEFAULT '' COMMENT '处理结果',
  `reply_content` varchar(128) DEFAULT '' COMMENT '回复内容',
  `is_reply` tinyint(1) DEFAULT 0 COMMENT '回复状态 0：未回复；1：已回复',
  `status` tinyint(1) DEFAULT 0 COMMENT '审核状态 0：未处理；1：已处理',
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `update_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  `deleted` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0:正常 1:已删除',
  PRIMARY KEY (`id`),
  KEY `in_topic_id` (`topic_id`),
  KEY `in_author_id` (`author_id`),
  KEY `in_user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COMMENT='举报表';



#敏感词表

CREATE TABLE `hl_sensitives` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `words` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `level` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1:禁止 2:危险 3:敏感',
  `ctime` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `utime` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  `deleted` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0:正常 1:已删除',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='敏感词表';


#微信push 表结构

CREATE TABLE `hl_wechat_notice` (
  `id` int(11)  NOT NULL AUTO_INCREMENT,
  `title` varchar(128) NOT NULL DEFAULT '' COMMENT'标题',
  `content` varchar(255) NOT NULL DEFAULT'' COMMENT '内容',
  `rid` int(11) NOT NULL DEFAULT'0' COMMENT '方案id',
  `expert_id` int(11) NOT NULL DEFAULT'0' COMMENT '专家id',
  `count` int(11) NOT NULL DEFAULT'0' COMMENT '发送人数',
  `complete_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '完成时间',
  `remarks` varchar(60) NOT NULL DEFAULT'' COMMENT '备注',
  `type` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0:全部，1：指定用户',
  `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0:发送中，1：已发送',
  `ctime` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `utime` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8  COMMENT='微信通知表';




#用户设备信息表
CREATE TABLE `hl_device_info` (
  `id` int(11)  NOT NULL AUTO_INCREMENT,
  `user_id` int(11)  NOT NULL  default 0 COMMENT '用户id',
  `first_install_datetime` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP  COMMENT'首次安装时间',
  `last_update_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP  COMMENT'应用上次更新时间',
  `first_install_channel` varchar(32) NOT NULL DEFAULT '' COMMENT '首次安装渠道',
  `install_channel` varchar(32) NOT NULL DEFAULT'' COMMENT '当前安装渠道',
  `app_version` varchar(32) NOT NULL DEFAULT'' COMMENT 'APP 当前版本',
  `device_version` varchar(32) NOT NULL DEFAULT'' COMMENT '设备版本',
  `readable_version` varchar(32) NOT NULL DEFAULT'' COMMENT '应用程序可读版本',
  `application_name` varchar(32) NOT NULL DEFAULT'' COMMENT '当前应用名称',
  `build_number` varchar(32) NOT NULL DEFAULT'' COMMENT '应用编译版本号',
  `bundle_id` varchar(32) NOT NULL DEFAULT'' COMMENT '应用程序包标识符',
  `font_scale` varchar(32) NOT NULL DEFAULT'' COMMENT '设备字体大小',
  `carrier` varchar(32) NOT NULL DEFAULT'' COMMENT '设备运营商',
  `manufacturer` varchar(32) NOT NULL DEFAULT'' COMMENT '设备制造商',
  `max_memory` varchar(32) NOT NULL DEFAULT'' COMMENT 'JVM试图使用的最大内存量(字节)',
  `phone_number` varchar(32) NOT NULL DEFAULT'' COMMENT '电话号码(字节)',
  `serial_number` varchar(32) NOT NULL DEFAULT'' COMMENT '设备唯一序列号',
  `system_version` varchar(32) NOT NULL DEFAULT'' COMMENT '操作系统版本',
  `system_name` varchar(32) NOT NULL DEFAULT'' COMMENT '系统名称',
  `total_disk_capacity` varchar(32) NOT NULL DEFAULT'' COMMENT '完整磁盘空间大小(字节)',
  `free_disk_storage` varchar(32) NOT NULL DEFAULT'' COMMENT '剩余存储容量(字节)',
  `total_memory` varchar(32) NOT NULL DEFAULT'' COMMENT '设备总内存(字节)',
  `resolution` varchar(32) NOT NULL DEFAULT'' COMMENT '设备分辨率',
  `device_ip` varchar(32) NOT NULL DEFAULT'' COMMENT '	设备 IP',
  `country` varchar(32) NOT NULL DEFAULT'' COMMENT '国家',
  `province` varchar(32) NOT NULL DEFAULT'' COMMENT '省',
  `city` varchar(32) NOT NULL DEFAULT'' COMMENT '市',
  `district` varchar(64) NOT NULL DEFAULT'' COMMENT '区',
  `idfa` varchar(64) NOT NULL DEFAULT'' COMMENT 'IOS 广告标示符',
  `mac` varchar(64) NOT NULL DEFAULT'' COMMENT 'MAC 地址',
  `android_id` varchar(64) NOT NULL DEFAULT'' COMMENT 'Android ID',
  `idfv` varchar(64) NOT NULL DEFAULT'' COMMENT 'Vendor 标识符',
  `device_brand` varchar(32) NOT NULL DEFAULT'' COMMENT '设备品牌',
  `device_model` varchar(32) NOT NULL DEFAULT'' COMMENT '设备模式',
  `ctime` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `utime` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8  COMMENT='用户设备信息表';






##2020-04-20
CREATE TABLE `hl_news_attention` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '自增id',
  `user_id` INT(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT '用户id',
  `nid` INT(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT '资讯id',
  `collect` TINYINT(1) NOT NULL DEFAULT '0' COMMENT '收藏状态 1收藏 0未',
  `ctime` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `utime` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `in_user_id` (`user_id`)
) ENGINE=INNODB DEFAULT CHARSET=utf8 COMMENT='资讯收藏记录表';

#配置表

CREATE TABLE `hl_config` (
  `id` int(11)  NOT NULL AUTO_INCREMENT,
  `desc` varchar(64) NOT NULL DEFAULT '' COMMENT '配置描述',
  `key` tinyint(1) NOT NULL DEFAULT '1' COMMENT '配置字段',
  `value` tinyint(1) NOT NULL DEFAULT '1' COMMENT '配置值',
  `ctime` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `utime` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8  COMMENT='网站配置表';


#2020-04-23
ALTER TABLE `hl_comment_reply` add `org_content` text  COMMENT '原始内容';
ALTER TABLE `hl_comment` add `org_content` text  COMMENT '原始内容';

#2020-04-27
alter table hl_channel add apple_nickname varchar(64) default '' comment '苹果授权昵称';
alter table hl_channel add apple_id varchar(64) default '' comment '苹果授权ID';

#2020-05-06
alter table hl_comment add author_name varchar(64) default '' comment '作者名字';
alter table hl_comment_reply add author_name varchar(64) default '' comment '作者名字';


#2020-05-07
ALTER TABLE `hl_news_video` ADD  `comment` TINYINT(1) NOT NULL DEFAULT '1' COMMENT '0关闭评论  1开启评论'''''
/* 6:35:51 PM haoliao haoliao */ ALTER TABLE `hl_news_video` ADD `seconds` INT  NOT NULL  DEFAULT '0'  COMMENT '时长'  AFTER `comment`;
/* 6:36:14 PM haoliao haoliao */ ALTER TABLE `hl_news_video` ADD `width` INT  NOT NULL  DEFAULT '0'  COMMENT '宽度'  AFTER `seconds`;
/* 6:36:32 PM haoliao haoliao */ ALTER TABLE `hl_news_video` ADD `height` INT  NOT NULL  DEFAULT '0'  COMMENT '高度'  AFTER `width`;
/* 6:36:44 PM haoliao haoliao */ ALTER TABLE `hl_news_video` ADD `size` INT  NOT NULL  DEFAULT '0'  COMMENT '大小'  AFTER `height`;

#2020-05-07
 ALTER TABLE `hl_device_info` ADD `device_number` varchar(64)  NOT NULL  DEFAULT ''  COMMENT 'ios 设备号 可最大可能保持唯一';


#2020-05-12

 ALTER TABLE `hl_user` ADD `forbidden_day` tinyint(1)  NOT NULL  DEFAULT 0  COMMENT '禁言天数';
 ALTER TABLE `hl_user` ADD `forbidden_time` int(10)  NOT NULL  DEFAULT 0  COMMENT '禁言开始时间';

 #2020-05-13
 ALTER TABLE `hl_comment_reply` ADD `topic_title` varchar (150)  NOT NULL  DEFAULT ''  COMMENT '主题标题：文章标题/视频标题';

 #2020-06-02
#方案修改记录表
CREATE TABLE `hl_resource_record` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `resource_id` int(11) NOT NULL DEFAULT '0' COMMENT '料ID',
  `content` text  COMMENT '方案内容',
  `league_id` int(11) NOT NULL DEFAULT '0' COMMENT '联赛ID',
  `schedule_id` int(11) NOT NULL DEFAULT '0' COMMENT '赛程ID',
  `type` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1足球  2篮球',
  `lottery_type` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1：竞彩 2：北单 3：外围  0：所有',
  `lottery_id` int(10) NOT NULL DEFAULT '0',
  `h` varchar(16) NOT NULL DEFAULT '' COMMENT '让球',
  `w` varchar(16) NOT NULL DEFAULT '' COMMENT '主胜',
  `d` varchar(16) NOT NULL DEFAULT '' COMMENT '平局， 篮球时此字段代表玩法 1：让分 2：让分胜负 3：大小分',
  `l` varchar(16) NOT NULL DEFAULT '' COMMENT '客胜',
  `recommend` varchar(16) NOT NULL DEFAULT '' COMMENT '专家推荐：主推，非主推',
  `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0:初始值，1已修改',
  `utime` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `resource_id` (`resource_id`) USING BTREE,
  KEY `league_id` (`league_id`) USING BTREE,
  KEY `schedule_id` (`schedule_id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COMMENT='方案赛事修改记录'











 #2020-06-02
 ALTER TABLE `check_config` ADD `bindmobile` tinyint(1)  NOT NULL  DEFAULT 1  COMMENT '登陆绑定手机号0:跳过，1绑定';
 ALTER TABLE `check_config` ADD `show_comment_model` tinyint(1)  NOT NULL  DEFAULT 1  COMMENT '评论模块：0关闭，1展示';

#2020-06-03 方案情报
 ALTER TABLE `hl_match_information` ADD `team_num` int(11)  NOT NULL  default 0 COMMENT '队伍编号';
 ALTER TABLE `hl_match_information` ADD `type` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0：有利，1：不利，2：中立';

#2020-06-11
#方案浏览记录表
CREATE TABLE `hl_resource_view_record` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `resource_id` int(11) NOT NULL DEFAULT '0' COMMENT '料ID',
  `user_id` int(11) NOT NULL DEFAULT '0' COMMENT '用户ID',
  `device` char(64) NOT NULL DEFAULT '' COMMENT '设备号',
  `ctime` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `resource_id` (`resource_id`) USING BTREE,
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COMMENT='方案浏览记录'


#足球比赛自动推测数据
CREATE TABLE `hl_match_recommend` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `match_num` int(11) NOT NULL DEFAULT '0' COMMENT '比赛编号',
  `league_num` int(11) NOT NULL DEFAULT '0' COMMENT '联赛编号',
  `comp_num` varchar(128) NOT NULL DEFAULT '' COMMENT '盘口公司编号',
  `comp_name` varchar(128) NOT NULL DEFAULT '' COMMENT '盘口公司名称',
  `init_pankou` char(10) NOT NULL DEFAULT '' COMMENT '初始盘口',
  `now_pankou` char(10) NOT NULL DEFAULT '' COMMENT '即时盘口',
  `recommend_team` int(11) NOT NULL DEFAULT 0 COMMENT '推荐队伍编号',
  `recommend_team_name` varchar(64) NOT NULL DEFAULT '' COMMENT '推荐队伍名称',
  `forecast` tinyint(1) NOT NULL DEFAULT 0 COMMENT '预测结果：1主胜，2：平，3客胜',
  `actual` varchar(64) NOT NULL DEFAULT '' COMMENT '实际结果',
  `confidence` varchar(32) NOT NULL DEFAULT '' COMMENT '推荐信心',
  `utime` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `match_num` (`match_num`) USING BTREE,
  KEY `league_num` (`league_num`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COMMENT='比赛预测分析表'