<?php
/**
 * Redis处理类
 * User: YangChao
 * Date: 2018/10/9
 */

namespace QK\HaoLiao\Model;


use QK\WSF\Core\Model;
use QK\WSF\DB\DBHandler;

class RedisModel extends Model {

    protected $_dbHandler;
    protected $_redisDB;

    public function __construct($selectDB = null) {
        parent::__construct();

        if ($selectDB != null) {
            $this->_dbHandler = DBHandler::newInstance();
            $this->_redisDB = $this->_dbHandler->createReidsBySetting($this->_appSetting->getRedisSetting($selectDB));
        }
    }


    /**
     * redis 连接
     * @return \Redis
     */
    public function dbRedis($selectDB = null) {

        if ($selectDB === null && $this->_redisDB) {
            return $this->_redisDB;
        }

        $this->_dbHandler = DBHandler::newInstance();

        $this->_redisDB = $this->_dbHandler->createReidsBySetting($this->_appSetting->getRedisSetting($selectDB));

    }

    /**
     * 设置key->value
     * @param $key
     * @param $value
     * @param int $timeOut
     * @return bool
     */
    public function redisSet($key, $value, $timeOut = 3600 * 24) {
        if ($value === null) {
            return false;
        }

        if (is_array($value)) {
            $value = json_encode($value, true);
        }

        $retRes = $this->_redisDB->set($key, $value);

        if ($timeOut > 0) {
            $this->_redisDB->expire($key, $timeOut);
        }
        return $retRes;
    }

    /**
     * 获取key->value
     * @param $key
     * @param bool $isobj
     * @return bool|mixed|null|string
     */
    public function redisGet($key, $isobj = false) {
        $value = $this->_redisDB->get($key);
        return $value !== null ? ($isobj ? json_decode($value, true) : $value) : null;
    }

    /**
     * 删除缓存key
     * @param $key
     * @return int
     */
    public function redisDel($key) {
        if (is_array($key)) {
            foreach ($key as $redisKey) {
                $this->_redisDB->del($redisKey);
            }
        } else {
            return $this->_redisDB->del($key);
        }
    }

    /**
     * 设置自增
     * @param $key
     * @param int $timeOut
     * @return int
     */
    public function redisIncr($key, $timeOut = 0) {
        $retRes = $this->_redisDB->incr($key);
        if ($timeOut > 0) {
            $this->_redisDB->expire($key, $timeOut);
        }
        return $retRes;
    }

    /**
     * 设置自减
     * @param $key
     * @param int $timeOut
     * @return int
     */
    public function redisDecr($key, $timeOut = 0) {
        $retRes = $this->_redisDB->decr($key);
        if ($timeOut > 0) {
            $this->_redisDB->expire($key, $timeOut);
        }
        return $retRes;
    }

    /**
     * 设置过期时间点
     * @param $key
     * @param $timestamp
     * @return bool
     */
    public function redisExpireAt($key, $timestamp) {
        $retRes = $this->_redisDB->expireAt($key, $timestamp);
        return $retRes;
    }

    /**
     * 获取剩余生存时间
     * @param $key
     * @return int
     */
    public function redisTtl($key) {
        $retRes = $this->_redisDB->ttl($key);
        return $retRes;
    }


    /**
     * 修改key名
     * @param $key
     * @param $newKey
     * @return bool
     */
    public function redisRename($key, $newKey) {
        if (empty($key) || empty($newKey) || ($key == $newKey)) return false;
        $retRes = $this->_redisDB->rename($key, $newKey);
        return $retRes;
    }

    /**
     * 返回列表 key 中指定区间内的元素，区间以偏移量 start 和 stop 指定。
     * @param $key
     * @param $start
     * @param $end
     * @return array
     */
    public function redisLrange($key, $start, $end) {
        $retRes = $this->_redisDB->lrange($key, $start, $end);
        return $retRes;
    }

