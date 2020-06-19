# 小程序接口文档

>主域：https://dyjconsole.7k.cn

>所有图片前面加"https://dyjpic.7k.cn/static/image"

>登录返回token值以及uid值，在调用其他接口时传入token值与uid值。文件上传接口除外。

>在管理小程序数据时，调用接口以gameId为字段名传入小程序列表接口中的小程序id。

### 小程序列表接口
>URL:api.php?c=config&do=getProgramList&p=admin     
方式：GET  
参数：         
无   

返回值
```json
{
	"code": 1,
	"data": [{
		"id": 1,
		"name": "7k猜成语",
		"code": "phrase"
	}, {
		"id": 2,
		"name": "7k猜漫画",
		"code": "comic"
	}]
}
```

### 1.登录：
>URL：/api.php?c=login&do=index&p=admin  
方式：POST  
参数：  
user    用户名  
pwd    密码  

返回值：
```json
{
        "code": 1,
        "message": "登录成功",
        "data": {
                "uid": 1,
                "realName": "王辉",
                "userName": "wanghui",
                "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpZCI6MSwidXNlck5hbWUiOiJhZG1pbiIsImlhdCI6MTUyNjk3MzExMiwiZXhwIjoxNTI2OTgwMzEyfQ.y_oYJER6ItCNHybu-5x0ZhvVGLqFYPQcflFEj8rII1M"
        }
}
```

ps：返回token用户请求其他接口使用。

### 2.标签列表：
>URL：/api.php?c=tag&do=tagList&p=admin&page=1&size=30&token=eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpZCI6MSwidXNlck5hbWUiOiJhZG1pbiIsImlhdCI6MTUyNjk3Mzk1NywiZXhwIjoxNTI2OTgxMTU3fQ.bHRh8HgTNPOfsWtrPMUpDdpJiKxt9GqEipszGZEdz0E  
方式：GET  
参数：  
status    标签状态（不传为全部标签）  
page    页码  
size    数据长度  

返回值：

```json
{
        "code": 1,
        "data": {
                "list": [{
                        "id": "1",
                        "name": "益智",
                        "status": "0",//（'标签状态:0下线;1线上;）
                        "time": "1526975095",    
                        "file":"1.jpg", 
                        "sort":"1", 
                        "pass_file":"1.jpg"
                }, {
                        "id": "2",
                        "name": "生活",
                        "status": "0",
                        "time": "1526975095", 
                        "file":"1.jpg", 
                        "sort":"1", 
                        "pass_file":"1.jpg"
                }, {
                        "id": "3",
                        "name": "体育",
                        "status": "0",
                        "time": "1526975095", 
                        "file":"1.jpg", 
                        "sort":"1", 
                        "pass_file":"1.jpg"
                }, {
                        "id": "4",
                        "name": "明星",
                        "status": "0",
                        "time": "1526975095", 
                        "file":"1.jpg", 
                        "sort":"1", 
                        "pass_file":"1.jpg"
                }, {
                        "id": "5",
                        "name": "歌曲",
                        "status": "0",
                        "time": "1526975095", 
                        "file":"1.jpg", 
                        "sort":"1", 
                        "pass_file":"1.jpg"
                }],
                "count":5
        }
}
```

### 3.新建标签
>URL：/api.php?c=tag&do=newTag&p=admin&name=体育  
方式 ：POST  
参数：  
name    标签名  
file    标签图片    
sort    排序    
pass_file   通关图片    
返回值：  
```json
{
        "code": 1,
        "msg":"标签创建成功"
}
```
### 4.编辑标签
>URL：/api.php?c=tag&do=editTag&p=admin&name=体育1&id=8&status=2  
方式 ：POST  
参数：  
id    标签id  
name    标签名  
file    标签图片    
sort    排序    
pass_file   通关图片    
返回值：
```json
{        
    "code": 1,         
    "message": "标签更新成功" 
}
```

### 5.标签检查
>URL：/api.php?c=tag&do=tagCheck&p=admin&name=体育  
方式 ：GET  
参数：  
name    标签名
  
返回值：
```json
{
        "code": 1,
        "data": [{
                "id": "3",
                "name": "体育",
                "status": "0",
                "time": "1526975095"
        }]
}

```

### 6.文件上传
>URL:/api.php?c=file&do=fileUpload&p=admin  
方式：POST  
参数：  
picture    图片文件
  
返回值：
```json
{
        "code": 1,
        "message": "文件上传成功",
        "data": {
                "name": "15269805895b03dfed59e488.00100378.jpg"
        }
}
```

### 7.临时题目列表
>URL：/api.php?c=question&do=getTempQuestionList&p=admin&page=1&size=30  
方式 ：GET  
参数：  
page    页码  
size    条目数量  

返回值：
```json
{
        "code": 1,
        "data": {
                "list": [{
                        "id": "1",
                        "name": "鱼贯而入",
                        "cover": "ce0730c5358b428e0a46205af9ffedde.png",
                        "pinyin": "yú guàn ér rù",
                        "jinyici": "井然有序 有条不紊",
                        "fanyici": "一拥而入 破门而入",
                        "yongfa": "偏正式；作谓语、定语；含褒义",
                        "jieshi": "象游鱼一样一个跟着一个地接连着走。形容一个接一个地依次序进入。",
                        "chuchu": "《三国志·魏志·邓艾传》：“将士皆攀木缘崖，鱼贯而进。”",
                        "shili": "众才女除卞、孟两家姊妹在后，其余都是按名鱼贯而入。（清·李汝珍《镜花缘》第六十七回）"
                }, {
                        "id": "2",
                        "name": "四通八达",
                        "cover": "28a631a50116f35ba24479d847e92a44.png",
                        "pinyin": "sì tōng bā dá",
                        "jinyici": " ",
                        "fanyici": " ",
                        "yongfa": "滑台～，非帝王之所居。 ◎《晋书·慕容德载记》",
                        "jieshi": "四面八方都有路可通。形容交通极便利。也形容通向各方。",
                        "chuchu": "《子华子·晏子问党》：“其途之所出，四通而八达，游士之所凑也。”",
                        "shili": "滑台四通八达，非帝王之所居。（《晋书·慕容德载记》）"
                }],
                "count": 500
        }
}

```
### 8.新建题目
>URL：/api.php?c=question&do=newQuestion&p=admin&name=一马当先&picture=&desc=&answer_list=&answer=  
方式 ：GET  
参数：  
name    成语汉字  
picture    通过文件上传接口获取的文件名  （json格式） 
desc    描述  
answer_list    备选字库索引（json格式）  
answer    答案索引（json格式）  
tag    标签列表（json格式） 
made_desc   定制描述    
made_status   朋友圈图片  0 不使用  1使用     
default_picture       默认字段  （json格式） 
source      通过文件上传接口获取的文件名（后续版本 source字段作为主资源字段  替代picture字段）    （json格式）        
返回值：
```json
{
        "code": 1,
        "message": "题目创建成功"
}
```

