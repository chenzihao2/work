<?php
/**
 * 字符串处理通用函数
 * User: YangChao
 * Date: 2018/7/25
 */

namespace QK\HaoLiao\Common;


use Grafika\Grafika;
use QK\CloudStorage\CloudStorageFactory;
use QK\WSF\Settings\AppSetting;

class CommonHandler {


    /**
     * @var String
     */
    private static $_instance;

    public static function newInstance(){
        if(!(self::$_instance instanceof CommonHandler)){
            self::$_instance = new CommonHandler();
        }
        return self::$_instance;
    }

    public function __construct(){

    }

    public function clientIpAddress(){
        $arr_ip_header = array('HTTP_CDN_SRC_IP', 'HTTP_PROXY_CLIENT_IP', 'HTTP_WL_PROXY_CLIENT_IP', 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR',);
        $client_ip = 'unknown';
        foreach($arr_ip_header as $key){
            if(!empty($_SERVER[$key]) && strtolower($_SERVER[$key]) != 'unknown'){
                $client_ip = $_SERVER[$key];
                break;
            }
        }
        return $this->myIp2long($client_ip);
    }

    public function myIp2long($ip){
        $ipArr = explode('.', $ip);
        return 256 * 256 * 256 * intval($ipArr[3]) + 256 * 256 * intval($ipArr[2]) + 256 * intval($ipArr[1]) + intval($ipArr[0]);
    }

    public function myLong2ip($longIP){
        $ipTemp = long2ip($longIP);
        return self::ipReverse($ipTemp);
    }

    public static function ipReverse($ip){
        $ipTemp = explode('.', $ip);
        return $ipTemp[3] . '.' . $ipTemp[2] . '.' . $ipTemp[1] . '.' . $ipTemp[0];
    }

    /**
     * 身份证格式检查
     * @param $value
     * @return bool
     */
    public function checkIdCard($value){
        if(!preg_match('/^\d{17}[0-9xX]$/', $value)){ //基本格式校验
            return false;
        }

        $parsed = date_parse(substr($value, 6, 8));
        if(!(isset($parsed['warning_count']) && $parsed['warning_count'] == 0)){ //年月日位校验
            return false;
        }

        $base = substr($value, 0, 17);

        $factor = [7, 9, 10, 5, 8, 4, 2, 1, 6, 3, 7, 9, 10, 5, 8, 4, 2];

        $tokens = ['1', '0', 'X', '9', '8', '7', '6', '5', '4', '3', '2'];

        $checkSum = 0;
        for($i = 0; $i < 17; $i++){
            $checkSum += intval(substr($base, $i, 1)) * $factor[$i];
        }

        $mod = $checkSum % 11;
        $token = $tokens[$mod];

        $lastChar = strtoupper(substr($value, 17, 1));

        return ($lastChar === $token); //最后一位校验位校验
    }

    /**
     * 是否微信
     * @return bool
     */
    public function isWeChat(){
        if(strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger') !== false){
            return true;
        }
        return false;
    }

    public function getPlatform($platform) {
        if ($platform == 'h5') {  // 微信
            return 'wx_h5';
        } elseif ($platform == 'android') {  // android
            return 'android';
        } elseif ($platform == 'ios') {  // ios
            return 'ios';
        } elseif ($platform == 'xcx') {  // 百度小程序
            return 'bd_xcx';
        }
    }


    /**
     * 生成二维码
     * @param $url
     * @return string
     */
    public function qrCode($url, $fileName){
        $prefix = 'qrcode';
        require_once __DIR__ . '/./phpqrcode.php';
        $_appSetting = AppSetting::newInstance(AppRoot);
        $filePath = $this->getPath($prefix);
        $qrCodePath = $prefix . "/" . $filePath . "/" . $fileName;
        $localQrCodePath = $_appSetting->getConstantSetting('FILE-UPLOAD') . '/' . $qrCodePath;
        \QRcode::png($url, $localQrCodePath, 'L', 6, 1);

        $res = [];
        $res['path'] = $qrCodePath;
        $res['fullPath'] = $localQrCodePath;
        return $res;
    }

    /**
     * 生成海报
     * @param $qrCodePath
     * @param $fileName
     * @return array
     */
    public function makePoster($qrCodePath, $fileName){
        $_appSetting = AppSetting::newInstance(AppRoot);

        // 构建海报图片
        $backGroudImg = $_appSetting->getConstantSetting('FILE-STATIC') . '/backgroud/poster.png';

        $prefix = 'poster';
        $filePath = $this->getPath($prefix);
        $posterPath = $prefix . "/" . $filePath . "/" . $fileName;
        $localPosterPath = $_appSetting->getConstantSetting('FILE-UPLOAD') . '/' . $posterPath;

        $editor = Grafika::createEditor();
        $editor->open($backGroud, $backGroudImg);
        $editor->open($qrCode, $qrCodePath);
        $editor->blend($backGroud, $qrCode, 'normal', 1, 'top-left', 522, 1125); // 画二维码
        $editor->save($backGroud, $localPosterPath, 'jpeg', 100); // 生成图片

        $res = [];
        $res['url'] = $_appSetting->getConstantSetting('STATIC_URL') . $posterPath;
        $res['path'] = $posterPath;
        $res['fullPath'] = $localPosterPath;

        try {
            $qiNiuPublicKey = $_appSetting->getConstantSetting('QiNiu-PUBLIC-KEY');;
            $qiNiuPrivateKey = $_appSetting->getConstantSetting('QiNiu-PRIVATE-KEY');;
            $qiNiuObj = CloudStorageFactory::newInstance()->createQiNiuObj($qiNiuPublicKey, $qiNiuPrivateKey);
            $qiNiuBucket = $_appSetting->getConstantSetting('QiNiu-BUCKET');
            $qiNiuObj->upload($qiNiuBucket, $posterPath, $localPosterPath);
            $result = $qiNiuObj->getRet();

            if ($result['hash']) {
                return $res;
            } else {
                return [];
            }
        } catch (\Exception $e) {
            return [];
        }
    }


    /**
     * 获取今日目录
     * @param $prefix
     * @return string
     */
    public function getPath($prefix) {
        $_appSetting = AppSetting::newInstance(AppRoot);
        $time = time();
        $monthPathString = $_appSetting->getConstantSetting('FILE-UPLOAD') . "/" . $prefix . "/" . date('Ym', $time);

        $this->checkPath($monthPathString);
        $dayPathString = $monthPathString . "/" . date("d", $time);
        $this->checkPath($dayPathString);
        $onlinePath = date("Ym", $time) . "/" . date("d", $time);
        return $onlinePath;
    }

    /**
     * 创建目录
     * @param $path
     */
    private function checkPath($path) {
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }
    }

