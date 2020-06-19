<?php
/**
 * User: zyj
 * Date: 2019/9/3
 *
 */

namespace QK\HaoLiao\Controllers\Console\Base;


use QK\HaoLiao\Model\VideoModel;
use QK\CloudStorage\CloudStorageFactory;
use QK\HaoLiao\Controllers\Expert\ExpertController;
use QK\WSF\Settings\AppSetting;
use Upload\File;
use Upload\Storage\FileSystem;
use Qiniu\Auth;

class VideoController extends ExpertController {
    private $auth;
    public function __construct(AppSetting $appSetting) {
        $this->setTokenCheck(false);

        parent::__construct($appSetting);
    }
    /*
     * 视频列表
     */

    public function getVideoList(){
        $param = $this->checkApiParam([], ['id' => 0,'title'=>'','cid'=>'','start_time'=>0,'end_time'=>'', 'page' => 1, 'pagesize' => 20]);
        //$query = json_decode($param['query'], true);


        $condition = array();
        if (!empty($param['id'])) {
            $condition['a.id'] = $param['id'];
        }
        if (!empty($param['title'])) {
            $condition['a.title'] = ['like', '%'.trim($param['title']).'%'];
        }
        if (!empty($param['cid'])) {
            $condition['a.cid'] =  ['=',$param['cid']];
        }
        if (!empty($param['start_time'])) {
            $start_time=date('Y-m-d H:i:s',bcdiv($param['start_time'], 1000));
            $condition['a.create_time'] = ['>', $start_time];
        }

        if (!empty($param['end_time'])) {
            $end_time=date('Y-m-d H:i:s',bcdiv($param['end_time'], 1000));
            $condition['a.create_time'] = ['<=', $end_time];
        }
        $condition['a.deleted'] = ['=', 0];

        $page = intval($param['page']);
        $pagesize = intval($param['pagesize']);
        $orderBy = ['a.create_time' => 'DESC'];
        $video=new VideoModel();
        $list=$video->getVideoList($condition, array(), $page, $pagesize, $orderBy);

        $this->responseJson($list);

    }
    /*
     * 添加/修改视频
     */
    public function editVideo(){
        $param = $this->checkApiParam(['title','cid','image','video'],['id'=>0,'money'=>0,'is_pay'=>0, 'comment' => 1]);

        $VideoModel = new VideoModel();
        //创建
        if(!$param['id']){
            unset($param['id']);
            $res=$VideoModel->insert($param);
        }else{
            $res=$VideoModel->update($param['id'],$param);
        }
        $this->responseJson();
    }


    /*
     * 删除视频
     */
    public function delVideo(){
        $param = $this->checkApiParam(['id'],[]);
        $VideoModel = new VideoModel();
        $res=$VideoModel->del($param['id']);
        $this->responseJson();
    }

    /*
     * 切换视频状态
     */
    public function changeVideoStatus(){
        $param = $this->checkApiParam(['id','status'],[]);
        $VideoModel = new VideoModel();
        $res=$VideoModel->changeStatus($param['id'],$param['status']);
        if(!$res){
            $this->responseJsonError(1000, '意外错误');
            return;
        }
        $this->responseJson();

    }


    /*
     * 获取视频信息
     */
    public function videoInfo(){
        $param = $this->checkApiParam(['id'],[]);
        $VideoModel = new VideoModel();
        $res=$VideoModel->getVideoInfo($param['id']);
        $this->responseJson($res);
    }





    /**
     * 获取七牛token
     */
    public function getfileUploadToken() {

        $param = $this->checkApiParam([],['where' => 6]);
        $where = $param['where'];
        $wheres = 'QINiu-PATH:' . $where;
        //获取今日目录
        $prefix = $this->_appSetting->getConstantSetting($wheres);
        $path = $this->getPath($prefix);
        $p=$this->_appSetting->getConstantSetting('STATIC_URL') . $prefix . "/" . $path . "/";

        $qiNiuPublicKey = $this->_appSetting->getConstantSetting('QiNiu-PUBLIC-KEY');;
        $qiNiuPrivateKey = $this->_appSetting->getConstantSetting('QiNiu-PRIVATE-KEY');
        $qiNiuObj = new Auth($qiNiuPublicKey, $qiNiuPrivateKey);
        $qiNiuBucket = $this->_appSetting->getConstantSetting('QiNiu-BUCKET');
        $token = $qiNiuObj->uploadToken($qiNiuBucket);
        $data['path']=$p;
        $data['token']=$token;

        $this->responseJson($data);
    }

    /**
     * 获取今日目录
     * @param $prefix
     * @return string
     */
    private function getPath($prefix) {
        $time = time();
        $monthPathString = $this->_appSetting->getConstantSetting('FILE-UPLOAD') . "/" . $prefix . "/" . date('Ym', $time);
        $dayPathString = $monthPathString . "/" . date("d", $time);
        $onlinePath = date("Ym", $time) . "/" . date("d", $time);
        return $onlinePath;
    }


}