### 9.获取答案字库或获取特定汉字字库编码
>URL：/api.php?c=question&do=getAnswerList&p=admin&str=%E4%BD%A0%E5%A5%BD&supply=0  
方式 ：GET  
参数：  
str    必须包含的汉字  
supply    是否补齐21个汉字(0不补齐，1补齐)  

返回值：
```json

{
        "code": 1,
        "data": [{
                "id": "666",
                "string": "你"
        }, {
                "id": "513",
                "string": "好"
        }, {
                "id": 2772,
                "string": "咧"
        }, {
                "id": 2283,
                "string": "暮"
        }, {
                "id": 1696,
                "string": "预"
        }, {
                "id": 650,
                "string": "利"
        }, {
                "id": 2309,
                "string": "稳"
        }, {
                "id": 1033,
                "string": "波"
        }, {
                "id": 2616,
                "string": "兑"
        }, {
                "id": 1908,
                "string": "绵"
        }, {
                "id": 135,
                "string": "分"
        }, {
                "id": 1808,
                "string": "得"
        }, {
                "id": 2010,
                "string": "黑"
        }, {
                "id": 1179,
                "string": "殃"
        }, {
                "id": 1469,
                "string": "桥"
        }, {
                "id": 2829,
                "string": "娜"
        }, {
                "id": 1960,
                "string": "棵"
        }, {
                "id": 1316,
                "string": "施"
        }, {
                "id": 1220,
                "string": "咬"
        }, {
                "id": 1495,
                "string": "较"
        }, {
                "id": 2818,
                "string": "恤"
        }]
}
```
### 10.编辑题目
>URL：/api.php?c=question&do=editQuestion&p=admin&name=二马当先&picture=&desc=&answer_list=&answer=&id=3  
方式 ：POST  
参数：  
name    成语汉字  
picture    通过文件上传接口获取的文件名  （json格式） 
desc    描述  
answer_list    备选字库索引（json格式）  
answer    答案索引（json格式）  
status    状态 （0线下，1线上）传1时，所有字段必须都有值，且name为汉字，字数不限制  
id    题目id（必填）  
tag    标签列表（json格式）     
made_desc   定制描述        
made_status   朋友圈图片  0 不使用  1使用     
default_picture       默认字段  （json格式） 
source      通过文件上传接口获取的文件名（后续版本 source字段作为主资源字段  替代picture字段）   （json格式） 
返回值：
```json
{
        "code": 1,
        "message": "题目修改成功"
}
```
### 11.正式题目列表

>URL：/api.php?c=question&do=getQuestionList&p=admin&page=1&size=30  
方式 ：GET  
参数：  
page    页码  
size    条目数量  
status    不传为全部，0默认，1线上2线下  
tid        集合标签ID
返回值：
```json
{
        "code": 1,
        "data": {
                "list": [{
                        "id": "1",
                        "name": "一心一意",//中文名称
                        "picture": "wangzherongyao113.jpg",//图片名称
                        "desc": "一心一意",//描述
                        "answer_list": null,//备选字列表
                        "answer": null,//答案
                        "status": "0",//状态 0默认，1线上2线下  
                        "create_time": null,//创建时间
                        "made_desc":"一心一意",//定制描述
                        "made_status":"0",//朋友圈图片状态   0  不使用  1使用
                        "default_picture" :"1.jpg",//默认图片 
                        "source" :"1.mp4",//图片名称  后续代替picture字段   
                }, {
                        "id": "2",
                        "name": "三心二意",
                        "picture": "wangzherongyao113.jpg",
                        "desc": null,
                        "answer_list": null,
                        "answer": null,
                        "status": "0",
                        "create_time": null,
                        "made_desc":"一心一意",
                        "made_status":"0",
                        "default_picture" :"1.jpg",//默认图片 
                        "source" :"1.jpg",//图片名称  后续代替picture字段  
                }],
                "count": 2
        }
}
```


### 12.搜索问题
>URL：/api.php?c=question&do=searchQuestion&p=admin&name=一  
方式 ：GET  
参数：  
name    关键字  

返回值：
```json
{
        "code": 1,
        "data": [{
                "id": "1",
                "name": "一心一意",
                "picture": "wangzherongyao113.jpg",
                "desc": "一心一意",
                "answer_list": null,
                "answer": null,
                "status": "0",
                "create_time": null,
                "made_desc":"一心一意",
                 "made_status":"0"
        }, {
                "id": "4",
                "name": "一马当先",
                "picture": "",
                "desc": "",
                "answer_list": "",
                "answer": "",
                "status": "0",
                "create_time": "1527236153",
                "made_desc":"一心一意",
                "made_status":"0"
        }]
}

```
### 13.分享列表
>URL:/api.php?c=share&do=getShareList&p=admin
方式：GET 
参数：  
    page    页码  
    size    条目数量  
    
返回值
```json
{
	"code": 1,
	"data": {
                "list": [{
                        "id":"1",
                        "describe":"111111111111",
                        "file":"1.jpg",
                        "name":"普通分享"
                }, {
                        "id":"2",
                        "describe":"22222222",
                        "file":"2.jpg",
                        "name":"求助分享"
                }, {
                        "id":"3",
                        "describe":"333",
                        "file":"3.jpg",
                        "name":"炫耀分享"
                }, {
                         "id":"4",
                        "describe":"44444",
                        "file":"4.jpg",
                        "name":"群排名分享"
                }],
                "count":4
        }
}
```

### 14.添加分享内容
>URL:/api.php?c=share&do=addShare&p=admin  
方式：GET   
参数：    
    file    图片名   
    name    分享类型  
    describe    描述内容  
    
返回值
```json
{
        "code": 1,
        "message": "分享添加成功"
}
```

### 15.编辑分享内容
>URL:/api.php?c=share&do=editShare&p=admin  
方式：GET   
参数：    
    id      分享ID(必填)  
    file    图片名   
    name    分享类型  
    describe    描述内容  
    
返回值
```json
{
        "code": 1,
        "message": "分享编辑成功"
}

```
### 16.添加锁定标签
>URL:/api.php?c=tag&do=addLockTag&p=admin  
方式：GET   
参数：    
    status      状态：1.上线 2.下线   
    start_tag_id     初始标签ID   
    progress_tag_id    进阶标签ID  
    num    进阶数量    
    prompt     进阶提示            

返回值
```json
{
        "code": 1,
        "message": "添加锁定标签成功"
}

```
### 17.编辑锁定标签
>URL:/api.php?c=tag&do=editLockTag&p=admin  
方式：GET   
参数：   
    id                  锁定ID(必填)    
    status      状态：1.上线 2.下线     
    start_tag_id     初始标签ID   
    progress_tag_id    进阶标签ID  
    num    进阶数量 
    prompt     进阶提示 
    
