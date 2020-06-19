<?php
/**
 * User: WangHui
 * Date: 2018/7/4
 * Time: 15:56
 */

namespace QK\HaoLiao\Model;

use QK\HaoLiao\DAL\BaseDAL;
use QK\WSF\Core\Model;

class BaseModel extends Model {

    public function __construct(){
        parent::__construct();
    }

    /**
     * 价格由元转分
     * @param $price 金额
     * @return int
     */
    public function ncPriceYuan2Fen($price){
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


    /**
     * 友好显示比赛时间
     * @param $scheduleTime
     * @return array
     */
    protected function formatScheduleTime($scheduleTime){
        $res = [];
        $res['schedule_week'] = $this->getTimeWeek($scheduleTime);
        $res['schedule_date'] = date("m-d", $scheduleTime);
        $res['schedule_hour'] = date("H:i", $scheduleTime);
        return $res;
    }

    /**
     * 根据时间戳获取周*
     * @param     $time
     * @param int $i
     * @return string
     */
    private function getTimeWeek($time, $i = 0){
        $weekarray = array("日", "一", "二", "三", "四", "五", "六");
        $oneD = 24 * 60 * 60;
        return "周" . $weekarray[date("w", $time + $oneD * $i)];
    }

    /**
     * 友好的时间显示
     * @param  int    $sTime 待显示的时间
     * @param  string $type 类型. normal | mohu | full | ymd | other
     * @return string
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
                return date('m-d H:i:s', $sTime);
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

    public function initTrans() {
        $dal = new BaseDAL($this->_appSetting);
        return  $dal;
    }

    public function retSuccess($data = []) {
        return ['status_code' => 200, 'data' => $data];
    }

    public function retError($statusCode = -1, $msg = '', $data = []) {
        return ['status_code' => $statusCode, 'msg' => $msg, 'data' => $data];
    }

}
