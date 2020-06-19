<?php

/*
 * author:zhangpiao
 * email:zhangpiaopiao@7k7k.com
 * time:2018-6-21
 */

namespace QK\HaoLiao\XML;

class SystemConfig extends BaseXml {

	protected $_XMl;
	protected $_dom;

	public function __construct() {
		parent::__construct();
		$this->_XMl = APP_ROOT . DIRECTORY_SEPARATOR . "config" . DIRECTORY_SEPARATOR . "SystemConfig.xml";
		if (file_exists($this->_XMl)) {
			$this->_dom = new \DOMDocument('1.0', 'utf-8');
			$this->_dom->preserveWhiteSpace = false;
			$this->_dom->load($this->_XMl);
		} else {
			echo('系统配置文件不存在');
			exit();
		}
	}

	/*
	 * 获取配置列表
	 */

	public function getConfigListAll() {
		$xpath = new \DOMXPath($this->_dom);
		$programCode = $this->_ProgramCode;
		if ($programCode == "") {
			return false;
		}
		$query = '/configList/' . $programCode . '/config';
		$configList = array();
		if ($configData = $xpath->query($query)) {
			foreach ($configData as $realNode) {
				$config = array();
				$config['key'] = trim($realNode->attributes->getNamedItem('key')->nodeValue);
				$config['name'] = trim($realNode->attributes->getNamedItem('name')->nodeValue);
				$config['value'] = $realNode->attributes->getNamedItem('value')->nodeValue;
				$configList[] = $config;
			}
		}
		return $configList;
	}

	/**
	 * 更新配置信息
	 * @param $data
	 * @return bool
	 */
	public function updateXML($data) {
		$data = json_decode($data, true);
		$xpath = new \DOMXPath($this->_dom);
		$programCode = $this->_ProgramCode;
		if ($programCode == "") {
			return false;
		}
		$query = '/configList/' . $programCode;
		if ($configList = $xpath->query($query)) {
			$initConfigNode = $configList->item(0);
			if ($config = $initConfigNode->childNodes) {
				while ($config->length > 0) {
					$config->item(0)->parentNode->removeChild($config->item(0));
				}
			}
		} else {
			return false;
		}

		//生成新的节点数据
		//将新的节点数据循环插入原有节点
		foreach ($data as $configInfo) {
			$configNode = $this->_dom->createElement('config');
			$configNode->setAttribute('key', $configInfo['key']);
			$configNode->setAttribute('name', $configInfo['name']);
			$configNode->setAttribute('value', $configInfo['value']);
			$initConfigNode->appendChild($configNode);
		}
		$this->_saveXML();
		return true;
	}

	/**
	 * 根据键值 获取配置信息
	 * @param $key
	 * @return bool|string
	 */
	public function getConfigByName($key) {
		$programCode = $this->_ProgramCode;
		if ($programCode == "") {
			return false;
		}
		$queryPath = '/configList/' . $programCode . '/config[@key="' . $key . '"]';
		$xpath = new \DOMXPath($this->_dom);
		$configNodes = $xpath->query($queryPath);
		$configNode = $configNodes->item(0);
		$value = $configNode->attributes->getNamedItem('value')->nodeValue;
		if ($value != "") {
			return $value;
		} else {
			return false;
		}
	}

	protected function _saveXML() {
		$this->_dom->formatOutput = true;
		$this->_dom->save($this->_XMl);
	}

}
