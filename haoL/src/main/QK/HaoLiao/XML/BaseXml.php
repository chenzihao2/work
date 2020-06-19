<?php
/**
 * User: WangHui
 * Date: 2018/7/23
 * Time: 17:38
 */

namespace QK\HaoLiao\XML;


class BaseXml {


	protected $_ProgramCode;

	public function __construct() {
		$this->_ProgramCode = $this->getProgramCode();
	}

	/**
	 * 获取当前调用程序的code值
	 * @return string
	 */
	public function getProgramCode() {
		$program = new ProgramConfig();
		$programInfo = $program->getConfigById($GLOBALS['GameId']);
		if ($programInfo) {
			return $programInfo['code'];
		} else {
			return "";
		}
	}

}