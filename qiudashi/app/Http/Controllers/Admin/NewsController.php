<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\hl_news;
use App\Models\hl_videos;
use App\Respository\FFMpegPhp;


class NewsController extends Controller
{

    public function __construct(hl_videos $hl_videos) {
        $this->hl_videos = $hl_videos;
    }
    //
    //资讯列表
    public function newsList(Request $request) {
        $query = $request->input('query', '');
        $pagesize = $request->input('pagesize', 20);
        $query = json_decode($query, 1);
        $data = hl_news::getNews($query, $pagesize, 1);
        return $this->rtJson($data);
    }

    //置顶||推荐
    public function recommend(Request $request) {
        $nid = $request->input('nid', '');
        if (!$nid) {
            return $this->rtJsonError(2000101);
        }
        $r_level = $request->input('is_recommend', 0);
        $result = hl_news::recommend($nid, $r_level);
        return $this->rtJson($result);
    }

    //视频列表
    public function videoList(Request $request) {
        $query = $request->input('query', '');
        $pagesize = $request->input('pagesize', 20);
        $query = json_decode($query, 1);
        $data = $this->hl_videos->getVideos($query, $pagesize);
        $this->rtJson($data);
    }

    //上传|修改 视频
    public function editVideo(Request $request) {
        $param = ['title', 'cid', 'image', 'video', 'id', 'money', 'is_pay','comment'];
        $query = [];
        foreach ($param as $item) {
            $query[$item] = $request->input($item, '');
            if ($item == 'comment' && $query[$item] === '') {
                $query[$item] = 1;
            }
            if (in_array($item, ['title', 'cid', 'image', 'video'])) {
                if (empty($query[$item])) {
                    return $this->rtJsonError(2000102, '确少参数' . $item);
                }
            }
        }
        $video_info = $this->hl_videos->editVideo($query);
        $this->rtJson();
        fastcgi_finish_request();
        new FFMpegPhp($video_info['filename'], $video_info['id']);
    }

    public function editVideoBak(Request $request) {
        $video_info = $this->hl_videos->getVideos([], 10);
        var_dump($video_info);die;
        $this->rtJson();
        fastcgi_finish_request();
        new FFMpegPhp($video_info['filename'], $video_info['id']);
    }


}
