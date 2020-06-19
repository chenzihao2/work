<?php
/**
 * redisKey管理
 * User: WangHui
 * Date: 2018/11/6
 * Time: 下午3:37
 */

namespace QK\HaoLiao\Model;

class RedisKeyManageModel extends BaseModel {
    private $_redisModel;

    public function __construct($selectDB) {
        parent::__construct();
        if ($selectDB != null) {
            $this->_redisModel = new RedisModel($selectDB);
        }
    }

    /**
     * 删除料缓存
     * @param $resourceId
     * @param null $expertId
     */
    public function delResourceKey($resourceId, $expertId = null) {
        //料信息
        $keys[] = RESOURCE_INFO . $resourceId;
        //料扩展信息
        $keys[] = RESOURCE_EXTRA_INFO . $resourceId;
        //料内容详情
        $keys[] = RESOURCE_DETAIL_INFO . $resourceId;
        if ($expertId) {
            //用户访问专家料列表
            $keys[] = RESOURCE_LIST . 1 . ':' . $expertId;
            $keys[] = RESOURCE_LIST . 2 . ':' . $expertId;
            //专家访问专家料列表
            $keys[] = RESOURCE_EXPERT_LIST . 1 . ':' . $expertId;
            $keys[] = RESOURCE_EXPERT_LIST . 2 . ':' . $expertId;
            // 资源列表二
            $redisManageModel = new RedisManageModel('resource');
            $redisManageModel->delList(RESOURCE_EXPERT_LIST_V2 . $expertId);
        }
        $keys[] = RESOURCE_RECOMMEND_LIST . 1;
        $keys[] = RESOURCE_RECOMMEND_LIST . 2;
        $this->_redisModel->redisDel($keys);
    }

    /**
     * 删除专家缓存
     * @param $expertId
     */
    public function delExpertKey($expertId) {
        //首页专家列表key
        $keys[] = EXPERT_LIST . 1;
        $keys[] = EXPERT_LIST . 2;
        $keys[] = EXPERT_LIST . 3;
        //专家主体信息
        $keys[] = EXPERT_INFO . $expertId;
        //专家订阅价格信息
        $keys[] = EXPERT_SUBSCRIBE_INFO . $expertId;
        //专家提现费率信息
        $keys[] = EXPERT_RATE_INFO . $expertId;
        //专家推荐列表
        $keys[] = EXPERT_RECOMMOND_TOP;
        $keys[] = RESOURCE_RECOMMEND_LIST . 1;
        $keys[] = RESOURCE_RECOMMEND_LIST . 2;
        // 全部专家列表
        $redisManageModel = new RedisManageModel('expert');
        $redisManageModel->delList(EXPERT_ALL_LIST);
        $redisManageModel->delList(EXPERT_ALL_LIST_V2);
	$redisManageModel->delList(EXPERT_BET_RECORD);
        $this->_redisModel->redisDel($keys);
    }
    /**
     * 删除专家料信息缓存
     * @param $expertId
     */
    public function delExpertResourceKey($expertId) {
        //用户访问专家料列表
        $keys[] = RESOURCE_LIST . 1 . ':' . $expertId;
        $keys[] = RESOURCE_LIST . 2 . ':' . $expertId;
        //专家访问专家料列
        $keys[] = RESOURCE_EXPERT_LIST . 1 . ':' . $expertId;
        $keys[] = RESOURCE_EXPERT_LIST . 2 . ':' . $expertId;
        // 资源列表二
        $redisManageModel = new RedisManageModel('resource');
        $redisManageModel->delList(RESOURCE_EXPERT_LIST_V2 . $expertId);
        $this->_redisModel->redisDel($keys);
    }


    /**
     * 专家战绩删除
     * @param $expertId
     */
    public function delExpertStat($expertId) {
        //专家近十场战绩
        $keys[] = BETRECORD_STAT_NEAR_TEN_SCORE . $expertId;
        $this->_redisModel->redisDel($keys);
        //专家近十场战绩列表
        $tenKeyPre = BETRECORD_STAT_NEAR_TEN_RECORD . $expertId;
        $tenKeys = $this->_redisModel->redisKeys($tenKeyPre . '*');
        $this->_redisModel->redisDel($tenKeys);

    }

    /**
     * banner列表删除
     */
    public function delBannerList() {
        $this->_redisModel->redisDel(OTHER_BANNER_LIST);
    }
}