返回值
```json
{
        "code": 1,
        "message": "编辑锁定标签成功"
}

```
### 18.锁定标签列表
>URL:/api.php?c=tag&do=lockTagList&p=admin  
方式：GET   
参数：   
    status              状态：1.上线 2.下线     
    page                页码  
    size                条目数量
    
返回值
```json
{
	"code": 1,
	"data": {
                "list": [{
                        "id":"1",
                        "num":"3",
                        "start_tag_id":"10",
                        "start_tag_name":"初级难度",
                        "progress_tag_id":"9",
                        "progress_tag_name":"中级难度",
                        "status":"1",
                        "prompt":"哇!太厉害了！初级题目已经难不倒你了！去中级难度挑战一下吧！"
                }, {
                        "id":"2",
                        "num":"13",
                        "start_tag_id":"9",
                        "start_tag_name":"中级难度",
                        "progress_tag_id":"8",
                        "progress_tag_name":"高级难度",
                        "status":"1",
                        "prompt":"哇!太厉害了！初级题目已经难不倒你了！去中级难度挑战一下吧！"
                }],
                "count":2
        }
}

```
### 19.获取红包配置列表
>URL:/api.php?c=red&do=getConfigList&p=admin  
方式：GET   

    
返回值
```json
{
	"code": 1,
	"data": {
                "list": [{
                        "key":"monthAmount",//key输入框name值    
                        "name":"每月提现金额",//name输入框中文名  
                        "value":"30000"//数值  输入框value值   
  
                }, {
                        "key":"minTimes",   
                        "name":"领取次数",  
                        "value":"5" 
                },{
                        "key":"minWithDraw",   
                        "name":"最低提现金额",  
                        "value":"5"
                },{
                        "key":"highMultiple",   
                        "name":"超发倍数",  
                        "value":"1"
                },{
                        "key":"firstMinAmount",   
                        "name":"第一个红包最低金额",  
                        "value":"1"
                },{
                        "key":"secondMinAmount",   
                        "name":"第二个红包最低金额",  
                        "value":"0.8"
                },{
                        "key":"thirdMinAmount",   
                        "name":"第三个红包最低金额",  
                        "value":"0.5"
                },{
                        "key":"openDuration",   
                        "name":"单个红包开启时长",  
                        "value":"3600"
                }],
                "count":2
        }
}

```

### 20.更新红包配置
>URL:/api.php?c=red&do=updateConfig&p=admin  
方式：GET   
参数：      
    key     key输入框name值 
    value   数值 输入框value值    
    
返回值
```json
{
    "code": 1,  
    "message": "红包配置更新成功"
}

```

### 21.一键推送
>URL:/api.php?c=tag&do=updateStatusByIds&p=admin  
方式：GET   

    
返回值
```json
{
    "code": 1,  
    "message": "推送成功"
}

```






### 23.获取金币排行榜配置
>URL: /api.php?c=rank&do=getRankData&p=admin
方式： GET
参数： 无
返回值
```json
{
  "code" : 1,
  "data" : [
    {
      "id" : 1,
      "rank" : 1,
      "nickname" : "测试测试",
      "avatarurl" : "https://dyjpic.7k.cn/static/image/b9/15301537885b344b3c6f2296.80468372.jpg",
      "gold" : 10000,
      "type" : 0
    }
  ]
}
```

### 24.更新保存金币排行榜配置
>URL: /api.php?c=rank&do=updateRank&p=admin
方式： POST
参数： id、rank、name、img、 count、 type
返回值
```json
{
  "code" : 1,
  "message" : "排行榜配置更新成功"
}
``` 

### 25.获取系统配置列表
>URL: /api.php?c=system&do=getConfigList&p=admin    
方式： GET  
参数： 无   
返回值  
```json 
{
	"code": 1,
	"data": {
                "list": [{
                        "key":"commonShareCount",//key输入框name值    
                        "name":"每日普通分享获得金币的次数",//name输入框中文名  
                        "value":"10"//数值  输入框value值   
                }, {
                        "key":"commonShareGold",   
                        "name":"每日普通分享获得的金币数",  
                        "value":"10" 
                },{
                        "key":"skipQuestionGold",   
                        "name":"跳过问题消耗金币数",  
                        "value":"5"
                },{
                        "key":"getAnswerGold",   
                        "name":"获得答案消耗金币数",  
                        "value":"5"
                }],
                "count":4
        }
}
```

        
### 26.更新系统配置     
>URL: /api.php?c=system&do=updateConfig&p=admin    
方式： GET  
参数：      
    key     key输入框name值     
    value   数值 输入框value值      
    
返回值  
```json     
{
    "code": 1,  
    "message": "红包配置更新成功"
}
```

### 27.问题搜索集合标签
>URL: /api.php?c=tag&do=questionTagList&p=admin    
方式： GET  
参数：      
    无   
    
返回值  
```json     
{
    "code": 1,  
    "data": {
             "list": [{
                "id":"1",
                 "name":"猜成语",
             }，{
                "id":"2",
                 "name":"日常用品",
             }]
}
```
### 28.微信文件上传   
>URL:/api.php?c=file&do=fileUploadWeChat&p=admin  
方式：POST     
参数：     
picture    图片文件 文件类型只能是JPG/PNG      
  
        
返回值  
```json     
{
    "code": 1,
            "message": "文件上传成功",
            "data": {
                    "name": "15269805895b03dfed59e488.00100378.jpg",
                    "media_id": "kafMsd2X2xGjXZjRES9Cd5TRhphj5KLmBTHaGenHJ8vI5UudKzLfu2No5urltFNL"
            }
}
```


### 29.添加客服消息       
>URL: /api.php?c=cs&do=add&p=admin    
方式： GET  
参数：  
    gameId          //小程序类型    
    key_word        //关键字   
    status          //状态  0下线 1上线   
    msg_type        //回复方式  1(文本) 2（图片）3（图文）4 (卡片)  
根据回复方式需要的参数如下：  
           1：{  
                content     //文本内容  
           }    
           2：{  
                picurl     //图片地址   
                media_id    //图片的media_id(通过 28.微信文件上传 接口获取到的)  (页面不展示)
           }    
           3：{  
                 description    //图文链接消息    
                 thumb_url         //图文链接消息的图片链接   
                 title          //消息标题  
                 url            //图文链接消息被点击后跳转的链接   
           }    
           4:{  
                title          //消息标题   
                pagepath        //小程序的页面路径    
                media_id        //图片的media_id(通过 28.微信文件上传 接口获取到的)  (页面不展示)
                appId           //APPID    
                picurl     //图片地址  
           }        
    
返回值     
```json     
{
    "code": 1,  
    "message": "添加成功"
}
```


