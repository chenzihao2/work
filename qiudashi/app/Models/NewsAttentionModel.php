<?php

namespace App\Models;

use App\Respository\FaceUtility;
use Illuminate\Database\Eloquent\Model;

class NewsAttentionModel extends Model
{
    //
    protected $connection = 'mysql_origin';
    protected $table = 'hl_news_attention';
    protected $pagesize = 20;
    public $timestamps = false;

    public function __construct() {
        $this->utility = new FaceUtility();
    }

    static public function collectNews($nid, $user_id) {
        $origin = self::where('nid', $nid)->where('user_id', $user_id)->first();
        if ($origin && $origin['collect'] == 1) {
            return self::where('id', $origin['id'])->update(['collect' => 0]);
        }
        if ($origin && $origin['collect'] == 0) {
            return self::where('id', $origin['id'])->update(['collect' => 1]);
        }
        if (!$origin) {
            return self::insert(['nid' => $nid, 'user_id' => $user_id, 'collect' => 1]);
        }
    }

    public function collectedNewsList($user_id) {
        if (!$user_id) {
            return [];
        }
        $collect_info = self::where('user_id', $user_id)->where('collect', 1)->latest('ctime')->get()->toArray();
        $nids = [];
        if ($collect_info) {
            $nids = array_column($collect_info, 'nid');
        }
        if (!$nids) {
            return [];
        }
        $query['article_source'] = 'expert';
        $query['nids'] = $nids;
        $data = hl_news::getNews($query, $this->pagesize);
        foreach ($data['data'] as $k => $v) {
            $data['data'][$k]['create_time'] = $this->utility->friendlyDate(strtotime($v['create_time']));
        }
        return $data;
    }
}
