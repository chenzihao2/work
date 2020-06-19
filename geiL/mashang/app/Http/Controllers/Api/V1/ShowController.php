<?php

namespace App\Http\Controllers\Api\V1;

use App\models\source_show;
use Illuminate\Http\Request;
use Ramsey\Uuid\Uuid;
use Ufile;
use Intervention\Image\Facades\Image;
use Tymon\JWTAuth\Facades\JWTAuth;

use Iwanli\Wxxcx\Wxxcx;
class ShowController extends BaseController {

    protected $wxxcx;

    function __construct(Wxxcx $wxxcx){
        $this->wxxcx = $wxxcx;
    }

    /**
     * 炫耀单列表
     */
    public function showList(Request $request){
        $token = JWTAuth::getToken();
        $clients = $this->UserInfo($token);
        if (array_key_exists('status_code', $clients)) {
            if ($clients['status_code'] == '401') {
                $return['status_code'] = "10001";
                $return['error_message'] = "token 失效或异常， 以正常渠道获取重试";
                return response()->json($return);
            }
        }
        $uid = $clients['id'];
        $page = $request->input('page', '0');
        $numberpage = $request->input('numberpage', '20');
        $offset = ($page-1) * $numberpage;

        $sourceShowList = source_show::where('uid', $uid)->where('status', 1)->orderBy('create_time', 'desc')->offset($offset)->limit($numberpage)->get()->toArray();
        if(!empty($sourceShowList)){
            foreach($sourceShowList as $key => $val){
		$img_url = (strpos($val['img_url'], 'https') === false) ? "https://zy.qiudashi.com/" . $val['img_url'] : $val['img_url'];
                $sourceShowList[$key]['img_url'] = $img_url;
                $sourceShowList[$key]['source_list'] = json_decode($val['source_list'], true);
                $sourceShowList[$key]['create_time'] = date("Y-m-d H:i:s", $val['create_time']);
            }
        }

        $sourceShowCount = source_show::where('uid', $uid)->where('status', 1)->count();

        $return['status_code'] = '200';
        $return['pagenum'] = $sourceShowCount;
        $return['data'] = $sourceShowList;

        return response()->json($return);

    }

    /**
     * 炫耀单详情
     */
    public function showInfo(Request $request, $id){
        $token = JWTAuth::getToken();
        $clients = $this->UserInfo($token);
        if (array_key_exists('status_code', $clients)) {
            if ($clients['status_code'] == '401') {
                $return['status_code'] = "10001";
                $return['error_message'] = "token 失效或异常， 以正常渠道获取重试";
                return response()->json($return);
            }
        }
        $uid = $clients['id'];

        $sourceShowInfo = source_show::where('id', $id)->where('status', 1)->where('uid', $uid)->first();
        if(empty($sourceShowInfo)){
            $return['status_code'] = "10003";
            $return['error_message'] = "该战绩不存在";
            return response()->json($return);
        }
        if(!empty($sourceShowInfo)){
	    $img_url = (strpos($sourceShowInfo['img_url'], 'https') === false) ? "https://zy.qiudashi.com/" . $sourceShowInfo['img_url'] : $sourceShowInfo['img_url'];
            $sourceShowInfo['img_url'] = $img_url;
            $sourceShowInfo['source_list'] = json_decode($sourceShowInfo['source_list']);
            $sourceShowInfo['create_time'] = date("Y-m-d H:i:s", $sourceShowInfo['create_time']);
        }

        $return['status_code'] = '200';
        $return['data'] = $sourceShowInfo;

        return response()->json($return);
    }

