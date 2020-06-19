<?php
/**
 * User: twenj
 * Date: 2019/7/03
 */

namespace App\Console\Commands;

require __DIR__ . '/../../Qiniu/Auth.php';
require __DIR__ . '/../../Qiniu/Config.php';
require __DIR__ . '/../../Qiniu/Common.php';
require __DIR__ . '/../../Qiniu/Region.php';
require __DIR__ . '/../../Qiniu/Zone.php';
require __DIR__ . '/../../Qiniu/Http/Error.php';
require __DIR__ . '/../../Qiniu/Http/Request.php';
require __DIR__ . '/../../Qiniu/Http/Response.php';
require __DIR__ . '/../../Qiniu/Http/Client.php';
require __DIR__ . '/../../Qiniu/Storage/BucketManager.php';

use Illuminate\Console\Command;
use Qiniu\Storage\BucketManager;
use Qiniu\Auth;

class staticMove extends Command {

    protected $signature = 'staticMove';

    protected $description = 'staticMove';

    protected $backetManager;

    protected $bucket;

    protected $host;

    public function __construct() {

        $accessKey = config('qiniu.ak');
        $secretKey = config('qiniu.sk');
        $this->bucket = config('qiniu.bucket');
        $auth = new Auth($accessKey, $secretKey);
        $this->bucketManager = new BucketManager($auth);
        $this->host = config('qiniu.host');

        parent::__construct();
    }

    public function handle() {

        $offset = 0;
        $limit = 100;

        $field = 'url';
        $table = 'source';

        while ($result = \DB::table($table)->select()->where('url', 'like', 'qrcode%')->orderBy('id', 'desc')->offset($offset)->limit($limit)->get()->ToArray()) {
            foreach ($result as $v) {
                if (!empty($v->$field)) {
                    $pictures = explode(',', $v->$field);
                    $afterPic = [];
                    foreach ($pictures as $pic) {
                        // 已经在给料服务器上
                        if (strpos($pic, 'gl-static.qiudashi.com') !== false) {
                            continue;
                        } elseif (strpos($pic, 'http') !== false) {
                            $file = basename($pic);
                            $ext = substr($file, strrpos($file, '.') + 1);
                            // $key = 'complains/' . time() . '.' . $ext;
                        } else {
                            $pre = 'http://zy.qiudashi.com';
                            $pic = $pre . '/' . $pic;
                            $key = $v->$field;
                        }
                        list($ret, $err) = $this->bucketManager->fetch($pic, $this->bucket, $key);
                        if ($err !== null) {
                            error_log($pic . ': ' . json_encode($err), 3, './pic.log');
                            echo $pic . "错误记录完成\n";
                            continue;
                        } else {
                            $afterPic[] = $this->host . '/' . $ret['key'];
                        }
                    }
                    if (!empty($afterPic)) {
                        $afterPicString = implode(',', $afterPic);
                        \DB::table($table)->where('id', $v->id)->update([$field => $afterPicString]);
                        error_log($v->sid . "\n", 3, './successPic.log');
                        echo $pic . "更新完成\n";
                    }
                }
            }

            $offset += $limit;
        }

        // while ()

        // $dbConfig = config('database');

        // \Config::set('database.connections.mysql.host', '127.0.0.1');
        // \Config::set('database.connections.mysql.port', '3306');
        // \Config::set('database.connections.mysql.database', 'information_schema');
        // \Config::set('database.connections.mysql.username', 'root');
        // \Config::set('database.connections.mysql.password', '');

        // \DB::reconnect();

        // $result = \DB::table('COLUMNS')->select()->where('TABLE_SCHEMA', 'yingxun')->get()->ToArray();

        // echo "| 表名 | 列名 | 类型 | 说明 |\n";
        // echo "| :--- | :--- | :--- | :--- |\n";

        // foreach ($result as $v) {
        //     echo '| ' . $v->TABLE_NAME . ' | ' . $v->COLUMN_NAME . ' | ' . $v->DATA_TYPE . ' | ' . $v->COLUMN_COMMENT . " |\n";
        // }
    }

}