### 30.修改客服消息    
>URL: /api.php?c=cs&do=edit&p=admin    
方式： GET  
参数：  
    id              //列表ID(必填) 
    gameId          //小程序类型    
    key_word        //关键字   
    status          //状态  0下线 1上线   
    msg_type        //回复方式  1(文本) 2（图片）3（图文）4 (卡片)  
根据回复方式需要的参数如下：  
           1：{  
                content     //文本内容  
           }    
           2：{  
                picurl     //图片地址   
                media_id    //图片的media_id(通过 28.微信文件上传 接口获取到的)  (页面不展示)
           }    
           3：{  
                 description    //图文链接消息    
                 thumb_url         //图文链接消息的图片链接   
                 title          //消息标题  
                 url            //图文链接消息被点击后跳转的链接 
           }    
           4:{  
                title          //消息标题   
                pagepath        //小程序的页面路径  
                media_id        //图片的media_id(通过 28.微信文件上传 接口获取到的)  (页面不展示)
                appId           //APPID     
                picurl     //图片地址   
           }    
    
返回值     
```json     
{
    "code": 1,  
    "message": "更新成功"
}
```

### 31.客服消息列表       
>URL: /api.php?c=cs&do=lists&p=admin    
方式： GET  
参数：  
   status       //状态  0下线 1上线   
   page         
   size 
    
返回值  
```json     
{
    "code": 1,  
    "data": {
                 "list": [{
                    "id":"1",
                     "key_word":"默认",//关键字
                     "msg_type":1,//回复方式    1(文本) 2（图片）3（图文）4 (卡片)
                     "status":1, //状态  0下线 1上线   
                     "content":"{
                            "content":"helll World"//文本内容
                            }"//内容
                 }，{
                    "id":"2",
                    "key_word":"默认",//关键字
                    "msg_type":2,//回复方式   1(文本) 2（图片）3（图文）4 (卡片)
                    "status":1, //状态  0下线 1上线   
                    "picurl":"1.jpg"//图片地址
                    "media_id":1232//图片的media_id
                            
                 }，{
                    "id":"3",
                    "key_word":"默认",//关键字
                    "msg_type":3,//回复方式  / 1(文本) 2（图片）3（图文）4 (卡片)
                    "status":1, //状态  0下线 1上线   
                    "description":"图片",//图文链接消息
                    "thumb_url":1232,//图文链接消息的图片链接
                    "title":图文，//消息标题
                    "url":"https://dyjapitest.7k.cn/api.php?c=t&do=lists&p=api"//图文链接消息被点击后跳转的链接
                 }，{
                    "id":"4",
                    "key_word":"默认",//关键字
                    "msg_type":4,//回复方式   1(文本) 2（图片）3（图文）4 (卡片)
                    "status":1, //状态  0下线 1上线   
                    "pagepath":"图片",//小程序的页面路径
                    "title":图文，//消息标题
                    "thumb_media_id":1232312,图片的media_id
                    "appId":"1232",
                    "picurl":"1.jpg"//图片地址
                 }]
                 "count":4
}
```

### 32.红包补单列表  
>URL:/api.php?c=red&do=orderLists&p=admin  
方式：GET    
参数：     
account        登录人账号   (必填)
start_time      开始时间    
end_time        结束时间         
page    
size    
        
返回值  
```json     
{
    "code": 1,
     "data":{
        "count":2,
        "check_status":0,//操作类型  0.补单  1.审核
        "lists":[{
                    "id":"3",//提现ID
                    "uid":"2",//用户ID
                    "order":"2",//订单号
                    "porder":"2",//微信单号
                    "wstatus":"",//微信状态
                    "wreason":null,//微信失败原因
                    "status":"2"//状态  0.未提交补单   1. 审核成功  2.未审核
                },{
                    "id":"4",
                    "uid":"2",
                    "order":"2",
                    "porder":"2",
                    "wstatus":"",
                    "wreason":null,
                    "status":"2"
                }]
         }
}
```


### 33.红包提交补单
>URL:/api.php?c=red&do=replenishOrder&p=admin  
方式：GET    
参数：     
wid         //提现ID  
        
返回值  
```json     
{
    "code": 1,
    "message":"提交成功"
}
```

### 34.红包补单审核
>URL:/api.php?c=red&do=checkOrder&p=admin  
方式：GET    
参数：     
wid         //提现ID  
check_account   //审核人账号
status          审核状态  1、同意 2、拒绝              
   
返回值  
```json     
{
    "code": 1,
    "message":"审核成功"
}
```

### 34.红包补单审核
>URL:/api.php?c=red&do=checkOrder&p=admin  
方式：GET    
参数：     
wid         //提现ID  
check_account   //审核人账号
status          审核状态  1、同意 2、拒绝              
   
返回值  
```json     
{
    "code": 1,
    "message":"审核成功"
}
```
### 35.推广配置添加接口
>URL:/api.php?c=extend&do=add&p=admin  
方式：GET    
参数：     
appId   APPID   
name    小程序名称   
extend_picture  推广图片    
code        二维码图片   
homepage_url    主页跳转链接  
partner         合作方 
extend_position     推广位置    
status          状态 0.下线 1.上线   
type        推广类型  1.主页图 2.广告图      
   
返回值  
```json     
{
    "code": 1,
    "message":"添加成功"
}
```

### 36.推广配置编辑接口
>URL:/api.php?c=extend&do=edit&p=admin  
方式：GET    
参数：     
id      配置ID    
appId   APPID   
name    小程序名称   
extend_picture  推广图片    
code        二维码图片   
homepage_url    主页跳转链接  
partner         合作方 
extend_position     推广位置    
status          状态 0.下线 1.上线   
type        推广类型  1.主页图 2.广告图      
   
返回值  
```json     
{
    "code": 1,
    "message":"编辑成功"
}
```

### 37.推广配置列表接口
>URL:/api.php?c=extend&do=lists&p=admin  
方式：GET    
参数：     
page
size 
   
返回值  
```json     
{
    "code": 1,
    "data":{
        "lists":[{
            "id":"1",
            "appId":"1232",
            "name":"7k7k猜成语", //小程序名称
            "extend_picture":"1.jpg",//推广图
            "code":"二维码.jpg",//二维码图
            "homepage_url":"http://www.baidu.com",//主页跳转链接
            "partner":"百度",//合作方
            "extend_position":"底部",//推广位置
            "status":"0",//状态  0 下线 1 上线  2 编辑中
            "type":"1",//推广类型  1.主页图 2.广告图  
        },{
            "id":"2",
            "appId":"1232",
            "name":"7k7k猜成语", //小程序名称
            "extend_picture":"1.jpg",//推广图
            "code":"二维码.jpg",//二维码图
            "homepage_url":"http://www.baidu.com",//主页跳转链接
            "partner":"百度",//合作方
            "extend_position":"底部",//推广位置
            "status":"0",//状态  0 下线 1 上线  2 编辑中
            "type":"1",//推广类型  1.主页图 2.广告图 
        }]
        "count":2
    }
}
```
### 38.推广配置一键推送接口
>URL:/api.php?c=extend&do=push&p=admin  
方式：GET    
参数：     
无
   
