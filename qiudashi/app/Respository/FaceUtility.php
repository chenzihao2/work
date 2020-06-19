<?php
/**
 * Created by PhpStorm.
 * descript:系统工具类
 */

namespace App\Respository;
use Qiniu\Auth;
use Qiniu\Storage\UploadManager;
require DOCUMENT_ROOT . '/../vendor/qiniu/php-sdk/autoload.php';

class FaceUtility
{
   //获取当前时间

    public function getCurrentTime(){
        return date('Y-m-d H:i:s');
    }

    //获取当前服务器的操作系统
    protected function getServerOs(){
        return PHP_OS;
    }

    //获取客户端的操作系统
    protected function getClientOS(){
        $os='';
        $Agent=$_SERVER['HTTP_USER_AGENT'];
        if (eregi('win',$Agent)&&strpos($Agent, '95')){
            $os='Windows 95';
        }elseif(eregi('win 9x',$Agent)&&strpos($Agent, '4.90')){
            $os='Windows ME';
        }elseif(eregi('win',$Agent)&&ereg('98',$Agent)){
            $os='Windows 98';
        }elseif(eregi('win',$Agent)&&eregi('nt 5.0',$Agent)){
            $os='Windows 2000';
        }elseif(eregi('win',$Agent)&&eregi('nt 6.0',$Agent)){
            $os='Windows Vista';
        }elseif(eregi('win',$Agent)&&eregi('nt 6.1',$Agent)){
            $os='Windows 7';
        }elseif(eregi('win',$Agent)&&eregi('nt 5.1',$Agent)){
            $os='Windows XP';
        }elseif(eregi('win',$Agent)&&eregi('nt',$Agent)){
            $os='Windows NT';
        }elseif(eregi('win',$Agent)&&ereg('32',$Agent)){
            $os='Windows 32';
        }elseif(eregi('linux',$Agent)){
            $os='Linux';
        }elseif(eregi('unix',$Agent)){
            $os='Unix';
        }else if(eregi('sun',$Agent)&&eregi('os',$Agent)){
            $os='SunOS';
        }elseif(eregi('ibm',$Agent)&&eregi('os',$Agent)){
            $os='IBM OS/2';
        }elseif(eregi('Mac',$Agent)&&eregi('PC',$Agent)){
            $os='Macintosh';
        }elseif(eregi('PowerPC',$Agent)){
            $os='PowerPC';
        }elseif(eregi('AIX',$Agent)){
            $os='AIX';
        }elseif(eregi('HPUX',$Agent)){
            $os='HPUX';
        }elseif(eregi('NetBSD',$Agent)){
            $os='NetBSD';
        }elseif(eregi('BSD',$Agent)){
            $os='BSD';
        }elseif(ereg('OSF1',$Agent)){
            $os='OSF1';
        }elseif(ereg('IRIX',$Agent)){
            $os='IRIX';
        }elseif(eregi('FreeBSD',$Agent)){
            $os='FreeBSD';
        }elseif($os==''){
            $os='Unknown';
        }
        return $os;
    }

    // 生成唯一ID
    public function create_guid($namespace = 'qiudashi')
    {
        static $guid = '';
        $uid = uniqid("face", true);
        $data = $namespace;
        $data .= $_SERVER['REQUEST_TIME'];
        $data .= $_SERVER['HTTP_USER_AGENT'];
        //$data .= $_SERVER['LOCAL_ADDR'];
        //$data .= $_SERVER['LOCAL_PORT'];
        $data .= $_SERVER['REMOTE_ADDR'];
        $data .= $_SERVER['REMOTE_PORT'];
        $hash = strtoupper(hash('ripemd128', $uid . $guid . md5($data)));
        $guid = '' .
            substr($hash, 0, 8) .
            '-' .
            substr($hash, 8, 4) .
            '-' .
            substr($hash, 12, 4) .
            '-' .
            substr($hash, 16, 4) .
            '-' .
            substr($hash, 20, 12) .
            '';
        return $guid;
    }