    /**
     * 将一个或多个值 value 插入到列表 key 的表头
     * @param $key
     * @param $value
     * @param int $timeOut
     * @return string
     */
    public function redisLpush($key, $value, $timeOut = 3600) {
        if (empty($value)) return '';
        if (is_array($value)) {
            foreach ($value as $val) {
                $this->_redisDB->lpush($key, $val);
            }
        } else {
            $this->_redisDB->lpush($key, $value);
        }

        if ($timeOut > 0) {
            $this->_redisDB->expire($key, $timeOut);
        }
    }

    /**
     * 将一个或多个值 value 插入到列表 key 的表尾(最右边)
     * @param $key
     * @param $value
     * @param int $timeOut
     * @return string
     */
    public function redisRpush($key, $value, $timeOut = 3600) {
        if (empty($value)) return '';
        if (is_array($value)) {
            foreach ($value as $val) {
                $this->_redisDB->rpush($key, $val);
            }
        } else {
            $this->_redisDB->rpush($key, $value);
        }

        if ($timeOut > 0) {
            $this->_redisDB->expire($key, $timeOut);
        }
    }


    /**
     * 将一个或多个值 value 插入到列表 key 的表头
     * @param $key
     * @return string
     */
    public function redisRpop($key) {
        return $this->_redisDB->rPop($key);
    }


    /**
     * 根据参数 count 的值，移除列表中与参数 value 相等的元素。
     * @param $key
     * @param $value
     * @param int $count
     * @return int|string
     */
    public function redisLrem($key, $value, $count = 0) {
        if (empty($value)) return '';
        $retRes = $this->_redisDB->lrem($key, $value, $count);
        return $retRes;
    }


    /**
     * 将一个或多个 member 元素加入到集合 key 当中，已经存在于集合的 member 元素将被忽略。
     * @param $key
     * @param $value
     * @param int $timeOut
     * @return int
     */
    public function redisSadd($key, $value, $timeOut = 3600) {
        $retRes = $this->_redisDB->sadd($key, $value);
        if ($timeOut > 0) {
            $this->_redisDB->expire($key, $timeOut);
        }
        return $retRes;
    }

    /**
     * 判断 value 元素是否集合 key 的成员
     * @param $key
     * @param $value
     * @return bool
     */
    public function redisSismember($key, $value) {
        $retRes = $this->_redisDB->sismember($key, $value);
        return $retRes;
    }

    /**
     * 移除集合key中的值value
     * @param $key
     * @param $value
     * @return bool
     */
    public function redisSRem($key, $value) {
        $retRes = $this->_redisDB->sRem($key, $value);
        return $retRes;
    }

    /**
     * 检查给定 key 是否存在
     * @param $key
     * @return bool
     */
    public function redisExists($key) {
        $retRes = $this->_redisDB->exists($key);
        return $retRes;
    }

    /**
     * 返回哈希表 key 中给定域 field 的值。
     * @param $hkey
     * @param $key
     * @param bool $obj
     * @return mixed|null|string
     */
    public function redisGetHashList($hkey, $key, $obj = false) {
        $r = $this->_redisDB->hGet($hkey, $key);

        if ($r !== false) {
            return $obj ? json_decode($r, true) : $r;
        }

        return null;
    }

    /**
     * 将哈希表 key 中的域 field 的值设为 value 。
     * @param $hkey
     * @param $key
     * @param $value
     * @param int $timeOut
     */
    public function redisSetHashList($hkey, $key, $value, $timeOut = 3600) {
        if (is_array($value)) {
            $value = json_encode($value);
        }

        $this->_redisDB->hSet($hkey, $key, $value);
        $this->_redisDB->expire($hkey, $timeOut);
    }

    /**
     * 为哈希表 key 中的指定字段的整数值加上增量 increment 。
     * @param $hkey
     * @param $key
     * @param $value
     */
    public function redisHincrby($hkey, $key, $value){
        $this->_redisDB->hIncrBy($hkey, $key, $value);
    }

