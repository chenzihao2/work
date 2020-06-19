<?php
namespace QK\HaoLiao\Common;
use QK\HaoLiao\Model\PushMsgModel;
require_once(dirname(__FILE__) . '/../../../../../library/uPush/src/' . 'notification/android/AndroidBroadcast.php');
require_once(dirname(__FILE__) . '/../../../../../library/uPush/src/' . 'notification/android/AndroidFilecast.php');
require_once(dirname(__FILE__) . '/../../../../../library/uPush/src/' . 'notification/android/AndroidGroupcast.php');
require_once(dirname(__FILE__) . '/../../../../../library/uPush/src/' . 'notification/android/AndroidUnicast.php');
require_once(dirname(__FILE__) . '/../../../../../library/uPush/src/' . 'notification/android/AndroidCustomizedcast.php');
require_once(dirname(__FILE__) . '/../../../../../library/uPush/src/' . 'notification/ios/IOSBroadcast.php');
require_once(dirname(__FILE__) . '/../../../../../library/uPush/src/' . 'notification/ios/IOSFilecast.php');
require_once(dirname(__FILE__) . '/../../../../../library/uPush/src/' . 'notification/ios/IOSGroupcast.php');
require_once(dirname(__FILE__) . '/../../../../../library/uPush/src/' . 'notification/ios/IOSUnicast.php');
require_once(dirname(__FILE__) . '/../../../../../library/uPush/src/' . 'notification/ios/IOSCustomizedcast.php');

class Upush {
    /* * * * android * * * */
    protected $appkey           = "5d0058084ca357104a000c6b";
    protected $appMasterSecret     = "doadysp495i8zy1u4lhjfjtewkuaxfzg";
    /* * * * ios * * * */
    protected $appkeyIos           = "5d0897b73fc195e11e000c6a";
    protected $appMasterSecretIos     = "7rkb7g8ghcas06j3zcsu29m2vuoknfpn";
    protected $timestamp        = NULL;
    protected $validation_token = NULL;
    private $production = false;
    private $ticker = '球大师';

    public function __construct() {
        //$this->appkey = $key;
        //$this->appMasterSecret = $secret;
        $this->timestamp = strval(time());
        if (ENV == 'production') {
            $this->production = true;
        }
        //$this->production = false;
    }

    public function sendAndroidBroadcast($msg_id, $title, $text, $after_open, $url, $send_time, $expire_time, $builder_id) {
        try {
            $brocast = new \AndroidBroadcast();
            $brocast->setAppMasterSecret($this->appMasterSecret);
            $brocast->setPredefinedKeyValue("appkey",           $this->appkey);
            $brocast->setPredefinedKeyValue("timestamp",        $this->timestamp);
            $brocast->setPredefinedKeyValue("ticker",           $this->ticker);
            $brocast->setPredefinedKeyValue("title",            $title);
            $brocast->setPredefinedKeyValue("text",             $text);
            $brocast->setPredefinedKeyValue("after_open",       $after_open);
            $brocast->setPredefinedKeyValue("builder_id",       $builder_id);
            if ($url && $after_open == 'go_url') {
                $brocast->setPredefinedKeyValue("url",       $url);
            }
            if ($url && $after_open == 'go_custom') {
                $brocast->setPredefinedKeyValue("custom",       $url);
            }
            $now = date('Y-m-d H:i:s', time());
            if ($send_time && $send_time > $now) {
                $brocast->setPredefinedKeyValue("start_time",       $send_time);
            }
            if ($expire_time && $expire_time > $now && $expire_time > $send_time) {
                $brocast->setPredefinedKeyValue("expire_time",       $expire_time);
            }
            $brocast->setPredefinedKeyValue("expire_time",       $expire_time);
            // Set 'production_mode' to 'false' if it's a test device.
            // For how to register a test device, please see the developer doc.
            $brocast->setPredefinedKeyValue("production_mode", $this->production);

            $brocast->setPredefinedKeyValue("mipush",true);
            $brocast->setPredefinedKeyValue("mi_activity",'com.qiudashi.qiudashitiyu.MainActivity');

            // [optional]Set extra fields
            //$brocast->setExtraField("test", "helloworld");
            //print("Sending broadcast notification, please wait...\r\n");
            $str1 = "【" . ENV . "】【广播】【消息ID】【". $msg_id  ."】【title】【 ". $title . "】";
            var_dump($str1);
            //正常应用消息推送
            $res = $brocast->send();


            //应用内消息推送
            $url2=json_decode($url,true);
            $url2['type']=true;
            $url2['contentTitle']=$title;
            $content=json_decode($text,true);

            if(!isset($content['text1'])){
                $content=[];
                $content['text1']=$text;
                $content=json_encode($content);
            }else{
                $content=$text;
            }
            $url2['content']=$content;
            $brocast->setPredefinedKeyValue("custom",       json_encode($url2));
            $brocast->setPredefinedKeyValue("display_type",'message');
            $brocast->send();


            $str2 = "【消息ID】【". $msg_id  ."】【返回结果】【 " . $res . "】";
            var_dump($str2);
            return $res;
        } catch (\Exception $e) {

            $push_model = new PushMsgModel();
            $r = $push_model->msgStatusSent($msg_id, '', 3);
            print("Caught exception: " . $e->getMessage());
        }
    }

