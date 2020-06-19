<?php
/**
 * Date: 2019/06/25
 * Time: 11:41
 */

namespace QK\HaoLiao\Controllers\Expert\Base;

use QK\HaoLiao\Controllers\BaseController;
use QK\HaoLiao\Model\ExpertModel;
use QK\HaoLiao\Model\ExpertExtraModel;

class ExpertController extends BaseController {

    public function stat() {

        $params = $this->checkApiParam(['user_id']);
        $user_id = $params['user_id'];

        $expertModel = new ExpertModel();
        $expertInfo = $expertModel->getExpertInfo($user_id);

        $registerDay = floor((time() - $expertInfo['create_time']) / (24 * 3600));

        $expertExtraModel = new ExpertExtraModel();
        $expertExtraInfo = $expertExtraModel->getExpertExtraInfo($user_id);

        $data = [
            'expert_name' => $expertInfo['expert_name'],
            'register_day' => $registerDay,
            'income' => $expertExtraInfo['income'],
            'publish_resource' => $expertExtraInfo['publish_resource_num'],
            'profit_rate' => $expertExtraInfo['profit_rate'],
        ];

        return $this->responseJson($data);
    }


    /*
    * 修改自动回复内容
    */
    public function replyContent(){
        $params = $this->checkApiParam(['expert_id', 'content']);
        $expertId=$params['expert_id'];
        $expertModel = new ExpertModel();

        $update['reply_content'] = $params['content'];
        $r=$expertModel->updateExpert($expertId, $update);
        if ($r) {
            $this->responseJson([], '操作成功');
        } else {
            $this->responseJsonError(-1, '参数错误');
        }
    }





}
