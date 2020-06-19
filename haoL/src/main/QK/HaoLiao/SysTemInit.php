<?php
/**
 * User: WangHui
 * Date: 2018/9/27
 * Time: 11:32
 */

namespace QK\HaoLiao;

use QK\WSF\Core\WebApp;
use QK\WSF\Settings\AppSetting;

class SysTemInit extends WebApp {
    public function __construct(AppSetting $appSetting) {
        parent::__construct($appSetting);
    }

    public function run() {

        // TODO: Implement run() method.s
        if ($this->_appSetting->isCrossDomain()) {
            $this->_crossDomainSetting();
        }

        $controller = isset($_GET['c']) && !empty($_GET['c']) ? trim($_GET['c']) : '';
        $doAction = isset($_GET['do']) && !empty($_GET['do']) ? trim($_GET['do']) : '';
        $p = isset($_GET['p']) && !empty($_GET['p']) ? trim($_GET['p']) : 'user';
        $v = isset($_GET['v']) && !empty($_GET['v']) ? trim($_GET['v']) : "1";
        try {
          if (isset($p) && $p != "") {
            $controller_name = $this->_appSetting->getControllerMapping($p.':'.$controller);
            $controllerClass = $this->_appSetting->getAppNamespace() . '\\Controllers\\' . ucfirst($p) . "\\V" . $v . "\\" . $controller_name;
            if (!class_exists($controllerClass)) {
              $controllerClass = $this->_appSetting->getAppNamespace() . '\\Controllers\\' . ucfirst($p) . "\\V1\\" . $controller_name;
            }
//                echo $controllerClass;exit;
            } else {
                $controllerClass = $this->_appSetting->getAppNamespace() . '\\Controllers\\' . $this->_appSetting->getControllerMapping($controller);
            }
            $classReflection = new \ReflectionClass($controllerClass);
            if ($classReflection->hasMethod($doAction)) {
                $methodReflection = $classReflection->getMethod($doAction);
                $controllerObj = $classReflection->newInstance($this->_appSetting);
                $methodReflection->invoke($controllerObj);
                $outputMethod = $classReflection->getMethod('output');
                $outputMethod->invoke($controllerObj);
            }
        } catch (\RuntimeException $e) {
            echo($e->getMessage() . ',File : ' . $e->getFile() . ', Line : ' . $e->getLine());
            echo($e->getTraceAsString());
            error_log($e->getMessage());
        }
    }

    private function _crossDomainSetting() {
        header('Access-Control-Allow-Origin: ' . $this->_appSetting->origin());
        header('Access-Control-Allow-Methods: GET, POST, PATCH, PUT, OPTION');
        header('Access-Control-Allow-Headers: x-requested-with,content-type,X-Token');
        header('Access-Control-Allow-Credentials: true');
    }

}