    /**
     * 价格由元转分
     * @param $price 金额
     * @return int
     */
    public function ncPriceYuan2Fen($price){
        $price = (int)$price;
        $price = (int)$this->ncPriceCalculate($this->ncPriceFormat($price), "*", 100);
        return $price;
    }


    /**
     * 价格由分转元
     * @param $price 金额
     * @return int
     */
    public function ncPriceFen2Yuan($price){
        $price = $this->ncPriceCalculate($this->ncPriceFormat($price), "/", 100);
        return $price;
    }

    public function ncPriceFen2YuanInt($price){
        $price = floor($this->ncPriceCalculate($this->ncPriceFormat($price), "/", 100));
        return $price;
    }

    /**
     * 价格格式化
     * @param int $price
     * @return string    $price_format
     */
    protected function ncPriceFormat($price){
        $price_format = number_format($price, 2, '.', '');
        return $price_format;
    }



    /**
     * PHP精确计算  主要用于货币的计算用
     * @param        $n1 第一个数
     * @param        $symbol 计算符号 + - * / %
     * @param        $n2 第二个数
     * @param string $scale 精度 默认为小数点后两位
     * @return  string
     */
    protected function ncPriceCalculate($n1, $symbol, $n2, $scale = '2'){
        $res = "";
        switch($symbol){
            case "+"://加法
                $res = bcadd($n1, $n2, $scale);
                break;
            case "-"://减法
                $res = bcsub($n1, $n2, $scale);
                break;
            case "*"://乘法
                $res = bcmul($n1, $n2, $scale);
                break;
            case "/"://除法
                $res = bcdiv($n1, $n2, $scale);
                break;
            case "%"://求余、取模
                $res = bcmod($n1, $n2, $scale);
                break;
            default:
                $res = "";
                break;
        }
        return $res;
    }

