<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use App\Comment\Pinyin;
class hl_sensitives extends Model
{
    /*
     * 敏感词表
     */
    protected $connection = 'mysql_origin';
    protected $table = 'hl_sensitives';
    protected $primaryKey = 'id';
    public $timestamps = false;
    protected $guarded = [];
    /*
     * 添加 敏感词
     */
    public static function addWords($data){
       return  self::insertGetId($data);
    }

    /*
     * 敏感词详情
     */
    public static function wordsInfo($id){
        return  self::where('id',$id)->first();
    }

    /*
     * 修改敏感词
     */

    public static function updateWords($id,$data){
        return  self::where('id',$id)->update($data);
    }

    /*
     * 获取所有敏感词
     * $where[]  条件
     */

    public static function wordsList($where=[]){
        $where[]=['deleted'=>0];
        return  self::where($where)->get()->toArray();
    }

    /*
     * 分页获取
     */
    public static function wordsLimit($where=[],$page=1,$pageSize=15){
        $model=self::where('deleted',0)->where($where);
        $count=$model->count();
        $totalPage = ceil($count/$pageSize); //总页数
        $startPage=($page-1)*$pageSize;//开始记录
        $list=$model->offset($startPage)->orderBy('id','desc')->limit($pageSize)->get()->toArray();
       return ['list'=>$list,'totalCount'=>$count];
    }

    //只获取敏感词
    public static function wordsListValue($where=[]){
        return  self::where('deleted',0)->pluck('words')->toArray();
    }
    //获取敏感词等级
    public static function wordsLevel($words){
        return self::whereIn("words",$words)->where('deleted',0)->get()->toArray();
    }
    /**
     * @todo 敏感词过滤，返回结果
     * @param array $list  定义敏感词一维数组
     * @param string $string 要过滤的内容
     * @return string $log 处理结果
     */
    public function matchSensitiveWords($string){
        $list=self::wordsListValue();//获取敏感词

        $stringAfter = $string;  //替换后的内容
        $pattern = "/".implode("|",$list)."/i"; //定义正则表达式

        if(preg_match_all($pattern, $string, $matches)){ //匹配到了结果
            $patternList = $matches[0];  //匹配到的数组

            $lists=self::wordsLevel($patternList);//获取敏感词等级
            $replaceArray=[];
            foreach($lists as $k=>$v){
                //报函等级1 的评论拒绝发布
                if($v['level']==1){
                    return 1;
                }
                //等级2 替换为*
                if($v['level']==2){
                    $replaceArray[$v['words']]=str_repeat('*', mb_strlen($v['words']));
                }
                //转为拼音
                if($v['level']==3){
                    $pinyin=new Pinyin();
                    $replaceArray[$v['words']]=$pinyin->str2pys($v['words']);
                }
            }
            //$replaceArray = array_combine($patternList,array_fill(0,count($patternList),'*')); //把匹配到的数组进行合并，替换使用

            $stringAfter = strtr($string, $replaceArray); //结果替换

        }
        return $stringAfter;
    }





}