    public function httpPost($url, $param, $verify = false, $header = '') {
        $res = $this->httpRequestOnce($url, $param, 'post', $verify, $header);
        if ($res && $res["result"] === false) {
            $res = $this->httpRequestOnce($url, $param, 'post', $verify, $header);
        }
        return $res;
    }

    public function httpGet($url, $param) {
        $res = $this->httpRequestOnce($url, $param, 'get', false);
        if ($res && $res["result"] === false) {
            $res = $this->httpRequestOnce($url, $param, 'get', false);
        }
        return $res;
    }

    public function httpGetRequest($url, $param) {
        return $this->httpRequestOnce($url, $param, 'get', false);
    }

    public function httpPostRequest($url, $param, $verify = false, $header = '') {
      return $this->httpRequestOnce($url, $param, 'post', $verify, $header);
    }
    /**
     * 发起一次Curl模拟的http请求
     * @param        $url
     * @param        $param
     *              array:在内部,自动被http_build_query()转换
     *              string:必须经http_build_query()转换
     * @param string $type get|post方式
     * @param bool   $verify 证书验证方式 true|false
     * @param string $cert 证书地址
     * @param string $key 私钥地址
     * @param string $header header设置
     *                   array("Host:127.0.0.1",
     *                   "Content-Type:application/x-www-form-urlencoded",
     *                   'Referer:http://127.0.0.1/toolindex.xhtml',
     *                   'User-Agent: Mozilla/4.0 (compatible; MSIE .0; Windows NT 6.1; Trident/4.0; SLCC2;)');
     * @return array
     */
    private function httpRequestOnce($url, $param, $type = 'post', $verify = false, $cert = '', $key = '', $header = '') {
        if (!empty($param) && is_array($param)) {
            $param = http_build_query($param);
        }
        if (!empty($param)) {
            $curlHandle = curl_init($url . ($type != 'post' ? "?$param" : ''));
        } else {
            $curlHandle = curl_init($url);
        }                                    // 初始化curl
        $options = array(
            // 不显示返回的Header区域内容
            CURLOPT_HEADER => false,
            // 获取的信息以文件流的形式返回
            CURLOPT_RETURNTRANSFER => true,
            // 连接超时
            CURLOPT_CONNECTTIMEOUT => 20,
            // 总超时
            CURLOPT_TIMEOUT => 40
        );
        $options[CURLOPT_DNS_USE_GLOBAL_CACHE] = false;
        if ($type == 'post') {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = $param;
        }

        $options[CURLOPT_SSL_VERIFYPEER] = $verify; // 验证对方提供的（读取https）证书是否有效，过期，或是否通过CA颁发的！
        $options[CURLOPT_SSL_VERIFYHOST] = $verify; // 从证书中检查SSL加密算法是否存在

        if ($verify) {
            $options[CURLOPT_SSLCERTTYPE] = 'PEM';    //证书类型
            $options[CURLOPT_SSLCERT] = $cert;        //证书文件
            $options[CURLOPT_SSLKEYTYPE] = 'PEM';     //私钥加密类型
            $options[CURLOPT_SSLKEY] = $key;          //私钥文件
        }
        if ($header !== '') {
            $options[CURLOPT_HTTPHEADER] = $header; //header信息设置
        }
        curl_setopt_array($curlHandle, $options);
        $httpResult = curl_exec($curlHandle);
        return $httpResult;
    }
}