    /**
     * 删除战绩
     * @param Request $request
     * @param         $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function showDel(Request $request, $id){
        $token = JWTAuth::getToken();
        $clients = $this->UserInfo($token);
        if (array_key_exists('status_code', $clients)) {
            if ($clients['status_code'] == '401') {
                $return['status_code'] = "10001";
                $return['error_message'] = "token 失效或异常， 以正常渠道获取重试";
                return response()->json($return);
            }
        }
        $uid = $clients['id'];
        source_show::where('id', $id)->update(['status' => 0]);
        $return['status_code'] = '200';
        $return['data'] = [];

        return response()->json($return);
    }

    /**
     * 提交炫耀单
     */
    public function showSubmit(Request $request){
        $token = JWTAuth::getToken();
        $clients = $this->UserInfo($token);
        if (array_key_exists('status_code', $clients)) {
            if ($clients['status_code'] == '401') {
                $return['status_code'] = "10001";
                $return['error_message'] = "token 失效或异常， 以正常渠道获取重试";
                return response()->json($return);
            }
        }
        $uid = $clients['id'];
        $id = $request->input('id', 0);
        $title = $request->input('title', '');
        if(empty($title)){
            $return['status_code'] = '10002';
            $return['error_message'] = '请填写标题';
            return response()->json($return);
        }

        $source_list = $request->input('source_list', '');
        $source_list = json_decode($source_list, true);
        if(empty($source_list)){
            $return['status_code'] = '10002';
            $return['error_message'] = '请选择资源';
            return response()->json($return);
        }
        $dates = array_column($source_list,'date');
        array_multisort($dates,SORT_DESC,$source_list);

//        $uid = 1000;
//        $title = 'test1';
//        $source_list = [
//            [
//                'sid' => '1',
//                'title' => '欧冠强势来袭！今日三场尽心推荐凑数字',
//                'date' => '2019-01-07 18:47:59',
//                'color' => 1
//            ],
//            [
//                'sid' => '1',
//                'title' => '欧冠强势来袭！今日三场尽心推荐凑111',
//                'date' => '2019-01-07 18:47:59',
//                'color' => 2
//            ],
//            [
//                'sid' => '1',
//                'title' => '欧冠强势来袭！今日三场尽心推荐2222',
//                'date' => '2019-01-07 18:47:59',
//                'color' => 3
//            ]
//        ];

        // foreach($source_list as $key => $val){
        //     $source_list[$key]['date'] = date("m-d", strtotime($val['date']));
        // }

        $showData = [];
        $showData['title'] = $title;

        $uuid = Uuid::uuid1();
        $url = 'show_qrcode/' . $uuid->getHex() . '.jpg';

        $this->makeImg(['title' => $title, 'url' => $url, 'source_list' => $source_list]);

        $showData['uid'] = $uid;
        $showData['title'] = $title;
        $showData['img_url'] = config('qiniu.host') . '/' .$url;
        $showData['source_list'] = json_encode($source_list);
        $showData['status'] = 1;

        if($id){
            $showData['id'] = $id;
            $showData['create_time'] = time();
            source_show::where('id', $id)->update($showData);
        } else {
            $showData['create_time'] = time();
            $showData['id'] = source_show::insertGetId($showData);
        }

        //$showData['img_url'] = "https://zy.qiudashi.com/$url";
        $showData['source_list'] = json_decode($showData['source_list'], true);

        $return['status_code'] = '200';
        $return['data'] = $showData;

        return response()->json($return);

    }