    function sendAndroidUnicast() {
        try {
            $unicast = new AndroidUnicast();
            $unicast->setAppMasterSecret($this->appMasterSecret);
            $unicast->setPredefinedKeyValue("appkey",           $this->appkey);
            $unicast->setPredefinedKeyValue("timestamp",        $this->timestamp);
            // Set your device tokens here
            $unicast->setPredefinedKeyValue("device_tokens",    "xx");
            $unicast->setPredefinedKeyValue("ticker",           "Android unicast ticker");
            $unicast->setPredefinedKeyValue("title",            "Android unicast title");
            $unicast->setPredefinedKeyValue("text",             "Android unicast text");
            $unicast->setPredefinedKeyValue("after_open",       "go_app");
            // Set 'production_mode' to 'false' if it's a test device.
            // For how to register a test device, please see the developer doc.
            $unicast->setPredefinedKeyValue("production_mode", "true");
            // Set extra fields
            $unicast->setExtraField("test", "helloworld");
            print("Sending unicast notification, please wait...\r\n");
            $unicast->send();
            print("Sent SUCCESS\r\n");
        } catch (Exception $e) {
            print("Caught exception: " . $e->getMessage());
        }
    }

    function sendAndroidFilecast($msg_id, $title, $text, $after_open, $url, $send_time, $expire_time, $device_tokens, $builder_id) {
        try {
            $filecast = new \AndroidFilecast();

            $filecast->setAppMasterSecret($this->appMasterSecret);
            $filecast->setPredefinedKeyValue("appkey",           $this->appkey);
            $filecast->setPredefinedKeyValue("mipush", true);
            $filecast->setPredefinedKeyValue("timestamp",        $this->timestamp);
            $filecast->setPredefinedKeyValue("ticker",           $this->ticker);
            $filecast->setPredefinedKeyValue("title",            $title);
            $filecast->setPredefinedKeyValue("text",             $text);
            $filecast->setPredefinedKeyValue("after_open",       $after_open);  //go to app
            $filecast->setPredefinedKeyValue("builder_id",       $builder_id);

            if ($url && $after_open == 'go_url') {
                $filecast->setPredefinedKeyValue("url",       $url);
            }
            if ($url && $after_open == 'go_custom') {
                $filecast->setPredefinedKeyValue("custom",       $url);
            }
            $now = date('Y-m-d H:i:s', time());
            if ($send_time && $send_time > $now) {
                $filecast->setPredefinedKeyValue("start_time",       $send_time);
            }
            if ($expire_time && $expire_time > $now && $expire_time > $send_time) {
                $filecast->setPredefinedKeyValue("expire_time",       $expire_time);
            }

            $filecast->setPredefinedKeyValue("production_mode", $this->production);

            $filecast->setPredefinedKeyValue("mi_activity", 'com.qiudashi.qiudashitiyu.MainActivity');

            $file_content = '';
            foreach ($device_tokens as $v) {
                $file_content .= $file_content ? "\n" . $v : $v;
            }
            //var_dump($file_content);
            $str1 = "【" . ENV . "】【文件播】【消息ID】【". $msg_id  ."】【title】【 ". $title . "】";
            var_dump($str1);
            // Upload your device tokens, and use '\n' to split them if there are multiple tokens
            $filecast->uploadContents($file_content);
            //dump($filecast);
            //print("Sending filecast notification, please wait...\r\n");
            //正常应用消息推送
            $res = $filecast->send();

            //应用内消息推送
            $url2=json_decode($url,true);
            $url2['type']=true;
            $url2['contentTitle']=$title;
            $content=json_decode($text,true);

            if(!isset($content['text1'])){
                $content=[];
                $content['text1']=$text;
                $content=json_encode($content);
            }else{
                $content=$text;
            }
            $url2['content']=$content;
            $filecast->setPredefinedKeyValue("custom",       json_encode($url2));
            $filecast->setPredefinedKeyValue("display_type",'message');
            $filecast->send();


            $str2 = "【消息ID】【". $msg_id  ."】【返回结果】【 " . $res . "】";
            var_dump($str2);
            return $res;
        } catch (\Exception $e) {

            $push_model = new PushMsgModel();
            $r = $push_model->msgStatusSent($msg_id, '', 3);
            print("Caught exception: " . $e->getMessage());
        }
    }


