<?php
/**
 * User: WangHui
 * Date: 2018/5/24
 * Time: 17:52
 */

namespace QK\HaoLiao\Common;


class StringHandler {

    private $_strbase = "Flpvf70CsakVjqgeWUPXQxSyJizmNH6B1u3b8cAEKwTd54nRtZOMDhoG2YLrI";

    /**
     * @var String
     */
    private static $_instance;

    public static function newInstance() {
        if (!(self::$_instance instanceof StringHandler)) {
            self::$_instance = new StringHandler();
        }
        return self::$_instance;
    }

    public function __construct() {

    }

    /**
     * 检查是否为中文字符,
     * @param $str
     * @param $length bool 检查是否为1个汉字
     * @return bool
     */
    public function checkString($str, $length = false) {
        //新疆等少数民族可能有·
        if (strpos($str, '·')) {
            //将·去掉，看看剩下的是不是都是中文
            $str = str_replace("·", '', $str);
            if (preg_match('/^[\x7f-\xff]+$/', $str)) {
                if ($length) {
                    if ($this->checkStringLength($str) == 1) {
                        return true;//全是中文
                    } else {
                        return false;
                    }
                } else {
                    return true;
                }
            } else {
                return false;//不全是中文
            }
        } else {
            if (preg_match('/^[\x7f-\xff]+$/', $str)) {
                if ($length) {
                    if ($this->checkStringLength($str) == 1) {
                        return true;
                    } else {
                        return false;
                    }
                } else {
                    return true;
                }
            } else {
                return false;//不全是中文
            }
        }
    }


    public function checkStringLength($string) {
        return mb_strlen($string);
    }

    /**
     * 传入一个数组，返回key与value的按照逗号分隔的字符串
     * @param $params
     * @return mixed
     */
    public function getDBInsertString($params) {
        $insertKeyString = $insertValueString = "";
        $comma = "";
        foreach ($params as $insert_key => $insert_value) {
            $insertKeyString .= $comma . '`' . $insert_key . '`';
            $insertValueString .= $comma . '\'' . $insert_value . '\'';
            $comma = ', ';
        }

        $array['insert'] = $insertKeyString;
        $array['value'] = $insertValueString;
        return $array;
    }

    /**
     * 根据数组获取数据库更新串
     * @param $params
     * @return bool|string
     */
    public function getDBUpdateString($params) {
        $formatParams = "";
        foreach ($params as $key => $value) {
            $formatParams .= "`$key` =\"$value\",";
        }

        $formatParams = substr($formatParams, 0, -1);
        return $formatParams;
    }

    /**
     * 传入一个数组，返回key与value的按照逗号分隔的字符串(自增或自减)
     * @param $params
     * @return string
     */
    public function getDBIncOrDecString($params) {
        $formatParams = "";
        foreach ($params as $key => $value) {
            $formatParams .= "`$key` =`$key`$value,";
        }
        $updateString = substr($formatParams, 0, -1);
        return $updateString;
    }

    /**
     * 根据唯一字段对两个二维数组取差集
     * - 去除$arr1 中 存在和$arr2相同的部分之后的内容
     * @param        $arr1
     * @param        $arr2
     * @param string $pk
     * @return array
     */
    public function getDiffArrayByPk($arr1, $arr2, $pk = 'expert_id'){
        try{
            $res = [];
            foreach($arr2 as $item) $tmpArr[$item[$pk]] = $item;
            foreach($arr1 as $v) if(!isset($tmpArr[$v[$pk]])) $res[] = $v;
            return $res;
        } catch(\Exception $exception) {
            return $arr1;
        }
    }

    /**
     * 获取唯一邀请码
     * @return string
     */
    public function getInvitationOnlyCode() {
        $charid = strtoupper(md5(uniqid(rand(), true)));
        return substr($charid, 0, 8) . substr($charid, 8, 4) . substr($charid, 12, 4) . substr($charid, 16, 4) . substr($charid, 20, 12);
    }

    /**
     * 字符串过滤
     * @param $string
     * @return string
     */
    public function stringExecute($string) {
        if (get_magic_quotes_gpc()) {
            return $string;
        } else {
            return addslashes($string);
        }
    }

    /**
     * 加密ID
     * @param $nums
     * @return string
     */
    public function encode($nums) {
        $strbase = $this->_strbase;
        $length = 9;
        $key = 2543.5415412812;
        $codelen  = substr($strbase, 0, $length);
        $codenums = substr($strbase, $length, 10);
        $codeext  = substr($strbase, $length + 10);


        $rtn = "";
        $numslen = strlen($nums);
        $begin   = substr($codelen, $numslen - 1,1);

        $extlen = $length - $numslen - 1;
        $temp = str_replace('.', '', $nums / $key);
        $temp = substr($temp,-$extlen);

        $arrextTemp = str_split($codeext);
        $arrext = str_split($temp);
        foreach ($arrext as $v) {
            $rtn .= $arrextTemp[$v];
        }

        $arrnumsTemp = str_split($codenums);
        $arrnums = str_split($nums);
        foreach ($arrnums as $v) {
            $rtn .= $arrnumsTemp[$v];
        }
        return $begin.$rtn;
    }

    /**
     * 解密ID
     * @param $code
     * @return string
     */
    public function decode($code) {
        $strbase = $this->_strbase;
        $length = 9;
        $key = 2543.5415412812;
        $codelen  = substr($strbase, 0, $length);
        $codenums = substr($strbase, $length, 10);
        $codeext  = substr($strbase, $length + 10);

        $begin = substr($code, 0, 1);
        $rtn = '';
        $len = strpos($codelen,$begin);
        if($len !== false) {
            $len ++;
            $arrnums = str_split(substr($code, -$len));
            foreach ($arrnums as $v) {
                $rtn .= strpos($codenums, $v);
            }
        }

        return $rtn;
    }

}
