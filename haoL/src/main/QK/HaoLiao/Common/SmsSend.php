<?php
/**
 * 短信发送处理类
 * User: WangHui
 * Date: 2018/10/9
 * Time: 9:55
 */

namespace QK\HaoLiao\Common;


class SmsSend {

    /**
     * 手机号验证
     * @param $telephone
     * @return bool
     */
    public function mobileCheck($telephone) {
        if( !preg_match('/^1[3|4|5|6|7|8]\d{9}$/', $telephone)) {
            return false;
        }else{
            return true;
        }
    }
    /**
     * 短信发送接口
     * @param string $mobile 手机号
     * @param string $content 短信内容
     * @return bool|string
     */
    public function send($mobile, $content) {
        if (empty($mobile) or empty($content)) {
            return "参数不可为空";
        }
        $sn = "SDK-666-010-03413";
        $pwd = strtoupper(md5("SDK-666-010-03413392325"));
        $url = 'http://sdk.entinfo.cn:8061/webservice.asmx/mdsmssend?sn=' . $sn . '&pwd=' . $pwd . '&mobile=' . $mobile . '&content=' . $content . '&ext=10&stime=&rrid=&msgfmt=';
        $opts = [
            'http' => [
                'method' => 'GET',
                'timeout' => 1,
            ]
        ];
        $cnt = 0;
        $result = false;
        while ($cnt < 3 && ($result = file_get_contents($url, false, stream_context_create($opts))) === false) {
            $cnt++;
        }
        return $result;

    }
}