    function sendAndroidGroupcast() {
        try {
            /*
              *  Construct the filter condition:
              *  "where":
              *	{
              *		"and":
              *		[
                *			{"tag":"test"},
                *			{"tag":"Test"}
              *		]
              *	}
              */
            $filter = 	array(
                "where" => 	array(
                    "and" 	=>  array(
                        array(
                            "tag" => "test"
                        ),
                        array(
                            "tag" => "Test"
                        )
                    )
                )
            );

            $groupcast = new AndroidGroupcast();
            $groupcast->setAppMasterSecret($this->appMasterSecret);
            $groupcast->setPredefinedKeyValue("appkey",           $this->appkey);
            $groupcast->setPredefinedKeyValue("timestamp",        $this->timestamp);
            // Set the filter condition
            $groupcast->setPredefinedKeyValue("filter",           $filter);
            $groupcast->setPredefinedKeyValue("ticker",           "Android groupcast ticker");
            $groupcast->setPredefinedKeyValue("title",            "Android groupcast title");
            $groupcast->setPredefinedKeyValue("text",             "Android groupcast text");
            $groupcast->setPredefinedKeyValue("after_open",       "go_app");
            // Set 'production_mode' to 'false' if it's a test device.
            // For how to register a test device, please see the developer doc.
            $groupcast->setPredefinedKeyValue("production_mode", "true");
            print("Sending groupcast notification, please wait...\r\n");
            $groupcast->send();
            print("Sent SUCCESS\r\n");
        } catch (Exception $e) {
            print("Caught exception: " . $e->getMessage());
        }
    }

    function sendAndroidCustomizedcast() {
        try {
            $customizedcast = new AndroidCustomizedcast();
            $customizedcast->setAppMasterSecret($this->appMasterSecret);
            $customizedcast->setPredefinedKeyValue("appkey",           $this->appkey);
            $customizedcast->setPredefinedKeyValue("timestamp",        $this->timestamp);
            // Set your alias here, and use comma to split them if there are multiple alias.
            // And if you have many alias, you can also upload a file containing these alias, then
            // use file_id to send customized notification.
            $customizedcast->setPredefinedKeyValue("alias",            "xx");
            // Set your alias_type here
            $customizedcast->setPredefinedKeyValue("alias_type",       "xx");
            $customizedcast->setPredefinedKeyValue("ticker",           "Android customizedcast ticker");
            $customizedcast->setPredefinedKeyValue("title",            "Android customizedcast title");
            $customizedcast->setPredefinedKeyValue("text",             "Android customizedcast text");
            $customizedcast->setPredefinedKeyValue("after_open",       "go_app");
            print("Sending customizedcast notification, please wait...\r\n");
            $customizedcast->send();
            print("Sent SUCCESS\r\n");
        } catch (Exception $e) {
            print("Caught exception: " . $e->getMessage());
        }
    }