    public function makeImg($show){
        // $show = [
        //     'title' => '肥强8月爆红8中7',
        //     'url' => 'showsource/' . time() . '.jpg',
        //     'source_list' => [
        //         [
        //             'sid' => '1',
        //             'title' => '欧冠强势来袭！今日三场尽心推荐凑数字',
        //             'date' => '08-11',
        //             'color' => 1
        //         ],
        //         [
        //             'sid' => '1',
        //             'title' => '欧冠强势来袭！今日三场尽心推荐凑111',
        //             'date' => '08-11',
        //             'color' => 2
        //         ],
        //         [
        //             'sid' => '1',
        //             'title' => '欧冠强势来袭！今日三场尽心推荐2222',
        //             'date' => '08-11',
        //             'color' => 2
        //         ],
        //         [
        //             'sid' => '1',
        //             'title' => '欧冠强势来袭！今日三场尽心推荐33333',
        //             'date' => '08-11',
        //             'color' => 3
        //         ],
        //         [
        //             'sid' => '1',
        //             'title' => '欧冠强势来袭！今日三场尽心推荐44444',
        //             'date' => '08-11',
        //             'color' => 1
        //         ],
        //         [
        //             'sid' => '1',
        //             'title' => '欧冠强势来袭！今日三场尽心推荐44444',
        //             'date' => '08-11',
        //             'color' => 1
        //         ],
        //     ],
        // ];

        $show_title = $show['title'];
        $show_source = $show['source_list'];
        $show_total = count($show_source);
        
        foreach($show_source as $key => $val){
            $show_source[$key]['date'] = date("m-d", strtotime($val['date']));
        }

        $img = Image::canvas(673, 210 + 50 * $show_total, '#fe4426');
        $img->insert('image/show_logo.png', 'top', 325, 28, function ($res){
            $res->align("center");
        });
        $img->text($show_title, 336.5, 144, function ($font){
            $font->file('ht.ttf');
            $font->size(40);
            $font->color("#FFFFFF");
            $font->align("center");
        });

        foreach($show_source as $key => $val){
            switch($val['color']){
                case 1:
                    //红单
                    $titleImg = Image::canvas(612, 47, '#ffe9e3');
                    $titleImg->text($val['date'], 30, 35, function ($font){
                        $font->file('ht.ttf');
                        $font->size(24);
                        $font->color("#ff4426");
                        $font->align("left");
                    });
                    $titleImg->text($val['title'], 104, 35, function ($font){
                        $font->file('ht.ttf');
                        $font->size(24);
                        $font->color("#ff4426");
                        $font->align("left");
                    });

                    $titleImg->insert('image/red_icon.png', 'right', 15, 30, function ($res){
                        $res->align("right");
                    });
                    break;
                case 2:
                    //黑单
                    $titleImg = Image::canvas(612, 47, '#f3f3f3');
                    $titleImg->text($val['date'], 30, 35, function ($font){
                        $font->file('ht.ttf');
                        $font->size(24);
                        $font->color("#757575");
                        $font->align("left");
                    });
                    $titleImg->text($val['title'], 104, 35, function ($font){
                        $font->file('ht.ttf');
                        $font->size(24);
                        $font->color("#757575");
                        $font->align("left");
                    });

                    $titleImg->insert('image/black_icon.png', 'right', 15, 30, function ($res){
                        $res->align("right");
                    });
                    break;
                case 3:
                    //走单
                    $titleImg = Image::canvas(612, 47, '#f0f6f8');
                    $titleImg->text($val['date'], 30, 35, function ($font){
                        $font->file('ht.ttf');
                        $font->size(24);
                        $font->color("#3760ca");
                        $font->align("left");
                    });
                    $titleImg->text($val['title'], 104, 35, function ($font){
                        $font->file('ht.ttf');
                        $font->size(24);
                        $font->color("#3760ca");
                        $font->align("left");
                    });

                    $titleImg->insert('image/blue_icon.png', 'right', 15, 30, function ($res){
                        $res->align("right");
                    });
                    break;
            }

            $img->insert($titleImg, 'top', 375, 179 + $key * 50, function ($res){
                $res->align("center");
            });
        }

        $basePath = $this->checkPathExist();
        $onlinePathInfo = explode("/", $show['url']);
        $localPath = $basePath . "/" . $onlinePathInfo[1];
        $img->save($localPath);
        /*$objUfile = new Ufile();
        $bucket = "qiudashizy";
        $re = $objUfile->put($bucket, $show['url'], $localPath);*/
	$re = $this->upload2Qiniu($show['url'], $localPath);

        if(!empty($re)){
            return true;
        } else {
            return false;
        }
    }

    private function upload2Qiniu($key, $filePath) {
      $upToken = $this->getUploadToken();
      $res = $this->qiniuUploadFile($upToken, $key, $filePath);
      return $res;
    }
    /**
     * 检查今日目录是否存在
     */
    private function checkPathExist() {
        $time = date("Ymd", time());
        $pathString = public_path() . "/showsource/" . $time;
        if (!is_dir($pathString)) {
            mkdir($pathString, 0777, true);
        }
        return $pathString;
    }

}