返回值  
```json     
{
    "code": 1,
    "message":"推送成功
}
```

### 39.获取账号下已存在的消息模版列表接口
>URL:/api.php?c=wxtemplate&do=getExistList&p=admin  
方式：GET    
参数：     
gameId   游戏ID

返回值  
```json     
{
    "code": 1,
    "message": "获取成功",
    "data": [
        {
            "template_id": "OtlvnrSy88jKDpkcONAaIGAt-nWXS-DUxiegaVaJgig",   //模版ID
            "title": "已预约活动开始提醒",                                     //模版标题
            "content": "活动名称{{keyword1.DATA}}\n活动入口{{keyword2.DATA}}\n活动描述{{keyword3.DATA}}\n",     //模版内容
            "example": "活动名称：一起去看流星雨\n活动入口：活动入口\n活动描述：XXX活动是XXX\n"                      //模版示例
        }
    ]
}
```

### 40.获取用户服务消息任务列表接口
>URL:/api.php?c=noticetask&do=getNoticeTask&p=admin  
方式：GET    
参数：     
page    页码
size    每页条数
返回值  
```json     
{
    "code": 1,
    "message": "获取成功",
    "data": [
        {
            "id": "1",                  标识ID
            "activity_title": "33",     活动名称
            "gameId": "0",              游戏ID
            "templateId": "1111",       模版ID
            "jump_url": "qqqq",         跳转URL
            "data": "211111",           发送模版数据
            "send_user_num": "0",       发送人数
            "activity_time": "1532304480",
            "status": "0",
            "send_time": "1532304480",
            "time": "1532317161",
            "game": "7k猜成语"
        }
    ]
}
```


### 41.添加用户服务消息推送接口
>URL:/api.php?c=noticetask&do=setNoticeTask&p=admin  
方式：POST    
参数：     
activity_title  活动名称
templateId      模版ID
jump_url        跳转URL
data            发送内容
```
{
      "keyword1": {
          "value": "339208499"
      },
      "keyword2": {
          "value": "2015年01月05日 12:30"
      },
      "keyword3": {
          "value": "粤海喜来登酒店"
      } ,
      "keyword4": {
          "value": "广州市天河区天河路208号"
      }
}
```
position        埋点位置标识ID
send_type       发送方式：1:定时发送 2:持续发送
activity_time   活动时间
send_time       发送时间
   
返回值  
```json     
{
    "code": 1,
    "message":"消息推送创建成功，消息推送执行中……"
}
```

### 41.获取埋点配置列表
>URL:/api.php?c=noticetask&do=getNoticePosition&p=admin  
方式：POST    
参数：     
token
   
返回值  
```json    
{
    "code": 1,
    "message": "获取成功",
    "data": [
        {
            "id": 1,                //埋点位置标识
            "name": "签到位置",      //埋点名称
            "desc": "定时发送",
            "sendType": 1
        },
        {
            "id": 2,
            "name": "领取红包",
            "desc": "持续发送",
            "sendType": 2
        }
    ]
}
```

### 42.红包活动列表
>URL:/api.php?c=ac&do=getActivityList&p=admin  
方式：POST   
参数：     
type    类型      //活动类型  2.红包活动 （其余类型待定） 
  
返回值  
```json     
{
    "code": 1,
    "data":{
        "lists":[{
            "id":"1",//活动ID
            "title":"活动测试",//活动标题
            "startTime":"2018-07-01",//开始时间
            "endTime":"2018-07-10",//结束时间
            "desc":"活动测试",//活动描述
            "status":2,//状态 0 下线 1上线 2 已结束
            "type":2,//活动类型  2红包活动
            }],
        "count":20
        }
}
```

### 43.添加红包活动
>URL:/api.php?c=ac&do=addActivity&p=admin  
方式：POST   
参数：     
type    类型      //活动类型  2.红包活动 （其余类型待定） 
title   活动标题    
s_time  活动开始时间  
e_time  活动结束时间  
describe    活动描述    
status      活动类型  0未上线  1上线中  2已结束  
返回值  
```json     
{
    "code": 1,
    "message":"活动添加成功"
}
```

### 44.编辑红包活动
>URL:/api.php?c=ac&do=updateActivity&p=admin  
方式：POST   
参数：    
id      活动ID    
type    类型      //活动类型  2.红包活动 （其余类型待定） 
title   活动标题    
s_time  活动开始时间  
e_time  活动结束时间  
describe    活动描述    
status      活动类型  0未上线  1上线中  2已结束  
返回值  
```json     
{
    "code": 1,
    "message":"活动修改成功"
}
```

### 45.获取小程序活动类型配置
>URL:/api.php?c=ac&do=getActivityLists&p=admin  
方式：POST   
参数：    
GameId      小程序类型   

返回值  
```json     
{
    "code": 1,
    "data":[{
               "id":"1",//活动ID
               "type":"2",//活动类型
               "name":"红包活动"，//活动名称
           }]
         }
}
```

### 46.获取线上合辑列表
>URL:/api.php?c=tag&do=getTagOnlineList&p=admin  
方式：GET   
参数：    
通用参数即可

返回值  
```json     
{
    "code": 1,
    "data": [
        {
            "id": "10",
            "name": "初级难度",
            "file": "ce/ceb45e940d7c2990c2ed723b7c2486b3.png",
            "sort": "1",
            "status": "1",
            "time": "1527236364",
            "gameId": "1",
            "pass_file": "b1/b1b69f38ca678b1094cd400d2c9edabb.png",
            "mtime": "1530184642"
        }
    ]
}
```

## 小程序前端接口

>主域：https://dyjapitest.7k.cn

>所有图片前面加"https://dyjpic.7k.cn/static/image"  
登录返回token值，在调用其他接口时传入token值  

>小程序启动时调用小程序配置获取接口，获取小程序id，调用接口时以gameId为字段名传入



### 1.小程序配置文件获取

>URL：/api.php?c=system&do=getConfig&p=api 
方式：GET  
参数：  
package 小程序包名  
v   版本号 

返回值
```json
{
	"code": 1,
	"data": {
		"id": 1,
		"name": "7k猜成语"
	}
}
```
### 2.登录

>URL：/api.php?c=login&do=login&p=api 
方式：GET  
参数：  
code     临时登录凭证code  
encryptedData      加密数据   
iv     加密算法的初始向量  
userInfo       用户信息对象 
invite       邀请人UID 