    function sendAndroidCustomizedcastFileId() {
        try {
            $customizedcast = new AndroidCustomizedcast();
            $customizedcast->setAppMasterSecret($this->appMasterSecret);
            $customizedcast->setPredefinedKeyValue("appkey",           $this->appkey);
            $customizedcast->setPredefinedKeyValue("timestamp",        $this->timestamp);
            // if you have many alias, you can also upload a file containing these alias, then
            // use file_id to send customized notification.
            $customizedcast->uploadContents("aa"."\n"."bb");
            // Set your alias_type here
            $customizedcast->setPredefinedKeyValue("alias_type",       "xx");
            $customizedcast->setPredefinedKeyValue("ticker",           "Android customizedcast ticker");
            $customizedcast->setPredefinedKeyValue("title",            "Android customizedcast title");
            $customizedcast->setPredefinedKeyValue("text",             "Android customizedcast text");
            $customizedcast->setPredefinedKeyValue("after_open",       "go_app");
            print("Sending customizedcast notification, please wait...\r\n");
            $customizedcast->send();
            print("Sent SUCCESS\r\n");
        } catch (Exception $e) {
            print("Caught exception: " . $e->getMessage());
        }
    }
    //IOS广播
    function sendIOSBroadcast($msg_id, $title, $text, $after_open, $url, $send_time, $expire_time, $builder_id) {
        try {
            $brocast = new \IOSBroadcast();
            $brocast->setAppMasterSecret($this->appMasterSecretIos);
            $brocast->setPredefinedKeyValue("appkey",           $this->appkeyIos);
            $brocast->setPredefinedKeyValue("timestamp",        $this->timestamp);

            $content=json_decode($text,true);
            if(!is_array($content)){
                $content=[];
                $content['text1']=$text;

            }

            $body='';
            if(isset($content['text1'])){
                $body.=$content['text1'];
            }
            if(isset($content['text2'])){
                $body.=(isset($content['text1'])&&$content['text1']!='')?'
'.$content['text2']:$content['text2'];
            }
            if(isset($content['text3'])){
                $body.=(isset($content['text2'])&&$content['text2']!='')?'
'.$content['text3']:$content['text3'];
            }
            $alert = [
                "title"    => $title,
                "subtitle" => '',
                "body"     => $body
            ];
            $brocast->setPredefinedKeyValue("alert", $alert);
            $brocast->setPredefinedKeyValue("badge", 0);
            $brocast->setPredefinedKeyValue("sound", "chime");
            // Set 'production_mode' to 'true' if your app is under production mode
            $brocast->setPredefinedKeyValue("production_mode", $this->production);
            // Set customized fields
            //$brocast->setCustomizedField("test", "helloworld");

            if ($url && $after_open == 'go_url') {
                $url=json_decode($url,true);
                $brocast->setCustomizedField("url",$url);
            }
            //自定义参数
            if ($url && $after_open == 'go_custom') {
                $url=json_decode($url,true);
                $brocast->setCustomizedField("custom", $url);
            }
            $now = date('Y-m-d H:i:s', time());
            if ($send_time && $send_time > $now) {
                $brocast->setPredefinedKeyValue("start_time",       $send_time);
            }
            if ($expire_time && $expire_time > $now && $expire_time > $send_time) {
                $brocast->setPredefinedKeyValue("expire_time",       $expire_time);
            }
            $str1 = "【" . ENV . "】【IOS广播】【消息ID】【". $msg_id  ."】【title】【 ". $title . "】";
            dump($str1);

            $res=$brocast->send();

            $str2 = "【消息ID】【". $msg_id  ."】【返回结果】【 " . $res . "】";
            dump($str2);
            return $res;
        } catch (Exception $e) {

            $push_model = new PushMsgModel();
            $r = $push_model->msgStatusSent($msg_id, '', 3);
            print("Caught exception: " . $e->getMessage());
        }
    }

