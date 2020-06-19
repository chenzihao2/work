<?php

/** Ufile root directory */
if (!defined('Ufile_ROOT')) {
    define('Ufile_ROOT', dirname(__FILE__) . '/');
    require(Ufile_ROOT . 'proxy.php');
}

/**
 * Ufile
 */
class Ufile
{

    /**
     * put
     *
     *
     */
    public function put($bucket = null,$key = null,$filename = null)
    {
        //�ýӿ�������0-10MBС�ļ�,������ļ�����ʹ�÷�Ƭ�ϴ��ӿ�
        list($data, $err) = UCloud_PutFile($bucket, $key, $filename);
        if ($err) {
            $res['status'] = -1;
            $res['msg'] = $err->ErrMsg;
            $res['code'] = $err->Code;
            return $res;exit;
        }
        $res['status'] = 1;
        $res['msg'] = $data['ETag'];
        return $res;exit;
    }


    /**
     * get
     *
     *
     */
    public function get($bucket = null,$key = null)
    {
        $url = UCloud_MakePublicUrl($bucket, $key);
        if ($url) {
            $res['status'] = 1;
            $res['url'] = $url;
        }else{
            $res['status'] = -1;
            $res['msg'] = $err->ErrMsg;
            $res['code'] = $err->Code;
        }

        return $res;exit;
    }


}