    /**
     * 获取所有哈希表中的字段
     * @param $hkey
     * @return array
     */
    public function redisHkeys($hkey){
        return $this->_redisDB->hKeys($hkey);
    }

    /**
     * 删除哈希表字段
     * @param $hkey
     * @param $key
     * @return int
     */
    public function redisHdel($hkey, $key){
        return $this->_redisDB->hDel($hkey, $key);
    }

    /**
     * 模糊删除key
     * @param $keys_reg
     */
    public function redisDelLike($keys_reg) {
        $keys_list = $this->_redisDB->keys($keys_reg);

        if (!empty($keys_list) && is_array($keys_list)) {
            return $this->_redisDB->delete($keys_list);
        }
    }

    /**
     * 将一个或多个 value 元素及其 score 值加入到有序集 key 当中。
     * @param $key
     * @param $score
     * @param $value
     * @param int $timeOut
     * @return int
     */
    public function redisZAdd($key, $score, $value, $timeOut = 0) {
        $retRes = $this->_redisDB->zAdd($key, $score, $value);
        if ($timeOut > 0) {
            $this->_redisDB->expire($key, $timeOut);
        }
        return $retRes;
    }

    /**
     * 删除有序集合的value
     * @param $key
     * @param $value
     * @return int
     */
    public function redisZDel($key, $value) {
        $retRes = $this->_redisDB->zDelete($key, $value);
        return $retRes;
    }

    /**
     * 为有序集 key 的成员 member 的 score 值加上增量 value
     * @param $key
     * @param $value
     * @param $member
     * @param int $timeOut
     * @return float
     */
    public function redisZIncrBy($key, $value, $member, $timeOut = 0) {
        $retRes = $this->_redisDB->zIncrBy($key, $value, $member);
        if ($timeOut > 0) {
            $this->_redisDB->expire($key, $timeOut);
        }
        return $retRes;
    }

    /**
     * 返回有序集 key 中，指定区间内的成员  分值倒序
     * @param $key
     * @param $start
     * @param $stop
     * @param null $withscore
     * @return array
     */
    public function redisZRevRange($key, $start, $stop, $withscore = null) {
        $retRes = $this->_redisDB->zRevRange($key, $start, $stop, $withscore);
        return $retRes;
    }

    /**
     * 返回有序集 key 中，指定区间内的成员  分值正序
     * @param $key
     * @param $start
     * @param $stop
     * @param null $withscore
     * @return array
     */
    public function redisZRange($key, $start, $stop, $withscore = null) {
        $retRes = $this->_redisDB->zRange($key, $start, $stop, $withscore);
        return $retRes;
    }

    /**
     * 返回有序集合指定成员的键值
     * @param $key
     * @param $member
     * @return int
     */
    public function redisZRank($key, $member) {
        $retRes = $this->_redisDB->zRank($key, $member);
        return $retRes;
    }
    /**
     * 删除有序集合指定成员
     * @param $key
     * @param $member
     * @return int
     */
    public function redisZRem($key, $member) {
        $retRes = $this->_redisDB->zRem($key, $member);
        return $retRes;
    }

    /**
     * 根据分值范围返回有序集 key 中，指定区间内的成员
     * @param $key
     * @param $min
     * @param $max
     * @return array
     */
    public function redisZRangeByScore($key, $min, $max) {
        $retRes = $this->_redisDB->zRangeByScore($key, $min, $max);
        return $retRes;
    }

    /**
     * 返回有续集和制定成员的分数值
     * @param $key
     * @param $member
     * @return float
     */
    public function redisZScore($key, $member) {
        $retRes = $this->_redisDB->zScore($key, $member);
        return $retRes;
    }

    /**
     * 查询key是否存在
     * @param $key
     * @return array
     */
    public function redisKeys($key) {
        return $this->_redisDB->keys($key);
    }


    /**
     * 更新过期时间
     */
    public function redisUpdateExpire($key, $timeOut = 3600 * 24) {
       return  $this->_redisDB->expire($key, $timeOut);
    }

}