    function sendIOSUnicast() {
        try {
            $unicast = new IOSUnicast();
            $unicast->setAppMasterSecret($this->appMasterSecret);
            $unicast->setPredefinedKeyValue("appkey",           $this->appkey);
            $unicast->setPredefinedKeyValue("timestamp",        $this->timestamp);
            // Set your device tokens here
            $unicast->setPredefinedKeyValue("device_tokens",    "xx");
            $unicast->setPredefinedKeyValue("alert", "IOS 单播测试");
            $unicast->setPredefinedKeyValue("badge", 0);
            $unicast->setPredefinedKeyValue("sound", "chime");
            // Set 'production_mode' to 'true' if your app is under production mode
            $unicast->setPredefinedKeyValue("production_mode", "false");
            // Set customized fields
            $unicast->setCustomizedField("test", "helloworld");
            print("Sending unicast notification, please wait...\r\n");
            $unicast->send();
            print("Sent SUCCESS\r\n");
        } catch (Exception $e) {
            print("Caught exception: " . $e->getMessage());
        }
    }

    //IOS文件播
    function sendIOSFilecast($msg_id, $title, $text, $after_open, $url, $send_time, $expire_time, $device_tokens, $builder_id) {
        try {
            $filecast = new \IOSFilecast();
            //dump($filecast);die;
            $filecast->setAppMasterSecret($this->appMasterSecretIos);
            $filecast->setPredefinedKeyValue("appkey",           $this->appkeyIos);
            $filecast->setPredefinedKeyValue("timestamp",        $this->timestamp);

            $content=json_decode($text,true);

            if(!is_array($content)){
                $content=[];
                $content['text1']=$text;

            }


            $body='';
            if(isset($content['text1'])){
                $body.=$content['text1'];
            }
            if(isset($content['text2'])){
                $body.=(isset($content['text1'])&&$content['text1']!='')?'
'.$content['text2']:$content['text2'];
            }
            if(isset($content['text3'])){
                $body.=(isset($content['text2'])&&$content['text2']!='')?'
'.$content['text3']:$content['text3'];
            }
            $alert = [
                "title"    => $title,
                "subtitle" => '',
                "body"     => $body
            ];


            $filecast->setPredefinedKeyValue("alert", $alert);
            $filecast->setPredefinedKeyValue("badge", 0);
            $filecast->setPredefinedKeyValue("sound", "chime");
            // Set 'production_mode' to 'true' if your app is under production mode
            $filecast->setPredefinedKeyValue("production_mode", $this->production);
//            $filecast->setPredefinedKeyValue("production_mode", true);
            //print("Uploading file contents, please wait...x\r\n");
            // Upload your device tokens, and use '\n' to split them if there are multiple tokens

            if ($url && $after_open == 'go_url') {
                $url=json_decode($url,true);
                $filecast->setCustomizedField("url",$url);
            }
            //自定义参数
            if ($url && $after_open == 'go_custom') {
                $url=json_decode($url,true);
                $filecast->setCustomizedField("custom", $url);
            }
            $now = date('Y-m-d H:i:s', time());
            if ($send_time && $send_time > $now) {
                $filecast->setPredefinedKeyValue("start_time",       $send_time);
            }
            if ($expire_time && $expire_time > $now && $expire_time > $send_time) {
                $filecast->setPredefinedKeyValue("expire_time",       $expire_time);
            }
            $str1 = "【" . ENV . "】【IOS文件播】【消息ID】【". $msg_id  ."】【title】【 ". $title . "】";
            dump($str1);
            //dump($filecast);die;
            $file_content = '';
            foreach ($device_tokens as $v) {
                $file_content .= $file_content ? "\n" . $v : $v;
            }

            $filecast->uploadContents($file_content);

            $res=$filecast->send();
            //dump($res);
            $str2 = "【消息ID】【". $msg_id  ."】【返回结果】【 " . $res . "】";
            //dump($str2);
            return $res;
        } catch (Exception $e) {

            $push_model = new PushMsgModel();
            $r = $push_model->msgStatusSent($msg_id, '', 3);
            print("Caught exception: " . $e->getMessage());
        }
    }