返回值
```json
{
	"code": 1,
	"data": {
		"uid": 1,
		"avatar": "http://...",//头像URL
		"firstLogin": "0",//首次登陆 0，否；1是
		"gender": "1",//1男2女0未知
		"nickname": "7k猜成语",
		"token": "7k猜成语",
		"gold": 100,//"金币数",
		"minWithdraw": 30,//"最低体现金额",
		"red": 0,//"红包数",
		"shareTimes": 10,//"分享次数为0时不弹出金币提示",
		"shareCount": 10 //"分享可得金币数",
	}
}
```
### 3.栏目列表

>URL:/api.php?c=tag&do=tagList&p=api  
方式：GET  
参数： 
uid     用户id  

返回值
```json
{
	"code": 1,
	"data": [{
		"id": "10",
		"file": "15290304635b23273f0e2513.56773480.png",//图片路径
		"sort": "1",//排序
		"name": "普通难度",//合集名称
		"speed": 1,
		"over":true,//合集答题结束
		"lock": false,//false 合集为解锁  true  合集解锁
		"count":"108",//总关数
        "shareFile":"2.jpg" //分享的图片
	}, {
		"id": "9",
		"file": "15290304285b23271c9bce36.90654821.png",
		"sort": "2",
		"name": "中级难度",
		"speed": 1,
		"over":true,
		"lock": true,
		"count":"108",//总关数
        "shareFile":"2.jpg" //分享的图片
	}, {
		"id": "8",
		"file": "15290304135b23270d1193f0.25112222.png",
		"sort": "3",
		"name": "困难难度",
		"speed": 1,
		"over":true,
		"lock": true,
		"count":"108",//总关数
         "shareFile":"2.jpg" //分享的图片
	}]
}

```

### 4.问题列表
>URL：/api.php?c=question&do=getQuestion&p=api
方式：GET  
参数：  
uid     用户id  
tid     栏目id  
size    记录数

返回值

```json
{
	"code": 1,
	"data": [{
		"id": "1",
		"answerCount": "4",
		"picture": "[\"15280876145b14c43e152233.49252277.png\"]",
		"default_picture": "[\"1.png\"]",//默认图片
		"source": "[\"15280876145b14c43e152233.49252277.mp4\"]",//视频字段  后续逐渐代替picture字段
		"answerList": "[{\"id\":\"981\",\"string\":\"鱼\"},{\"id\":\"1101\",\"string\":\"贯\"},{\"id\":\"350\",\"string\":\"而\"},{\"id\":\"10\",\"string\":\"入\"},{\"id\":\"84\",\"string\":\"支\"},{\"id\":\"224\",\"string\":\"灭\"},{\"id\":\"284\",\"string\":\"鸟\"},{\"id\":\"441\",\"string\":\"肌\"},{\"id\":\"418\",\"string\":\"份\"},{\"id\":\"394\",\"string\":\"肉\"},{\"id\":\"617\",\"string\":\"时\"},{\"id\":\"547\",\"string\":\"扯\"},{\"id\":\"74\",\"string\":\"夫\"},{\"id\":\"436\",\"string\":\"企\"},{\"id\":\"396\",\"string\":\"年\"},{\"id\":\"652\",\"string\":\"秀\"},{\"id\":\"201\",\"string\":\"讨\"},{\"id\":\"255\",\"string\":\"生\"},{\"id\":\"264\",\"string\":\"仪\"},{\"id\":\"198\",\"string\":\"宁\"},{\"id\":\"691\",\"string\":\"犹\"}]"
	}, {
		"id": "2",
		"answerCount": "4",
		"picture": "[\"28a631a50116f35ba24479d847e92a44.png\"]",
		"default_picture": "[\"1.png\"]",//默认图片
        "source": "[\"15280876145b14c43e152233.49252277.mp4\"]",//视频字段  后续逐渐代替picture字段
		"answerList": "[{\"id\":\"254\",\"string\":\"四\"},{\"id\":\"1693\",\"string\":\"通\"},{\"id\":\"11\",\"string\":\"八\"},{\"id\":\"356\",\"string\":\"达\"},{\"id\":\"213\",\"string\":\"民\"},{\"id\":\"442\",\"string\":\"朵\"},{\"id\":\"710\",\"string\":\"冷\"},{\"id\":\"377\",\"string\":\"吓\"},{\"id\":\"654\",\"string\":\"每\"},{\"id\":\"167\",\"string\":\"办\"},{\"id\":\"138\",\"string\":\"仓\"},{\"id\":\"227\",\"string\":\"卡\"},{\"id\":\"117\",\"string\":\"长\"},{\"id\":\"186\",\"string\":\"功\"},{\"id\":\"402\",\"string\":\"迁\"},{\"id\":\"569\",\"string\":\"壳\"},{\"id\":\"430\",\"string\":\"舟\"},{\"id\":\"598\",\"string\":\"求\"},{\"id\":\"408\",\"string\":\"休\"},{\"id\":\"237\",\"string\":\"叶\"},{\"id\":\"506\",\"string\":\"收\"}]"
	}]
}
```

### 5.答案检查
>URL:/api.php?c=question&do=checkAnswer&p=api 
方式：POST  
参数：  
uid     用户id  
qid     问题id  
tid     栏目id  
answer  用户选择答案json格式数组，键值为int类型

返回值
```json
{
	"code": 1,
	"data": {
		"name": "kkkkkkkk",
		"desc": "kkkkkkkk",
        "repeat":false,//false第一次答题  true 重复答题     
        "gold":5,//答题所得金币 
        "total_gold":100, //金币总数 
        "lock":false,//false 合集为解锁  ture 合集解锁提示
        "lock_prompt":"哇!太厉害了！初级题目已经难不倒你了！去中级难度挑战一下吧！",//进阶提示
        "made_desc":"1111",//定制描述
        "over":false, //false 合集答题没有结束  ture  合集答题结束
        "red":false //false  没有红包  true  有红包
	},
        "msg":"成功"
}
```

### 6.分享列表
>URL:/api.php?c=system&do=getShareList&p=api   
方式：GET 
参数：  
    page    页码  
    size    条目数量  
    
返回值
```json
{
	"code": 1,
	"data": {
                "list": [{
                        "id":"1",
                        "describe":"111111111111",
                        "file":"1.jpg",
                        "name":"普通分享"
                }, {
                        "id":"2",
                        "describe":"22222222",
                        "file":"2.jpg",
                        "name":"求助分享"
                }, {
                        "id":"3",
                        "describe":"333",
                        "file":"3.jpg",
                        "name":"炫耀分享"
                }, {
                         "id":"4",
                        "describe":"44444",
                        "file":"4.jpg",
                        "name":"群排名分享"
                }],
                "count":4
        }
}
```

### 7.分享日志添加
>URL:/api.php?c=share&do=addShareLog&p=api   
方式：GET   
参数：  
    uid         用户ID  
    share_id    分享ID  
    
返回值
```json
{
        "code": 1,
        "message": "分享日志成功"
}
```
### 8.红包基本信息
>URL:/api.php?c=red&do=info&p=api     
方式： GET  
参数：  
uid 用户uid  

