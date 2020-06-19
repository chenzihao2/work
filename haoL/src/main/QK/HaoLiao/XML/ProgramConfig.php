<?php
/**
 * 小程序配置文件处理   MiniList.xml
 * User: WangHui
 * Date: 2018/7/18
 * Time: 15:10
 */

namespace QK\HaoLiao\XML;

class ProgramConfig {

	protected $_dom;
	protected $_XMl;

	public function __construct() {
		$this->_XMl = APP_ROOT . DIRECTORY_SEPARATOR . "config" . DIRECTORY_SEPARATOR . "MiniList.xml";
		if (file_exists($this->_XMl)) {
			$this->_dom = new \DOMDocument('1.0', 'utf-8');
			$this->_dom->preserveWhiteSpace = false;
			$this->_dom->load($this->_XMl);
		} else {
			echo("排行榜配置文件不存在");
			exit();
		}
	}


	/*
	 * 获取配置列表
	 */
	public function getConfigListAll() {
		$xpath = new \DOMXPath($this->_dom);
		$query = '/configList/config';
		$configList = array();
		if ($configData = $xpath->query($query)) {
			foreach ($configData as $realNode) {
				$config = array();
                $config['id'] = intval($realNode->attributes->getNamedItem('id')->nodeValue);
                $config['name'] = trim($realNode->attributes->getNamedItem('name')->nodeValue);
                $config['type'] = trim($realNode->attributes->getNamedItem('type')->nodeValue);
				$configList[] = $config;
			}
		}
		return $configList;
	}

	/**
	 * 根据code 获取小程序id与名称
	 * @param $key
	 * @return array|bool
	 */
	public function getConfigByName($key) {
		$queryPath = '/configList/config[@code="' . $key . '"]';
		$xpath = new \DOMXPath($this->_dom);
		$configNodes = $xpath->query($queryPath);
		$configNode = $configNodes->item(0);
		$id = $configNode->attributes->getNamedItem('id')->nodeValue;
		$name = $configNode->attributes->getNamedItem('name')->nodeValue;
		if ($id != "") {
			$data = [];
			$data['id'] = $id;
			$data['name'] = $name;
			return $data;
		} else {
			return false;
		}
	}

	/**
	 * 根据id 获取小程序code与名称
	 * @param $id
	 * @return array|bool
	 */
	public function getConfigById($id) {
		$queryPath = '/configList/config[@id="' . $id . '"]';
		$xpath = new \DOMXPath($this->_dom);
		$configNodes = $xpath->query($queryPath);
		$configNode = $configNodes->item(0);
        
        $data = [];
        $data['id'] = intval($configNode->attributes->getNamedItem('id')->nodeValue);
        $data['name'] = trim($configNode->attributes->getNamedItem('name')->nodeValue);
        $data['type'] = trim($configNode->attributes->getNamedItem('type')->nodeValue);
        return $data;
	}

    /**
     * 更新配置信息
     * @param $data
     * @return bool
     */
    public function updateXML($data) {
        $xpath = new \DOMXPath($this->_dom);
        $query = '/configList';
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
//
        //生成新的节点数据
        //将新的节点数据循环插入原有节点
        foreach ($data as $configInfo) {
            $configNode = $this->_dom->createElement('config');
            $configNode->setAttribute('id', $configInfo['id']);
            $configNode->setAttribute('name', $configInfo['name']);
            $configNode->setAttribute('type', $configInfo['type']);
            $initConfigNode->appendChild($configNode);
        }
        $this->_saveXML();
        return true;
    }

    /**
     * 根据ID修改数据信息
     * @param $id
     * @param $data
     * @return bool
     */
    public function editConfigById($id, $data){
        $queryPath = '/configList/config[@id="' . $id . '"]';
        $xpath = new \DOMXPath($this->_dom);
        $configNodes = $xpath->query($queryPath);
        $configNode = $configNodes->item(0);
        foreach ($data AS $key=>$val) {
            $configNode->setAttribute($key, $val);
        }
        $this->_saveXML();

        return TRUE;
    }

	/**
	 * 根据金币类型获取小程序列表
	 * @param $goldType
	 * @return array
	 */
    public function getMiniList($goldType) {
    	$query = '/configList/config[@goldType="'.$goldType.'"]';
    	$xPath = new \DOMXPath($this->_dom);
    	$list = array();
    	if($configNodes = $xPath->query($query)) {
    		foreach ($configNodes as $configNode) {
    			$info = array();
				$info['id'] = intval($configNode->attributes->getNamedItem('id')->nodeValue);
				$info['name'] = trim($configNode->attributes->getNamedItem('name')->nodeValue);
				$list[] = $info;
			}
		}
		return $list;
	}

    protected function _saveXML() {
        $this->_dom->formatOutput = true;
        $this->_dom->save($this->_XMl);
    }


}