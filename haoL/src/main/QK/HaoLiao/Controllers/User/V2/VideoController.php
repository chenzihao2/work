<?php

namespace QK\HaoLiao\Controllers\User\V2;

use QK\HaoLiao\Controllers\User\Base\VideoController as video;
use QK\HaoLiao\Model\CategoryModel;
use QK\HaoLiao\Model\VideoModel;

class VideoController extends video{

    public function videoCategory() {
        $CategoryModel = new CategoryModel();
        $result = $CategoryModel->getVideoCategory();
        $this->responseJson($result);
    }

    public function videoList() {
        $params = $this->checkApiParam([], ['cid' => 0, 'page' => 1, 'page_num' => 20, 'user_id' => 0]);
        $video_model = new VideoModel();
        $result = $video_model->getVideoByCategory($params['cid'], $params['page'], $params['page_num'], $params['user_id']);
        $this->responseJson($result);
    }

    public function viewVideo() {
        $params = $this->checkApiParam(['id']);
        $video_id = $params['id'];
        $video_model = new VideoModel();
        $video_model->viewVideo($video_id);
        $this->responseJson();
    }

    public function praiseVideo() {
        $this->checkToken();
        $params = $this->checkApiParam(['id']);
        $user_id = $this->getCurrentUserId();
	//$user_id = 1;
	$video_id = $params['id'];
        $video_model = new VideoModel();
        $video_model->attentVideo($user_id, $video_id);
        $this->responseJson([1]);
    }

    public function collectVideo() {
        $this->checkToken();
        $params = $this->checkApiParam(['id']);
        $user_id = $this->getCurrentUserId();
	//$user_id = 1;
        $video_id = $params['id'];
        $video_model = new VideoModel();
        $video_model->attentVideo($user_id, $video_id, 'collect');
        $this->responseJson([1]);
    }

    public function collectList() {
        $this->checkToken();
        $user_id = $this->getCurrentUserId();
	//$user_id = 1;
        $params = $this->checkApiParam([], ['page' => 1, 'page_num' => 20]);
        $video_model = new VideoModel();
        $result = $video_model->collectList($user_id, $params['page'], $params['page_num']);
        $this->responseJson($result);
    }

}