返回值
```json
{
	"code": 1,
	"data": {
		"amount": "5.51",
		"num": 0,
		"time": 0,
		"redJumpTag": 10
	}
}
```

### 9.开红包  
>URL：/api.php?c=red&do=open&p=api   
方式：POST     
参数：         
uid 用户uid

返回值
```json
{
	"code": 1,
	"data": {
		"amount": 0.02
	}
}
```
### 10.红包助力  
>URL：/api.php?c=red&do=help&p=api   
方式：POST     
参数：     
uid 用户uid   
helpUid 被助力uid  

返回值
```json
{
	"code": 1,
	"data": []
}
```

### 11.重复答题
>URL:/api.php?c=question&do=getRepeatQuestion&p=api     
方式：POST  
参数：  
uid     用户id   
tid     栏目id  

返回值
```json
{
	"code": 1,
	"data": [{
		"id": "1",
		"answerCount": "4",
		"picture": "[\"15280876145b14c43e152233.49252277.png\"]",
		"answerList": "[{\"id\":\"981\",\"string\":\"鱼\"},{\"id\":\"1101\",\"string\":\"贯\"},{\"id\":\"350\",\"string\":\"而\"},{\"id\":\"10\",\"string\":\"入\"},{\"id\":\"84\",\"string\":\"支\"},{\"id\":\"224\",\"string\":\"灭\"},{\"id\":\"284\",\"string\":\"鸟\"},{\"id\":\"441\",\"string\":\"肌\"},{\"id\":\"418\",\"string\":\"份\"},{\"id\":\"394\",\"string\":\"肉\"},{\"id\":\"617\",\"string\":\"时\"},{\"id\":\"547\",\"string\":\"扯\"},{\"id\":\"74\",\"string\":\"夫\"},{\"id\":\"436\",\"string\":\"企\"},{\"id\":\"396\",\"string\":\"年\"},{\"id\":\"652\",\"string\":\"秀\"},{\"id\":\"201\",\"string\":\"讨\"},{\"id\":\"255\",\"string\":\"生\"},{\"id\":\"264\",\"string\":\"仪\"},{\"id\":\"198\",\"string\":\"宁\"},{\"id\":\"691\",\"string\":\"犹\"}]"
	}, {
		"id": "2",
		"answerCount": "4",
		"picture": "[\"28a631a50116f35ba24479d847e92a44.png\"]",
		"answerList": "[{\"id\":\"254\",\"string\":\"四\"},{\"id\":\"1693\",\"string\":\"通\"},{\"id\":\"11\",\"string\":\"八\"},{\"id\":\"356\",\"string\":\"达\"},{\"id\":\"213\",\"string\":\"民\"},{\"id\":\"442\",\"string\":\"朵\"},{\"id\":\"710\",\"string\":\"冷\"},{\"id\":\"377\",\"string\":\"吓\"},{\"id\":\"654\",\"string\":\"每\"},{\"id\":\"167\",\"string\":\"办\"},{\"id\":\"138\",\"string\":\"仓\"},{\"id\":\"227\",\"string\":\"卡\"},{\"id\":\"117\",\"string\":\"长\"},{\"id\":\"186\",\"string\":\"功\"},{\"id\":\"402\",\"string\":\"迁\"},{\"id\":\"569\",\"string\":\"壳\"},{\"id\":\"430\",\"string\":\"舟\"},{\"id\":\"598\",\"string\":\"求\"},{\"id\":\"408\",\"string\":\"休\"},{\"id\":\"237\",\"string\":\"叶\"},{\"id\":\"506\",\"string\":\"收\"}]"
	}]
}
```

### 12.单问题获取
>URL:/api.php?c=question&do=getQuestionById&p=api   
方式：POST  
参数：  
uid     用户id   
qid     问题id   
tid     栏目id  

返回值
```json
{
	"code": 1,
	"data": [{
		"id": "1",
		"answerCount": "4",
		"picture": "[\"15280876145b14c43e152233.49252277.png\"]",
		"answerList": "[{\"id\":\"981\",\"string\":\"鱼\"},{\"id\":\"1101\",\"string\":\"贯\"},{\"id\":\"350\",\"string\":\"而\"},{\"id\":\"10\",\"string\":\"入\"},{\"id\":\"84\",\"string\":\"支\"},{\"id\":\"224\",\"string\":\"灭\"},{\"id\":\"284\",\"string\":\"鸟\"},{\"id\":\"441\",\"string\":\"肌\"},{\"id\":\"418\",\"string\":\"份\"},{\"id\":\"394\",\"string\":\"肉\"},{\"id\":\"617\",\"string\":\"时\"},{\"id\":\"547\",\"string\":\"扯\"},{\"id\":\"74\",\"string\":\"夫\"},{\"id\":\"436\",\"string\":\"企\"},{\"id\":\"396\",\"string\":\"年\"},{\"id\":\"652\",\"string\":\"秀\"},{\"id\":\"201\",\"string\":\"讨\"},{\"id\":\"255\",\"string\":\"生\"},{\"id\":\"264\",\"string\":\"仪\"},{\"id\":\"198\",\"string\":\"宁\"},{\"id\":\"691\",\"string\":\"犹\"}]"
	}]
}
```

### 13.问题跳过
>URL:/api.php?c=question&do=skipQuestion&p=api  
方式：POST  
参数：  
qid     问题id   
tid     栏目id  
uid     用户id  

返回值
```json
{
	"code": 1,
	"data":[{
          "over":false,  //查看题目是否结束  false  未结束  true  结束
          "lock":true,//进阶解锁  true 已解锁  false  未解锁
          "lock_prompt":"进阶提示"
	}],
	"message": "成功"
}
```

### 14.邀请列表
>URL:/api.php?c=invite&do=inviteList&p=api  
方式：GET  
参数：  
uid     uid        
返回值
```json
{
	"code": 1,
	"data": [{
                        "id":"1",
                        "nickname":"111111111111",
                        "avatar":"1.jpg"
                }, {
                        "id":"1",
                        "nickname":"111111111111",
                        "avatar":"1.jpg"
                }, {
                        "id":"1",
                        "nickname":"111111111111",
                        "avatar":"1.jpg"
                }, {
                        "id":"1",
                        "nickname":"111111111111",
                        "avatar":"1.jpg"
                }]
}
```

### 15.获得用户基本信息接口
>URL:/api.php?c=user&do=getUserInfo&p=api   
方式：POST  
参数：  
uid     用户id  