    function sendIOSGroupcast() {
        try {
            /*
              *  Construct the filter condition:
              *  "where":
              *	{
              *		"and":
              *		[
                *			{"tag":"iostest"}
              *		]
              *	}
              */
            $filter = 	array(
                "where" => 	array(
                    "and" 	=>  array(
                        array(
                            "tag" => "iostest"
                        )
                    )
                )
            );

            $groupcast = new IOSGroupcast();
            $groupcast->setAppMasterSecret($this->appMasterSecret);
            $groupcast->setPredefinedKeyValue("appkey",           $this->appkey);
            $groupcast->setPredefinedKeyValue("timestamp",        $this->timestamp);
            // Set the filter condition
            $groupcast->setPredefinedKeyValue("filter",           $filter);
            $groupcast->setPredefinedKeyValue("alert", "IOS 组播测试");
            $groupcast->setPredefinedKeyValue("badge", 0);
            $groupcast->setPredefinedKeyValue("sound", "chime");
            // Set 'production_mode' to 'true' if your app is under production mode
            $groupcast->setPredefinedKeyValue("production_mode", "false");
            print("Sending groupcast notification, please wait...\r\n");
            $groupcast->send();
            print("Sent SUCCESS\r\n");
        } catch (Exception $e) {
            print("Caught exception: " . $e->getMessage());
        }
    }

    function sendIOSCustomizedcast() {
        try {
            $customizedcast = new IOSCustomizedcast();
            $customizedcast->setAppMasterSecret($this->appMasterSecret);
            $customizedcast->setPredefinedKeyValue("appkey",           $this->appkey);
            $customizedcast->setPredefinedKeyValue("timestamp",        $this->timestamp);

            // Set your alias here, and use comma to split them if there are multiple alias.
            // And if you have many alias, you can also upload a file containing these alias, then
            // use file_id to send customized notification.
            $customizedcast->setPredefinedKeyValue("alias", "xx");
            // Set your alias_type here
            $customizedcast->setPredefinedKeyValue("alias_type", "xx");
            $customizedcast->setPredefinedKeyValue("alert", "IOS 个性化测试");
            $customizedcast->setPredefinedKeyValue("badge", 0);
            $customizedcast->setPredefinedKeyValue("sound", "chime");
            // Set 'production_mode' to 'true' if your app is under production mode
            $customizedcast->setPredefinedKeyValue("production_mode", "false");
            print("Sending customizedcast notification, please wait...\r\n");
            $customizedcast->send();
            print("Sent SUCCESS\r\n");
        } catch (Exception $e) {
            print("Caught exception: " . $e->getMessage());
        }
    }

    public function makeParamsForCheckStatus($url, $task_id,$send_limit) {
        $method = 'POST';
        //ios
        if($send_limit==2){
            $post_body = ['appkey' => $this->appkeyIos, 'timestamp' => $this->timestamp, 'task_id' => $task_id];
            $appMasterSecret=$this->appMasterSecretIos;
        }else{
            $post_body = ['appkey' => $this->appkey, 'timestamp' => $this->timestamp, 'task_id' => $task_id];
            $appMasterSecret=$this->appMasterSecret;
        }

        $post_body = json_encode($post_body);
        $keystring = $method . $url . $post_body . $appMasterSecret;
        return ['sign' => md5($keystring), 'post_body' => $post_body];
    }
}

// Set your appkey and master secret here
$demo = new Upush("your appkey", "your app master secret");
//$demo->sendAndroidUnicast();
/* these methods are all available, just fill in some fields and do the test
 * $demo->sendAndroidBroadcast();
 * $demo->sendAndroidFilecast();
 * $demo->sendAndroidGroupcast();
 * $demo->sendAndroidCustomizedcast();
 * $demo->sendAndroidCustomizedcastFileId();
 *
 * $demo->sendIOSBroadcast();
 * $demo->sendIOSUnicast();
 * $demo->sendIOSFilecast();
 * $demo->sendIOSGroupcast();
 * $demo->sendIOSCustomizedcast();
 */
