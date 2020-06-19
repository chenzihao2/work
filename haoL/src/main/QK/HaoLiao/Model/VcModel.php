<?php


namespace QK\HaoLiao\Model;

use QK\HaoLiao\DAL\DALVcBuyConfig;
use QK\HaoLiao\Common\PayParams;

class VcModel extends BaseModel {

    public function __construct() {
        parent::__construct();

    }

    public function editVcBuyConfig($id = '', $data) {

        $upData['money'] = $this->ncPriceYuan2Fen($data['money']);
        $payParams = new PayParams();
        $upData['vc'] = $this->ncPriceCalculate($upData['money'], '*', $payParams->getVcRate());
        $upData['gift_vc'] = $this->ncPriceYuan2Fen($data['giftVc']);
        $upData['tags'] = $data['tags'];
        $upData['apple_product_id'] = $data['apple_product_id'];

        $dalVcBuyConfig = new DALVcBuyConfig($this->_appSetting);

        if (empty($id)) {
            $upData['create_time'] = time();
            $dalVcBuyConfig->createVcBuyConfig($upData);
        } else {
            $upData['modify_time'] = time();
            $dalVcBuyConfig->updateVcBuyConfig($id, $upData);
        }

        return true;
    }

    public function getVcBuyConfigList($startItem = 0 , $perpage = 0) {

        $dalVcBuyConfig = new DALVcBuyConfig($this->_appSetting);
        $list = $dalVcBuyConfig->vcBuyConfigList(['deleted' => 0], ['id', 'money', 'vc', 'gift_vc', 'tags', 'apple_product_id', 'enable'], $startItem, $perpage, ['money' => 'ASC']);
        foreach ($list as &$row) {
            $row['money'] = $this->ncPriceFen2YuanInt($row['money']);
            $row['vc'] = $this->ncPriceFen2YuanInt($row['vc']);
            $row['gift_vc'] = $this->ncPriceFen2YuanInt($row['gift_vc']);
        }
        return $list;
    }

    public function vcBuyConfigDetailById($id, $isYuan = true) {

        $dalVcBuyConfig = new DALVcBuyConfig($this->_appSetting);
        $res = $dalVcBuyConfig->vcBuyConfigDetailById($id, ['id', 'money', 'vc', 'gift_vc', 'tags', 'apple_product_id', 'enable', 'deleted']);
        if (empty($res)) return false;
        if ($isYuan === true) {
            $res['money'] = $this->ncPriceFen2YuanInt($res['money']);
            $res['gift_vc'] = $this->ncPriceFen2YuanInt($res['gift_vc']);
        }

        return $res;
    }

    public function vcBuyConfigEnable($id) {

        $dalVcBuyConfig = new DALVcBuyConfig($this->_appSetting);
        $resetData['enable'] = 0;
        $resetData['modify_time'] = time();
        $dalVcBuyConfig->updateVcBuy(['deleted' => 0], $resetData);

        $upData['enable'] = 1;
        $upData['modify_time'] = time();
        $dalVcBuyConfig->updateVcBuyConfig($id, $upData);

        return true;
    }

    public function vcBuyConfigDelById($id) {

        $upData['deleted'] = 1;
        $upData['modify_time'] = time();

        $dalVcBuyConfig = new DALVcBuyConfig($this->_appSetting);
        $dalVcBuyConfig->updateVcBuyConfig($id, $upData);

        return true;
    }

    public function checkVcBuyConfigOk(&$info) {
        if ($info['deleted'] != 1) {
            return true;
        }
        return false;
    }

    /*
     * 获取单个支付项
     */
    public function vcBuyAmountById($id){
        $data=$this->amountConfig();
        $info=[];
        foreach($data as $v){
            if($id==$v['id']){
                $info=$v;
                break;
            }
        }
        return $info;
    }

    /*
     * h5充值页金额配置
     * $isYuan true:分转元，false不转
     */
    public function amountConfig($isYuan=false){
        $data=[
            0=>[
                'id'=>100,
                'money'=>100000,
                'vc'=>100000,
                'gift_vc'=>20000,
                'enable'=>1,
            ],
            1=>[
                'id'=>101,
                'money'=>200000,
                'vc'=>200000,
                'gift_vc'=>50000,
                'enable'=>1,
            ],
            2=>[
                'id'=>102,
                'money'=>500000,
                'vc'=>500000,
                'gift_vc'=>150000,
                'enable'=>1,
            ],
            3=>[
                'id'=>103,
                'money'=>1000000,
                'vc'=>1000000,
                'gift_vc'=>400000,
                'enable'=>1,
            ],
        ];
        if($isYuan==true){
            foreach($data as &$v){
                $v['money'] = $this->ncPriceFen2YuanInt($v['money']);
                $v['gift_vc'] = $this->ncPriceFen2YuanInt($v['gift_vc']);
                $v['vc'] = $this->ncPriceFen2YuanInt($v['gift_vc']);
            }
        }
        return $data;

    }
}