返回值
```json
	{"code":1,
            "data":{
                "base":{
                        "nickname":"孤单vs温暖",//昵称
                        "avatarurl":"https://wx.qlogo.cn/mmopen/vi_32/Q0j4TwGTfTK7WRtaqmx28DlLf1xsYCIgQDBKucHZ4EvmDwsOFnSYA36Ku4slf6PEUeaP76IQ3Muek24WZwnG6g/132",  //头像地址
                        "sex":"2" //性别  1.男  2.女
                },
                "gold":{
                        "count":"60"    //金币总数
                },
                "red":{
                        "balance":"2.94",   //红包余额
                        "minWithDraw":"30"  //红包最低提现金额
                },
                "share":{
                        "shareCount":8,     //分享剩余次数
                        "shareGold":"10"    //分享所得金币
                        }
            }
        }
```

### 16.成语提示
>URL:/api.php?c=question&do=hintQuestion&p=api   
方式：POST  
参数：  
uid     用户id  

返回值
```json
{
	"code": 1,
	"message": "成功"
}
```
### 17.活动信息
>URL:/api.php?c=ac&do=getActivityInfo&p=api    
方式：POST  
参数：
    uid     用户ID  
    
返回值  
```json
{
	"code": 1,
         "data":{
            "list":[{
                "id":1,
                "type":1,
                "name":"签到"
            }],
            "info":{ 
                "1":{       
                    "activity":[{
                        "id":"1",
                        "value":"1"
                    },{
                         "id":"2",
                          "value":"2"
                    },{
                        "id":"3",
                        "value":"3"
                    },{
                        "id":4,
                        "value":4
                    },{
                        "id":5,
                        "value":5
                    },{ 
                        "id":6,
                        "value":6
                    },{
                        "id":7,
                        "value":7
                    }],
                    "user":{
                        "day" : "1",
                        "today" : false   
                        }
                    }
            }
        }
}
```

### 18.活动签到
>URL:/api.php?c=ac&do=signActivity&p=api    
方式：POST  
参数：  
    uid     用户ID  
    activity_id     活动ID 
     
返回值  
```json
{
	"code": 1,
        "data":{
                "code":1,
                "count":1
        },
	"message": "签到成功"
}
```

### 19.金币排行榜
>URL:/api.php?c=rank&do=getGoldRank&p=api   
方式： GET     
参数：
uid 用户id
page 页数 默认从1开始
size 数据长度         

返回值
```json
{
  "code" : 1,
  "data" : {
    "list": [
          {
            "id" : 1, 
            "rank" : 1,
            "nickname" : "测试测试",
            "avatarurl": "https://wx.qlogo.cn/mmopen/vi_32/Q0j4TwGTfTK7WRtaqmx28DlLf1xsYCIgQDBKucHZ4EvmDwsOFnSYA36Ku4slf6PEUeaP76IQ3Muek24WZwnG6g/132",
            "gold": 10000
          },
          {
            "uid" : 2,
            "nickname" : "孤单vs温暖",
            "avatarurl": "https://wx.qlogo.cn/mmopen/vi_32/Q0j4TwGTfTK7WRtaqmx28DlLf1xsYCIgQDBKucHZ4EvmDwsOFnSYA36Ku4slf6PEUeaP76IQ3Muek24WZwnG6g/132",
            "gold": 93503
          }
    ],
    "user_rank": 10,    //当前用户排名
    "goldNum": 110      //当前用户金币数
  }
  
}
```

### 20.红包提现
>URL:/api.php?c=pay&do=pay&p=api   
方式： post     
参数：     
uid 用户id

返回值
```json
{
  "code" : 1,
  "data" : {
    "code" : 1000,
    "msg" : "成功",
    "time": "2018-07-03 10:54:46"
  }
}
```

### 21.推广配置主页列表
>URL:/api.php?c=extend&do=homeLists&p=api   
方式： GET     
参数：     
无   

返回值
```json
{
   "code": 1,
    "data":[{
              "appId":"1232",
              "name":"7k7k猜成语", //小程序名称
              "extend_picture":"1.jpg",//推广图
              "code":"二维码.jpg",//二维码图
              "homepage_url":"http://www.baidu.com",//主页跳转链接
              "partner":"百度",//合作方
              "extend_position":"底部",//推广位置
          },{
              "id":"2",
              "appId":"1232",
              "name":"7k7k猜成语", //小程序名称
              "extend_picture":"1.jpg",//推广图
              "code":"二维码.jpg",//二维码图
              "homepage_url":"http://www.baidu.com",//主页跳转链接
              "partner":"百度",//合作方
              "extend_position":"底部",//推广位置
    }]   
}
```


### 22.推广配置广告列表
>URL:/api.php?c=extend&do=adLists&p=api   
方式： GET     
参数：     
无   

返回值
```json
{
   "code": 1,
    "data":[{
              "appId":"1232",
              "name":"7k7k猜成语", //小程序名称
              "extend_picture":"1.jpg",//推广图
              "code":"二维码.jpg",//二维码图
              "homepage_url":"http://www.baidu.com",//主页跳转链接
              "partner":"百度",//合作方
              "extend_position":"底部",//推广位置
          },{
              "id":"2",
              "appId":"1232",
              "name":"7k7k猜成语", //小程序名称
              "extend_picture":"1.jpg",//推广图
              "code":"二维码.jpg",//二维码图
              "homepage_url":"http://www.baidu.com",//主页跳转链接
              "partner":"百度",//合作方
              "extend_position":"底部",//推广位置
    }]   
}
```


### 23.用户服务通知设置
>URL:/api.php?c=notice&do=setUserNotice&p=api   
方式： GET     
参数：     
uid 用户id
formId      微信交互id
gameId      小游戏id
position    埋点位置标识ID

返回值
```json
{
    "code": 1,
    "message": "设置成功"
}
```


### 24.公告日志
>URL:/api.php?c=notices&do=popupLists&p=api   
方式： GET     
参数：     
uid 用户id
GameId 小游戏id

返回值
```json
{
    "code": 1,
    "data":true //true  已展示  false 未展示
}
```


### 25.通关排行榜
>URL:/api.php?c=rank&do=getPassRank&p=api   
方式： GET     
参数：     
gameId 
page    //页数
size    //每页个数

返回值
```json
{
    "code": 1,
    "data": {
        "count": 500,   //总数
        "list": [
            {
                "uid": "9594",      //用户ID
                "nickname": "lxw",  //用户昵称
                "avatarurl": "https://wx.qlogo.cn/mmopen/vi_32/Q0j4TwGTfTKFZqRxpq9Rz71Cv9aHOooGunHGWoGD1ibicg4Wic15qcBCturU7iahKI8yByk1NQVSdM2LO8XQC5JPtg/132",
                "answers_num": 0    //答题数
            }
        ]
    }
}
```

### 26.小程序码获取
>URL:/api.php?c=system&do=getQrCode&p=api
方式： GET     
参数：     
gameId 
scene    //scene参数
path    //路径

返回值
```json
{
    "code": 1,
    "msg": "文件上传成功",
    "data": {
        "name": "88/88e27235cfc6d4d53c7fe7e920ccf73f.png",   //七牛路径
    }
}
```



