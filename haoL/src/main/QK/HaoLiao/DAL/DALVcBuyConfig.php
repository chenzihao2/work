<?php


namespace QK\HaoLiao\DAL;


class DALVcBuyConfig extends BaseDAL {

    protected $_table = 'hl_vc_buy_config';

    public function createVcBuyConfig($data) {
        return $this->insertData($data, $this->_table);
    }

    public function updateVcBuyConfig($id, $data) {
        return $this->updateData($id, $data, $this->_table);
    }

    public function updateVcBuy($condition, $data) {
        return $this->updateByCondition($condition, $data, $this->_table);
    }

    public function vcBuyConfigList($condition = [], $fields = [], $offset = 0, $limit = 0, $orderBy = []) {
        return $this->select($this->_table, $condition, $fields, $offset, $limit, $orderBy);
    }

    public function vcBuyConfigDetailById($id, $fields = []) {
        return $this->get($this->_table, ['id' => $id], $fields);
    }

}