<?php
/**
 * User: WangHui
 * Date: 2018/6/11
 * Time: 18:03
 */

namespace QK\HaoLiao\Common;


class NumberHandler {

    /**
     * @var String
     */
    private static $_instance;

    public static function newInstance(){
        if(!(self::$_instance instanceof NumberHandler)){
            self::$_instance = new NumberHandler();
        }
        return self::$_instance;
    }

    public function __construct(){

    }

    /**
     * 获取一个随机数
     * @param $min
     * @param $max
     * @return int
     */
    public function getRandNumber($min, $max){
        return rand($min, $max);

    }

    /**
     * 生成订单号
     * @return string
     */
    public function getOrderNumber(){
        $orderNumMain = date('YmdHis') . rand(10000000, 99999999);
        $orderNumLen = strlen($orderNumMain);
        $orderNumSum = 0;
        for($i = 0; $i < $orderNumLen; $i++){
            $orderNumSum += (int)(substr($orderNumMain, $i, 1));
        }
        $orderNum = $orderNumMain . str_pad((100 - $orderNumSum % 100) % 100, 2, '0', STR_PAD_LEFT);
        return $orderNum;
    }

}