<?php
/**
 * User: WangHui
 * Date: 2018/9/29
 * Time: 16:37
 */

namespace QK\HaoLiao\DAL;


class DALLogUserLogin extends BaseDAL
{
	protected $_table = 'hl_log_user_login';

    /**
     * 登录日志入库
     * @param $params
     */
	public function loginLog($params) {
		$this->insertData($params, $this->_table);
	}

}