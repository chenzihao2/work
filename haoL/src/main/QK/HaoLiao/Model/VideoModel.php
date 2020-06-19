<?php
/**
 * User: zyj
 * Date: 2019/09/02
 * Time: 11:42
 */

namespace QK\HaoLiao\Model;

use \QK\HaoLiao\DAL\DALVideo;
use QK\HaoLiao\Common\StringHandler;
use QK\HaoLiao\DAL\DALOrder;
class VideoModel extends BaseModel {

    private $_video;
    private $_otherRedisKeyManageModel;

    public function __construct() {
        parent::__construct();
        $this->_video = new DALVideo($this->_appSetting);
//        $this->_otherRedisKeyManageModel = new RedisKeyManageModel('other');
    }
    //获取分类列表
    public function lists($condition = '', $fields = '', $order = '') {
        //$redisModel = new RedisModel('other');
        //$list = $redisModel->redisGet(OTHER_BANNER_LIST, true);
        //if (!$list) {
            $list = $this->_video->getVideoList($condition, $fields, $order);
            //$redisModel->redisSet(OTHER_BANNER_LIST, $list);
        //}
        return $list;
    }



    public function getVideoList($condition = array(), $fields = array(), $page = 0, $pageSize = 20, $orderBy = array()) {
        $list = $this->_video->getVideoList($condition, $fields, $page, $pageSize, $orderBy);
		foreach($list as &$v){
			$v['money']=intval($this->ncPriceFen2Yuan($v['money']));
		}
        if($page == 0){
            $total = $this->_video->getNewsTotal([]);
        }else {
            $total = $this->_video->getNewsTotal($condition);
        }
        return array(
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'pagesize' => $pageSize
        );
    }









    //添加
    public function insert($params) {

        $insertData['title'] = StringHandler::newInstance()->stringExecute($params['title']);
        $insertData['img_url'] = StringHandler::newInstance()->stringExecute($params['image']);
        $insertData['cid'] = intval($params['cid']);
        $insertData['video_url'] = $params['video'];
		$insertData['money'] = $this->ncPriceYuan2Fen($params['money']);
        $insertData['is_pay'] = $params['is_pay'];
        $nowtime =time();
        $insertData['create_time'] =$nowtime;
        $insertData['modify_time'] = $nowtime;

        // 删除缓存
       // $this->_otherRedisKeyManageModel->delBannerList();
        return $this->_video->videoInsert($insertData);
    }


    //修改视频
    public function update($id, $params) {

        $id = intval($id);
        $data['title'] = StringHandler::newInstance()->stringExecute($params['title']);
        $data['img_url'] = StringHandler::newInstance()->stringExecute($params['image']);
        $data['cid'] = intval($params['cid']);
        $data['video_url'] = $params['video'];
		$data['money'] = $this->ncPriceYuan2Fen($params['money']);
        $data['is_pay'] = $params['is_pay'];
        $nowtime =time();
        $data['modify_time'] = $nowtime;

        // 删除缓存
        //$this->_otherRedisKeyManageModel->delBannerList();
        return $this->_video->updateVideo($id, $data);
    }
    //删除视频
    public function del($id) {
        $upData['deleted'] = 1;
        $upData['modify_time'] = time();
        // 删除缓存
       // $this->_otherRedisKeyManageModel->delBannerList();
        return $this->_video->updateVideo($id, $upData);
    }


    /**
     * 获取视频信息
     * @param $d
     */
    public function getVideoInfo($id) {
        $getVideoInfo = $this->_video->getVideoInfo($id);
		$getVideoInfo['money']=intval($this->ncPriceFen2Yuan($getVideoInfo['money']));
        return $getVideoInfo;
    }


    /*
     * 切换视频状态
     */

    public function changeStatus($id,$status){
        $upData['status'] = $status;
        $upData['modify_time'] = time();
        // 删除缓存
        // $this->_otherRedisKeyManageModel->delBannerList();
        return $this->_video->updateVideo($id, $upData);
    }


