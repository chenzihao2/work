<?php
/**
 * 错误码处理   ErrorCode.xml
 * User: YangChao
 * Date: 2018/9/29
 */

namespace QK\HaoLiao\XML;

class ErrorCodeConfig {

	protected $_dom;
	protected $_XMl;

	public function __construct() {
		$this->_XMl = APP_ROOT . DIRECTORY_SEPARATOR . "config" . DIRECTORY_SEPARATOR . "ErrorCode.xml";
		if (file_exists($this->_XMl)) {
			$this->_dom = new \DOMDocument('1.0', 'utf-8');
			$this->_dom->preserveWhiteSpace = false;
			$this->_dom->load($this->_XMl);
		} else {
			echo("错误码配置文件不存在");
			exit();
		}
	}

    /**
     * 根据code获取错误码
     * @param $errorCode
     * @return string
     */
	public function getErrorMessageByCode($errorCode) {
		$queryPath = '/codeList/code[@code="' . $errorCode . '"]';
		$xpath = new \DOMXPath($this->_dom);
		$configNodes = $xpath->query($queryPath);
		$configNode = $configNodes->item(0);
		$message = $configNode->attributes->getNamedItem('message')->nodeValue;
		return $message;
	}

}
