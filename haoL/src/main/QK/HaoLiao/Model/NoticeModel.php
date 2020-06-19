<?php
/**
 * 资讯处理模块
 * User: zyj
 * Date: 2020/03/13
 * Time: 10:10
 */

namespace QK\HaoLiao\Model;


use QK\HaoLiao\Common\StringHandler;
use QK\HaoLiao\DAL\DALNews;
use QK\CloudStorage\CloudStorageFactory;
use QK\HaoLiao\DAL\DALNotice;
use QK\WSF\Settings\AppSetting;
use QK\HaoLiao\Common\CommonHandler;
use Qiniu\Auth;
use Qiniu\Storage\BucketManager;
use QK\HaoLiao\DAL\DALOrder;
class NoticeModel extends BaseModel {

    private $_dalNotice;
    public function __construct() {
        parent::__construct();
        $this->_dalNotice = new DALNotice($this->_appSetting);
        $this->common = new CommonHandler();
    }

    /**
     * 创建通知
     * @param $params
     */
    public function createNotice($params) {
        $notice['title'] = StringHandler::newInstance()->stringExecute($params['title']);
        $notice['content'] = StringHandler::newInstance()->stringExecute($params['content']);
        $notice['remarks'] = StringHandler::newInstance()->stringExecute($params['remarks']);
        $notice['rid'] = $params['rid'];
        $notice['expert_id'] = $params['expert_id'];
        $notice['complete_time'] = $params['complete_time'];
        $notice['status'] = $params['status'];
        $notice['ctime'] = $params['ctime'];
        $notice['utime'] = $params['ctime'];

        $this->_dalNotice->createNotice($notice);
        $id = $this->_dalNotice->getInsertId();
        return $id;
    }

    /**
     * 更新通知
     * @param $newsId
     * @param $data
     */
    public function updateNotice($Id, $data) {
        $data['utime'] = date('Y-m-d H:i:s');
        $res = $this->_dalNotice->updateNotice($Id, $data);
        return $res;
    }





    /**
     * 通知详情
     * @param $nid
     * @param $data
     */

    public function findNotice($nid){
        $res = $this->_dalNotice->findNotice($nid);
        return $res;
    }


    /**
     * 获取通知列表
     * @return mixed
     */
    public function getNoticeList($condition = array(), $fields = array(), $page = 0, $pageSize = 20, $orderBy = array(),$user_id = 0) {
        $list = $this->_dalNotice->getNoticeList($condition, $fields, $page, $pageSize, $orderBy);

        if($page == 0){
            $total = $this->_dalNotice->getNoticeTotal([]);
        }else {
            $total = $this->_dalNotice->getNoticeTotal($condition);
        }

        return array(
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'pagesize' => $pageSize
        );
    }


}