    //排序
    public function sort($id, $sort_type, $type = 1) {
                $id = intval($id);
                $sort = $this->_Category->CategoryInfo('deleted = 0 AND id = ' . $id, 'id, sort');

                if (empty($sort)) {
                    return false;
                }
                $condition = 'deleted = 0 AND type = '.$type;
                if ($sort_type == 1) {  // 上移
                    $condition .= ' AND sort < ' . $sort['sort'];
                    $order = 'sort desc';
                } else {  // 下移
                    $condition .= ' AND sort > ' . $sort['sort'];
                    $order = 'sort asc';
                }

                $other = $this->_Category->listsCategory($condition, 'id, sort', $order, 0, 1);

                if (!$other) {
                    return false;
                }
                $nowtime = time();
                //两个分类排序互换
                $this->_Category->updateCategory($sort['id'], ['sort' => $other[0]['sort'], 'modify_time' => $nowtime]);
                $this->_Category->updateCategory($other[0]['id'], ['sort' => $sort['sort'], 'modify_time' => $nowtime]);
                // 删除缓存
                //$this->_otherRedisKeyManageModel->delBannerList();
                return true;
    }

    public function getVideoByCategory($cid, $page, $page_num, $user_id = 0) {
        if ($cid) {
            $condition['cid'] = $cid;
        }
        $condition['status'] = 1;
        $condition['page'] = $page;
        $condition['page_num'] = $page_num;

        $video_info = $this->_video->getVideoByCategory($condition);
		$DALOrder=new DALOrder($this->_appSetting);
        foreach ($video_info['data'] as $k => $v) {
            $video_info['data'][$k]['is_fabulous'] = $video_info['data'][$k]['is_collect'] = 0;
            if ($user_id) {
                $attent_info = $this->_video->getAttentInfo($user_id, $v['id']);
                if ($attent_info['fabulous']) {
                    $video_info['data'][$k]['is_fabulous'] = 1;
                }
                if ($attent_info['collect']) {
                    $video_info['data'][$k]['is_collect'] = 1;
                }
            }
			$video_info['data'][$k]['money']=intval($this->ncPriceFen2Yuan($v['money']));
			//付费视频，检查是否已购买
			$video_info['data'][$k]['is_buy']=0;
            if($v['is_pay']&&$user_id){
                $res=$DALOrder->getOrderByUserId($user_id,$v['id'],4);
                if($res){
                    $video_info['data'][$k]['is_buy']=1;
                }
            }
        }
        return $video_info;
    } 

    public function viewVideo($video_id) {
        return $this->_video->columnAddSub($video_id, 'views', 'add');
    }

    public function attentVideo($user_id, $video_id, $action = 'fabulous') {
        $info = $this->_video->getAttentInfo($user_id, $video_id);
        if (!$info) {
		$this->_video->columnAddSub($video_id, $action, 'add');
            return $this->_video->addAttention($user_id, $video_id, $action);
        }
        $update_data = [$action => $info[$action]];
        $this->_video->updateAttent($user_id, $video_id, $update_data);
        if ($info[$action]) {
            $this->_video->columnAddSub($video_id, $action, 'sub');
        } else {
            $this->_video->columnAddSub($video_id, $action, 'add');
        }
		return;
    }


    public function assembleData($data, $user_id = 0) {
		$DALOrder=new DALOrder($this->_appSetting);
        foreach ($data as $k => $v) {
            $data[$k]['is_fabulous'] = $data[$k]['is_collect'] = 0;
            if ($user_id) {
                $attent_info = $this->_video->getAttentInfo($user_id, $v['id']);
                if ($attent_info['fabulous']) {
                    $data[$k]['is_fabulous'] = 1;
                }
                if ($attent_info['collect']) {
                    $data[$k]['is_collect'] = 1;
                }
            }
			$data[$k]['money']=intval($this->ncPriceFen2Yuan($v['money']));
			//付费视频，检查是否已购买
            $data[$k]['is_buy']=0;
            if($v['is_pay']&&$user_id){
                $res=$DALOrder->getOrderByUserId($user_id,$v['id'],4);
                if($res){
                    $data[$k]['is_buy']=1;
                }
            }
        }

        return $data;
    }

    public function collectList($user_id, $page, $page_num) {
       $videos = $this->_video->getVideoByUserId($user_id, $page, $page_num);
       if (empty($videos['data'])) {
           return $videos;
       }
       $videos['data'] = $this->assembleData($videos['data'], $user_id);
       return $videos;
    }

}
