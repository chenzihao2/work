<?php
namespace App\Respository;
//include(dirname(__FILE__) . '/../../vendor/autoload.php');
use FFMpeg\FFMpeg;
use FFMpeg\FFProbe;
use App\Models\hl_videos;

class FFMpegPhp{
    private $ffmpeg_path = '/data/sh/ffmpeg-git-20200504-amd64-static/ffmpeg';
    private $ffprobe_path = '/data/sh/ffmpeg-git-20200504-amd64-static/ffprobe';
    private $ffmpeg_cmd =  '/data/sh/ffmpeg-git-20200504-amd64-static/ffmpeg -i "%s" 2>&1';
    public function __construct($filename = '', $id = 0) {
        $path = [
            'ffmpeg.binaries'  => $this->ffmpeg_path,
            'ffprobe.binaries' => $this->ffprobe_path,
        ];
        $this->ffmpeg = FFMpeg::create($path);
        $this->ffprobe = FFProbe::create($path);
        //$info = $this->ffprobe->format('https://hl-static.haoliao188.com/video/201909/05/6edf1fadb295a3792f9cbd96aac1596d.mp4');
        //$info = $this->getVideoInfo('https://hl-static.haoliao188.com/video/201909/05/6edf1fadb295a3792f9cbd96aac1596d.mp4');
        $info = $this->getVideoInfo($filename);
        $this->saveVideoInfo($info, $id);
        var_dump($info);
    }

    private function getVideoInfo($file){
        if (!$file) {
            return false;
        }
        ob_start();
        passthru(sprintf($this->ffmpeg_cmd, $file));
        $video_info = ob_get_contents();
        ob_end_clean();

        // 使用输出缓冲，获取ffmpeg所有输出内容
        $ret = array();

        // Duration: 00:33:42.64, start: 0.000000, bitrate: 152 kb/s
        if (preg_match("/Duration: (.*?), start: (.*?), bitrate: (\d*) kb\/s/", $video_info, $matches)){
            $ret['duration'] = $matches[1]; // 视频长度
            $duration = explode(':', $matches[1]);
            $ret['seconds'] = $duration[0]*3600 + $duration[1]*60 + $duration[2]; // 转为秒数
            $ret['start'] = $matches[2]; // 开始时间
            $ret['bitrate'] = $matches[3]; // bitrate 码率 单位kb
        }

        // Stream #0:1: Video: rv20 (RV20 / 0x30325652), yuv420p, 352x288, 117 kb/s, 15 fps, 15 tbr, 1k tbn, 1k tbc
        if(preg_match("/Video: (.*?), (.*?), (.*?), (.*?)[,\s]/", $video_info, $matches)){
            $ret['vcodec'] = $matches[1];     // 编码格式
            $ret['vformat'] = $matches[2];    // 视频格式
            $ret['resolution'] = $matches[3]; // 分辨率
            $sizeinfo = explode('x', $matches[3]);
            if (isset($sizeinfo[0]) && isset($sizeinfo[1])) {
                list($width, $height) = explode('x', $matches[3]);
            } else {
                list($width, $height) = explode('x', $matches[4]);
            }
            $ret['width'] = $width;
            $ret['height'] = $height;
        }

        // Stream #0:0: Audio: cook (cook / 0x6B6F6F63), 22050 Hz, stereo, fltp, 32 kb/s
        if(preg_match("/Audio: (.*), (\d*) Hz/", $video_info, $matches)){
            $ret['acodec'] = $matches[1];      // 音频编码
            $ret['asamplerate'] = $matches[2]; // 音频采样频率
        }

        if(isset($ret['seconds']) && isset($ret['start'])){
            $ret['play_time'] = $ret['seconds'] + $ret['start']; // 实际播放时间
        }

        //$ret['size'] = filesize($file); // 视频文件大小
        $ret['size'] = $this->ffprobe->format($file)->get('size', 0);
        $video_info = iconv('gbk','utf8', $video_info);
        //return array($ret, $video_info);
        return $ret;

    }

    private function saveVideoInfo($info, $id) {
        if (!$info || !$id) {
            return false;
        }
        $data = [];
        $data['seconds'] = (int)$info['seconds'];
        $data['width'] = $info['width'];
        $data['height'] = $info['height'];
        $data['size'] = $info['size'];
        hl_videos::where('id', $id)->update($data); 
    }

}