    /*
     * 获取ip
     */
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
    //原始没有处理过的ip
    public function clientIp(){
        $arr_ip_header = array('HTTP_CDN_SRC_IP', 'HTTP_PROXY_CLIENT_IP', 'HTTP_WL_PROXY_CLIENT_IP', 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR',);
        $client_ip = 'unknown';
        foreach($arr_ip_header as $key){
            if(!empty($_SERVER[$key]) && strtolower($_SERVER[$key]) != 'unknown'){
                $client_ip = $_SERVER[$key];
                break;
            }
        }
        return $client_ip;
    }



    /*
     * http请求
     */
    public function httpRequestOnce($url, $param = [], $type = 'get', $verify = false, $cert = '', $key = '', $header = '') {
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

        $options[CURLOPT_SSL_VERIFYPEER] = $verify; // 验证对方提供的（读取https）证书是否有效，过期， >或是否通过CA颁发的！
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

    /*
     * 处理base64成图片格式
     */
    public function dealBase64($base64_image_content) {  
        if (preg_match('/^(data:\s*image\/(\w+);base64,)/', $base64_image_content, $result)) {
            $type = $result[2];
            $file_path = DOCUMENT_ROOT . "/base64_image/";
            if(!file_exists($file_path)){
                mkdir($file_path, 0775);
            }
            $new_file = $file_path . time() . ".$type";
            if (file_put_contents($new_file, base64_decode(str_replace($result[1], '', $base64_image_content)))){
                return $this->upload2qiniu($new_file);
            } else {
                throw new \Exception('', 2000204);
            }
        } else {
            throw new \Exception('', 2000203);
        }
    }

    /*
     * 上传图片至七牛
     */
    public function upload2qiniu($file_path, $new_file_path = '') {
        $accessKey = config('app.qn_access_key');
        $secretKey = config('app.qn_secret_key');
        $bucket = config('app.qn_bucket');
        $auth = new Auth($accessKey, $secretKey);
        $token = $auth->uploadToken($bucket);
        $uploadMgr = new UploadManager();
        !$new_file_path && $new_file_path = basename($file_path);
        list($ret, $err) = $uploadMgr->putFile($token, $new_file_path, $file_path);
        if ($err !== null) {
            var_dump($err);die;
            //throw new \Exception('', 2000205);
        }
        $url = config('app.qn_domain') . $ret['key'];
        return $url;
    }

    /*
     * 友好显示比赛时间
     */
    public function formatScheduleTime($time) {
        $res = [];
        $res['schedule_week'] = $this->getTimeWeek($time);
        $res['schedule_date'] = date("m-d", $time);
        $res['schedule_hour'] = date("H:i", $time);
        return $res;
    }

    /*
     * 根据时间戳获取周*
     */
    public function getTimeWeek($time, $i = 0) {
        $weekarray = array("日", "一", "二", "三", "四", "五", "六");
        $oneD = 24 * 60 * 60;
        return "周" . $weekarray[date("w", $time + $oneD * $i)];
    }

    /*
     * 友好的显示发布时间
     */
    public function friendlyDate($sTime, $type = 'normal'){
        if(!$sTime){
            return '';
        }
        //sTime=源时间，cTime=当前时间，dTime=时间差
        $cTime = time();
        $dTime = $cTime - $sTime;
        $dDay = intval(date('z', $cTime)) - intval(date('z', $sTime));
        $dYear = intval(date('Y', $cTime)) - intval(date('Y', $sTime));
        //normal：n秒前，n分钟前，n小时前，日期
        if($type == 'normal'){
            if($dTime < 1800){
                return '刚刚';    //by yangjs
            } elseif($dTime < 3600) {
                return '半小时前';
                //今天的数据.年份相同.日期相同.
            } elseif($dTime >= 3600 && $dDay == 0) {
                return intval($dTime / 3600) . '小时前';
            } elseif($dTime >= 3600 && $dTime <= 21600 && $dDay == 0) {
                return '今天' . date('H:i:s', $sTime);
            } else {
                return date('Y-m-d H:i:s', $sTime);
            }
        } elseif($type == 'mohu') {
            if($dTime < 60){
                return $dTime . '秒前';
            } elseif($dTime < 3600) {
                return intval($dTime / 60) . '分钟前';
            } elseif($dTime >= 3600 && $dDay == 0) {
                return intval($dTime / 3600) . '小时前';
            } elseif($dDay > 0 && $dDay <= 7) {
                return intval($dDay) . '天前';
            } elseif($dDay > 7 && $dDay <= 30) {
                return intval($dDay / 7) . '周前';
            } elseif($dDay > 30) {
                return intval($dDay / 30) . '个月前';
            }
        } elseif($type == 'full') {
            return date('Y-m-d , H:i:s', $sTime);
        } elseif($type == 'ymd') {
            return date('Y-m-d', $sTime);
        } elseif($type == 'mdhis') {
            return date('m-d H:i:s', $sTime);
        } else {
            if($dTime < 60){
                return $dTime . '秒前';
            } elseif($dTime < 3600) {
                return intval($dTime / 60) . '分钟前';
            } elseif($dTime >= 3600 && $dDay == 0) {
                return intval($dTime / 3600) . '小时前';
            } elseif($dYear == 0) {
                return date('Y-m-d H:i:s', $sTime);
            } else {
                return date('Y-m-d H:i:s', $sTime);
            }
        }
    }

    /*
     * 获取首字母
     */
    function getFirstChar($str) {
        if (empty($str)) {
            return '#';
        }
 
        $fir = $fchar = ord($str[0]);
        if ($fchar >= ord('A') && $fchar <= ord('z')) {
            return strtoupper($str[0]);
        }
 
        $s1 = @iconv('UTF-8', 'gb2312//IGNORE', $str);
        $s2 = @iconv('gb2312', 'UTF-8', $s1);
        $s = $s2 == $str ? $s1 : $str;
        if (!isset($s[0]) || !isset($s[1])) {
            return '#';
        }
 
        $asc = ord($s[0]) * 256 + ord($s[1]) - 65536;
 
        if (is_numeric($str)) {
            return $str;
        }
 
        if (($asc >= -20319 && $asc <= -20284) || $fir == 'A') {
            return 'A';
        }
        if (($asc >= -20283 && $asc <= -19776) || $fir == 'B') {
            return 'B';
        }
        if (($asc >= -19775 && $asc <= -19219) || $fir == 'C') {
            return 'C';
        }
        if (($asc >= -19218 && $asc <= -18711) || $fir == 'D') {
            return 'D';
        }
        if (($asc >= -18710 && $asc <= -18527) || $fir == 'E') {
            return 'E';
        }
        if (($asc >= -18526 && $asc <= -18240) || $fir == 'F') {
            return 'F';
        }
        if (($asc >= -18239 && $asc <= -17923) || $fir == 'G') {
            return 'G';
        }
        if (($asc >= -17922 && $asc <= -17418) || $fir == 'H') {
            return 'H';
        }
        if (($asc >= -17417 && $asc <= -16475) || $fir == 'J') {
            return 'J';
        }
        if (($asc >= -16474 && $asc <= -16213) || $fir == 'K') {
            return 'K';
        }
        if (($asc >= -16212 && $asc <= -15641) || $fir == 'L') {
            return 'L';
        }
        if (($asc >= -15640 && $asc <= -15166) || $fir == 'M') {
            return 'M';
        }
        if (($asc >= -15165 && $asc <= -14923) || $fir == 'N') {
            return 'N';
        }
        if (($asc >= -14922 && $asc <= -14915) || $fir == 'O') {
            return 'O';
        }
        if (($asc >= -14914 && $asc <= -14631) || $fir == 'P') {
            return 'P';
        }
        if (($asc >= -14630 && $asc <= -14150) || $fir == 'Q') {
            return 'Q';
        }
        if (($asc >= -14149 && $asc <= -14091) || $fir == 'R') {
            return 'R';
        }
        if (($asc >= -14090 && $asc <= -13319) || $fir == 'S') {
            return 'S';
        }
        if (($asc >= -13318 && $asc <= -12839) || $fir == 'T') {
            return 'T';
        }
        if (($asc >= -12838 && $asc <= -12557) || $fir == 'W') {
            return 'W';
        }
        if (($asc >= -12556 && $asc <= -11848) || $fir == 'X') {
            return 'X';
        }
        if (($asc >= -11847 && $asc <= -11056) || $fir == 'Y') {
            return 'Y';
        }
        if (($asc >= -11055 && $asc <= -10247) || $fir == 'Z') {
            return 'Z';
        }
 
        return '#';
    }

    /**
     * @name: get_encoding
     * @description: 自动检测内容编码进行转换
     * @param: string data
     * @param: string to  目标编码
     * @return: string
     **/
    public function get_encoding($data,$to){
        $encode_arr=array('UTF-8','ASCII','GBK','GB2312','BIG5','JIS','eucjp-win','sjis-win','EUC-JP');
        $encoded=mb_detect_encoding($data, $encode_arr);
        if ($encoded) {
            $data = mb_convert_encoding($data,$to,$encoded);
            return $data;
        } else {
            return false;
        }
    }



    /**
     * Judge the winner of a soccer match according to a certain bwin.
     *初始让球数   最后让球数  初始赔率  终盘赔率  博彩公司编号
     * @param  float  $initBalls, initial loss balls
     * @param  float  $endBalls, end loss balls
     * @param  float  $initOdds, inital odds
     * @param  float  $endOdds, end odds
     * @param  int bwinId, bwin's id
     * @return array 1: the winner; 2: the confidence ratio
     */
    public function soccerAsiaIndexSvmClassifier($initBalls, $endBalls, $initOdds, $endOdds, $bwinId=0){
        $w1 = -0.38185643;
        $w2 = 1.08393914;
        $b = 0.110783;

        $x0 = $endOdds - $initOdds;
        $y0 = $endBalls - $initBalls;

        $z = $w1 * $x0 + $w2 * $y0 + $b;
        $d = abs($z) / (sqrt($w1*$w1 + $w2*$w2));
        $area = 0;
        if($z > 0){
            $area = 1;
        }else if($z < 0){
            $area = -1;
        }
        $conf = 0.125 * $d + 0.5;
        $conf = $conf > 0.75 ? 0.75 : $conf;
        return [$area, $conf];
    }

}
