<?php
/**
 * User: WangHui
 * Date: 2018/11/14
 * Time: 上午11:38
 */

namespace QK\HaoLiao\Controllers\Console\Base;


use QK\CloudStorage\CloudStorageFactory;
use QK\HaoLiao\Controllers\Expert\ExpertController;
use QK\WSF\Settings\AppSetting;
use Upload\File;
use Upload\Storage\FileSystem;
use Qiniu\Auth;
class FileController extends ExpertController {

    public function __construct(AppSetting $appSetting) {
        $this->setTokenCheck(false);
        parent::__construct($appSetting);
    }
    /**
     * 文件上传
     */
    public function fileUpload() {
        $params = $this->checkApiParam(['type']);
        //获取今日目录
        $prefix = $this->_appSetting->getConstantSetting('QINiu-PATH:' . $params['type']);
        $path = $this->getPath($prefix);
        $localPath = $this->_appSetting->getConstantSetting('FILE-UPLOAD') . "/" . $prefix . "/" . $path;
        if ($_FILES == null || !isset($_FILES['file'])) {
            $this->setResultFailed();
            $this->setResultMessage("请选择文件");
        } else {

            $storage = new FileSystem($localPath);
            $file = new File('file', $storage);
            try {
                $fileMd5 = $file->getMd5();
                $new_filename = uniqid(time(), true);
                $file->setName($new_filename);
                $file->upload();
                $fileName = $file->getNameWithExtension();
                $returnName = $fileMd5 . "." . $file->getExtension();
                $data = array(
                    'img_src' => $this->_appSetting->getConstantSetting('STATIC_URL') . $prefix . "/" . $path . "/" . $returnName
                );
                $qiNiuPublicKey = $this->_appSetting->getConstantSetting('QiNiu-PUBLIC-KEY');;
                $qiNiuPrivateKey = $this->_appSetting->getConstantSetting('QiNiu-PRIVATE-KEY');;
                $qiNiuObj = CloudStorageFactory::newInstance()->createQiNiuObj($qiNiuPublicKey, $qiNiuPrivateKey);
                $qiNiuBucket = $this->_appSetting->getConstantSetting('QiNiu-BUCKET');

                $key = $prefix . "/" . $path . "/" . $returnName;
                $file = $localPath . "/" . $fileName;
                $qiNiuObj->upload($qiNiuBucket, $key, $file);

                $result = $qiNiuObj->getRet();
                if ($result['hash']) {
                    $this->responseJson($data);
                } else {
                    $this->responseJsonError(105);
                }
            } catch (\Exception $e) {
                $this->responseJsonError(105);
            }
        }
    }
	
	
	/**
     * 获取七牛token
     */
    public function getfileUploadToken() {
            $qiNiuPublicKey = $this->_appSetting->getConstantSetting('QiNiu-PUBLIC-KEY');;
            $qiNiuPrivateKey = $this->_appSetting->getConstantSetting('QiNiu-PRIVATE-KEY');
            $qiNiuBucket = $this->_appSetting->getConstantSetting('QiNiu-BUCKET');
            $qiNiuObj = new Auth($qiNiuPublicKey, $qiNiuPrivateKey);
            $token = $qiNiuObj->uploadToken($qiNiuBucket);
            $data['token']=$token;
            $this->responseJson($data);
    }
	
	/**
     * 获取七牛配置信息
     */
    public function getfileUploadConfig() {
        $qiNiuPublicKey = $this->_appSetting->getConstantSetting('QiNiu-PUBLIC-KEY');;
        $qiNiuPrivateKey = $this->_appSetting->getConstantSetting('QiNiu-PRIVATE-KEY');
        $qiNiuBucket = $this->_appSetting->getConstantSetting('QiNiu-BUCKET');
        $qiNiuObj = new Auth($qiNiuPublicKey, $qiNiuPrivateKey);
        //$token = $qiNiuObj->uploadToken($qiNiuBucket);
        $data['public_key']=$qiNiuPublicKey;
        $data['private_key']=$qiNiuPrivateKey;
        $data['bucket']=$qiNiuBucket;
        $this->responseJson($data);
    }

    /**
     * 获取今日目录
     * @param $prefix
     * @return string
     */
    private function getPath($prefix) {
        $time = time();
        $monthPathString = $this->_appSetting->getConstantSetting('FILE-UPLOAD') . "/" . $prefix . "/" . date('Ym', $time);

        $this->checkPath($monthPathString);
        $dayPathString = $monthPathString . "/" . date("d", $time);
        $this->checkPath($dayPathString);
        $onlinePath = date("Ym", $time) . "/" . date("d", $time);
        return $onlinePath;
    }

    /**
     * 创建目录
     * @param $path
     */
    private function checkPath($path) {
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }
    }
}