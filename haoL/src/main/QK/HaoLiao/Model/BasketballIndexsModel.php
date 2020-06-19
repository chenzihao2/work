<?php
/**
 * User: yzj
 * Date: 2019/09/04
 * Time: 10:42
 */

namespace QK\HaoLiao\Model;

use QK\HaoLiao\DAL\DALBasketballindexs;
use QK\HaoLiao\DAL\DALBasketballMatch;
use QK\HaoLiao\Common\StringHandler;
use QK\HaoLiao\Common\CommonHandler;

class BasketballIndexsModel extends BaseModel {

    private $_dalTest;
    private $_otherRedisKeyManageModel;
    private $_redisModel;
    const DOMAIN_7M = 'http://feed.sportsdt.com/szyuanzhan/basketball/?';
    const DOMAIN_7M2 = 'http://feed.sportsdt.com/szyuanzhan/soccer/?';

    private $index_Asia = self::DOMAIN_7M .  'type=getahoddsinfo&gameid='; //亚赔指数单场
    private $index_Europe = self::DOMAIN_7M .  'type=gethdaoddsinfo&gameid='; //欧赔指数单场
    private $index_Total = self::DOMAIN_7M .  'type=getouoddsinfo&gameid='; //总分指数单场
    private $index_Asia_list = self::DOMAIN_7M .  'type=getahoddslist&t=%d&pid=%d'; //亚赔指数列表
    private $index_Europe_list = self::DOMAIN_7M .  'type=gethdaoddslist&t=%d&pid=%d'; //欧赔指数列表
    private $index_Total_list = self::DOMAIN_7M .  'type=getouoddslist&t=%d&pid=%d'; //大小指数列表

    /*----------------北单&精彩--------------------*/


    private $dc_time = self::DOMAIN_7M2. 'type=getdegree_dc&year='; //获取单场彩期

    private $getschedule_jc_sp = self::DOMAIN_7M .  'type=getschedule_jc_sp&date='; //根据日期获取竞彩比赛(带参考值)

    private $getsphistory_jc = self::DOMAIN_7M .  'type=getsphistory_jc&gameid='; //根据比赛编号获取竞彩参考值的变化

    private $getschedule_dc_sp = self::DOMAIN_7M .  'type=getschedule_dc_sp&degree=%d'; //根据日期获取北京单场比赛(带参考值)

    //公司
    private $comp = [
        0 => '澳門',
        1 => '威廉希爾',
        2 => '易勝博',
        3 => '12BET',
        4 => 'S2',
        5 => '立博',
        6 => '188BET',
        7 => '10BET'

    ];
    public function __construct() {
        parent::__construct();
        $this->_dalTest = new DALBasketballindexs($this->_appSetting);
        $this->_otherRedisKeyManageModel = new RedisKeyManageModel('other');
        $this->match_dal = new DALBasketballMatch($this->_appSetting);
        $this->common = new CommonHandler();
    }


    //亚赔指数列表
    public function graspLists(){
        $res=$this->basketballIndex();//根据公司获取
        $res2=$this->singleBasketballMatch();//根据比赛编号获取
//        dump($res);
//
//        dump($res2);

    }


//dump($result);
    //比赛详情下的 指数
    public function lists($match_num,$indexs_type) {
        //根据类型获取公司
        $data=[];
       if($indexs_type==1){
           //获取该比赛下的公司 根据类型
           $comp_nums = $this->_dalTest->getIndexsInfoByNum($match_num, $indexs_type,0,'asc');
            foreach ($comp_nums as $v) {
                $data[$v['comp_num']]['comp_num'] = $v['comp_num'];
                $data[$v['comp_num']]['comp_name'] = $v['comp_name'];
                //欧指 篮球 本金需要+1
                $v['right_indexs']=round($v['right_indexs']+1,2);
                $v['left_indexs']=round($v['left_indexs']+1,2);

                if ($v['is_first'] == 1) {
                    $data[$v['comp_num']]['first'] = [$v['right_indexs'], $v['center_indexs'],$v['left_indexs']];
                } elseif ($v['is_first'] == 2) {
                    $data[$v['comp_num']]['now'] = [$v['right_indexs'], $v['center_indexs'], $v['left_indexs']];
                }
                $data[$v['comp_num']]['indexs_date'] = $v['indexs_date'];
            }

        }else {

           $comp_nums = $this->_dalTest->getIndexsCompByNum($match_num, $indexs_type);
            foreach ($comp_nums as $v) {
                    $data[$v['comp_num']]['comp_num'] = $v['comp_num'];
                    $data[$v['comp_num']]['comp_name'] = $this->comp[$v['comp_num']];
					 $v['right_indexs']=round($v['right_indexs']+1,2);
					$v['left_indexs']=round($v['left_indexs']+1,2);
                     $first=$this->_dalTest->getIndexsByComp($match_num, $indexs_type, $v['comp_num'], 1);
                     $now=$this->_dalTest->getIndexsByComp($match_num, $indexs_type, $v['comp_num'], 2);
                    if($indexs_type==2){
                        //$first['center_indexs']=$this->conversion($first['center_indexs']);
                        //$now['center_indexs']=$this->conversion($now['center_indexs']);
                    }
					//本金+1
					$first['right_indexs']=round($first['right_indexs']+1,2);
					$first['left_indexs']=round($first['left_indexs']+1,2);
					$now['right_indexs']=round($now['right_indexs']+1,2);
					$now['left_indexs']=round($now['left_indexs']+1,2);
					
                    $data[$v['comp_num']]['first'] =[$first['right_indexs'],$first['center_indexs'],$first['left_indexs'],$first['indexs_date']];

                    $data[$v['comp_num']]['now'] =[$now['right_indexs'],$now['center_indexs'],$now['left_indexs'],$now['indexs_date']];

             }

       }

        $data = array_values($data);

        return $data;
    }

    //根据公司获取该公司的指数列表
    public function compIndexs($match_num, $indexs_type, $comp_num) {
        $comp_info = $this->_dalTest->getIndexsCompByNum($match_num, $indexs_type);

        $comp = array_values($comp_info);

        $indexs_data = [];
        if ($indexs_type == 1) {


            $indexs_info = $this->_dalTest->getIndexsInfoByNum($match_num, $indexs_type, $comp_num,'desc',2);

            foreach ($indexs_info as $iv) {
                $tmp = [];
                //欧指 篮球 本金需要+1
                $iv['right_indexs']=round($iv['right_indexs']+1,2);
                $iv['left_indexs']=round($iv['left_indexs']+1,2);

                $tmp[] = $iv['right_indexs'];
                $tmp[] = $iv['center_indexs'];
                $tmp[] = $iv['left_indexs'];
                $tmp[] = $iv['indexs_date'];
                $indexs_data[] = $tmp;
            }
            //获取初盘
            $indexs_info_first = $this->_dalTest->getIndexsInfoByNum($match_num, $indexs_type, $comp_num,'asc',1);
            $right_indexs=round($indexs_info_first[0]['right_indexs']+1,2);
            $center_indexs=$indexs_info_first[0]['center_indexs'];
            $left_indexs=round($indexs_info_first[0]['left_indexs']+1,2);
            array_push($indexs_data,[$right_indexs,$center_indexs,$left_indexs,$indexs_info_first[0]['indexs_date']]);
        } else {
            $indexs_info = $this->_dalTest->getIndexsByComp($match_num, $indexs_type, $comp_num,0,2);
            foreach ($indexs_info as $iv) {
               if($indexs_type==2){
                   //$iv['center_indexs']=$this->conversion($iv['center_indexs']);
                }
				$iv['right_indexs']=round($iv['right_indexs']+1,2);
                $iv['left_indexs']=round($iv['left_indexs']+1,2);
                $tmp = [];
                $tmp[] = $iv['right_indexs'];
                $tmp[] = $iv['center_indexs'];
                $tmp[] = $iv['left_indexs'];
                $tmp[] = $iv['indexs_date'];
                $indexs_data[] = $tmp;
            }
            //初盘
            $indexs_info_first = $this->_dalTest->getIndexsByComp($match_num, $indexs_type, $comp_num,0,1);
            $first_info=$indexs_info_first[0];
            if($indexs_type==2){
                //$first_info['center_indexs']=$this->conversion($first_info['center_indexs']);
            }
			$right_indexs=round($first_info['right_indexs']+1,2);
            $center_indexs=$first_info['center_indexs'];
            $left_indexs=round($first_info['left_indexs']+1,2);
            array_push($indexs_data,[$right_indexs,$center_indexs,$left_indexs,$first_info['indexs_date']]);

            //array_push($indexs_data,[$first_info['right_indexs'],$first_info['center_indexs'],$first_info['left_indexs'],$first_info['indexs_date']]);
        }

        return ['comp' => $comp, 'indexs' => $indexs_data];
    }



    //根据赛事获取单场比赛指数
    public function singleBasketballMatch(){
        $match_nums=$this->_dalTest->getMatchNumByDate(0, 0, ['indexs' => 1]);
        //$match_nums=[1104265,1104285];
        if (!$match_nums) {
            return ;
        }
        foreach($match_nums as $v){
            $this->asiaMatchNumsIndex($v);
            $this->europeMatchNumsIndex($v);
            $this->totalMatchNumsIndex($v);
        }
    }

    //根据赛事编号获取欧赔指数
    public function europeMatchNumsIndex($match_nums){
        $url=$this->index_Europe.$match_nums;
        $result=$this->indexRequest($url);
        if (!$result['Datas']) {
            return [];
        }
        $this->assembleMatchNumsIndex($result,1);
        return;
    }
    //根据赛事编号获取亚赔指数
    public function asiaMatchNumsIndex($match_nums){
        $url=$this->index_Asia.$match_nums;
        $result=$this->indexRequest($url);
        if (!$result['Datas']) {
            return [];
        }
        $this->assembleMatchNumsIndex($result,2);
        return;
    }
    //根据赛事编号获取大小（总分）指数
    public function totalMatchNumsIndex($match_nums){
        $url=$this->index_Total.$match_nums;
        $result=$this->indexRequest($url);
        if (!$result['Datas']) {
            return [];
        }
        $this->assembleMatchNumsIndex($result,3);
       return;
    }



    //组装 当前赛事 亚，欧，大小 盘指数数据
    public function assembleMatchNumsIndex($result,$indexs_type){

        $company=$this->comp;
        foreach($result['Datas'] as &$vv){
            $vv['Datas']=date('Y-m-d H:i:s', bcdiv($vv['Date'], 1000));
            $data['comp_num']=$vv['Cid'];//公司编号
            $data['comp_name']=$company[$vv['Cid']];//公司名称
            $data['indexs_date']=date('Y-m-d H:i:s', bcdiv($vv['Date'], 1000));//当前指数时间
            $data['match_num']=$result['GameId'];//比赛编号
            $data['ctime']=date("Y-m-d H:i:s",time());//创建时间
            //检查是否已存在
            $exists = $this->_dalTest->existsIndexs($data['match_num'], $data['comp_num'], $indexs_type, $data['indexs_date']);

            if ($exists) {
                continue;
            }
            $data['is_first'] = 2;
            $data['left_indexs']=$vv['Data'][0];//主胜
            $data['right_indexs']=$vv['Data'][1];//客胜
            $data['center_indexs']=$indexs_type!=1?$vv['Data'][2]:0;//盘口
            $data['indexs_type']=$indexs_type;//指数类型 1：欧指 2：亚指 3：大小球
            //dump($data);
            //添加
            $this->_dalTest->addBasketballIndexs($data);

            //die;
        }
        //dump($result);die;
    }

    //根据公司获取篮球指数列表
    public function basketballIndex(){


        $company=$this->comp;
        foreach ($company as $k=>$comp_num) {

            $this->asiaIndex($k,$comp_num);
            $this->europeIndex($k,$comp_num);
            $this->totalIndex($k,$comp_num);
        }
    }


    //欧赔单场及时指数
    public function europeIndex($num,$comp_num){
        $url = sprintf($this->index_Europe_list, 1, $num);
        $result=$this->indexRequest($url);
        if (!$result['Datas']) {
            return [];
        }
        $this->assembleIndex($result,$comp_num,1);
    }

    //亚赔指数列表
    public function asiaIndex($num,$comp_num){
            $url = sprintf($this->index_Asia_list, 1, $num);
            $result=$this->indexRequest($url);
            if (!$result['Datas']) {
                return [];
            }
             $this->assembleIndex($result,$comp_num,2);
    }
    //大小指数列表
    public function totalIndex($num,$comp_num){

           $url = sprintf($this->index_Total_list, 1, $num);
            $result=$this->indexRequest($url);
            if (!$result['Datas']) {
                return [];
            }
             $this->assembleIndex($result,$comp_num,3);
    }

    //组装 亚，欧，大小 盘数据
    public function assembleIndex($result,$comp_name,$indexs_type){

            foreach($result['Datas'] as $vv){

                $data['comp_num']=$result['CId'];//公司编号
                $data['comp_name']=$comp_name;//公司名称
                $data['indexs_date']=date('Y-m-d H:i:s', bcdiv($vv['Date'], 1000));//当前指数时间
                $data['match_num']=$vv['GameId'];//比赛编号
                $data['ctime']=date("Y-m-d H:i:s",time());//创建时间
                //检查是否已存在
                $exists = $this->_dalTest->existsIndexs($data['match_num'], $data['comp_num'], $indexs_type, $data['indexs_date']);

                if ($exists) {
                    continue;
                }
                $data['is_first'] = 2;
                $data['left_indexs']=$vv['Data'][0];//主胜
                $data['right_indexs']=$vv['Data'][1]?$vv['Data'][1]:0;//客胜
                $data['center_indexs']=$indexs_type!=1?$vv['Data'][2]:0;//盘口
                $data['indexs_type']=$indexs_type;//指数类型 1：欧指 2：亚指 3：大小球
               //亚 【主赔,客赔,让分,主赔(初盘),客赔(初盘),让分(初盘)】
                //欧 【主赔,客赔,主赔(初盘),客赔(初盘)】


                //添加
                $this->_dalTest->addBasketballIndexs($data);

                $data['is_first'] = 1;
                $data['left_indexs'] = $indexs_type!=1?$vv['Data'][3]:$vv['Data'][2];//主胜初盘
                $data['right_indexs'] = $indexs_type!=1?$vv['Data'][4]:$vv['Data'][3];//客胜初盘
                $data['center_indexs'] = $indexs_type!=1?$vv['Data'][5]:0;//盘口
                $this->_dalTest->addBasketballIndexs($data);




                //die;
            }
    }



    /*
	*
	*获取竞彩&北单 数据
	*
	*/
    public function import_basketball_lottery(){
        $date0 = date('Y-m-d', time() - 86400);
        $date1 = date('Y-m-d', time());
        $date2 = date('Y-m-d', time() + 86400);
        $date3 = date('Y-m-d', time() + 86400 * 2);
        $dataArr=[$date0, $date1, $date2, $date3];
        //竞彩数据
        foreach ($dataArr as $date) {
            $url = $this->getschedule_jc_sp . $date;
            var_dump($url);
            $result = $this->indexRequest($url);
            if (!$result['LotteryS']) {
                continue;
            }
            $lotterys = $result['LotteryS'];
            foreach ($lotterys as $item) {

                $this->basketballLottery($item);
            }
        }

        //北单数据
        $year = date('Y', time());
        $bd_times_url = $this->dc_time .$year;

        $bd_times = $this->indexRequest($bd_times_url);
        if ($bd_times) {
            $cur_times = $bd_times['CurDegree'];
            $bd_lottery_url = sprintf($this->getschedule_dc_sp, $cur_times);

            $bd_result = $this->indexRequest($bd_lottery_url);

            //dump($bd_result);die;
            if ($bd_result['LotteryS']) {
                $bd_lotterys = $bd_result['LotteryS'];
                foreach ($bd_lotterys as $item) {
                    $this->basketballLottery($item, $cur_times);
                }
            }
        }
    }


    private function basketballLottery($data, $degree = 0) {
        if ($degree && empty($data['HHDA'])) {
            return true;
        }
        $arr=[$data['HDA'],$data['HHDA'], $data['HILO']];

        foreach ($arr as $k=>$item) {

            if ($item) {
                $format_data = [];
                $format_data['match_num'] = $data['matchId'];
                $format_data['date'] = $data['starttime'];
                $format_data['is_ah'] = $data['isAH'];
                $format_data['is_single'] = isset($item['single'])&&$item['single']?:0;
                if (empty($data['num'])) {
                    continue;
                }
                $format_data['lottery_num'] = $data['num'];
                $format_data['lottery_type'] = 1;
                if ($degree) {
                    $format_data['lottery_num'] = $degree . '-' . $data['num'];
                    $format_data['lottery_type'] = 2;
                }
                //大小分参考值
                if($k==2){
                    $format_data['h'] = $item['T'];//总分
                    $format_data['w'] = sprintf("%01.2f",$item['H']?:0);//大分
                    $format_data['l'] = sprintf("%01.2f",$item['L']);//小分
                }else{
                    $format_data['h'] = $item['H'] ?: 0;//让球
                    $format_data['w'] = sprintf("%01.2f",$item['W']?:0);//主胜
                    $format_data['l'] = sprintf("%01.2f",$item['L']);//主负
                }

                $utime = date('Y-m-d H:i:s', bcdiv($item['ut'], 1000));
                $format_data['utime'] = $utime;
                //查询条件
                $exists_condition = ['is_first'=>0,'match_num' => $format_data['match_num'], 'lottery_num' => $format_data['lottery_num'], 'lottery_type' => $format_data['lottery_type']];

                $exists_condition['type'] = ($k+1);
                //$exists_condition['utime'] = $format_data['utime'];

                $exists_info = $this->match_dal->existsLottery($exists_condition);//竞彩||北单是否已存在


                $this->firstJc($format_data,$k);//初盘

                if (!$exists_info) {

                    $match_info = $this->match_dal->getMatchInfo($format_data['match_num']);//查询比赛信息
                    if ($match_info) {
                        $leagueInfo = $this->match_dal->getLeagueInfo($match_info['league_num']);//获取联赛信息
                        $hostInfo = $this->match_dal->getTeamInfo($match_info['host_team']);//主队比赛球队信息
                        $guestInfo = $this->match_dal->getTeamInfo($match_info['guest_team']);//客队比赛球队信息
                        $format_data['league_short_name'] = $leagueInfo['short_name'] ?: '';
                        $format_data['league_name'] = $leagueInfo['name'] ?: '';
                        $format_data['host_team_name'] = $hostInfo['name'] ?: '';
                        $format_data['guest_team_name'] = $guestInfo['name'] ?: '';
                        $format_data['league_num'] = $match_info['league_num'];
                        $format_data['host_team'] = $match_info['host_team'];
                        $format_data['guest_team'] = $match_info['guest_team'];
                        $format_data['type'] = ($k+1);//1:胜平负参考值,2:让球胜平负参考值,3:大小分参考值

                        $this->match_dal->addLottery($format_data);

                    }
                } else {
                    if ($exists_info['utime'] < $utime) {
                        $this->match_dal->updateLottery($format_data, $exists_info['id']);
                    }
                }

            }
        }
        return;
    }

    //初盘数据
    private function firstJc($data,$k) {
        if($data['lottery_type'] != 1) {
            return true;
        }

        $url = $this->getsphistory_jc . $data['match_num'];
        var_dump('first_' . $url);
        //$url = $this->getsphistory_jc . '607853';

        $jc_history = $this->indexRequest($url);


        $hda = $jc_history['HDA'];
        $hhda =$jc_history['HHDA'];
        $arr=[$jc_history['HDA'],$jc_history['HHDA'], $jc_history['HILO']];

        $aim = [];
        foreach ($arr as $items) {
            $index=count($items)-1;
            if($index>=0){
                $aim[]=$items[$index];//初盘数据
            }
        }

        $item=$aim[$k];
        if($item){
            //foreach($aim as $k=>$item){
            $format_data['match_num'] = $data['match_num'];
            $format_data['lottery_num'] = $data['lottery_num'];
            $ut = date('Y-m-d H:i:s', bcdiv($item['ut'], 1000));
            $format_data['date'] = $data['date'];
            $format_data['is_ah'] = $data['is_ah'];
            $format_data['utime'] = $ut;
            $format_data['is_single'] = isset($item['single'])&&$item['single']?:0;
            $format_data['type'] = ($k+1);//1:胜平负参考值,2:让球胜平负参考值,3:大小分参考值
            $format_data['is_first'] = 1;
            $format_data['lottery_type'] = 1;
            //$format_data['lottery_type'] =$data['lottery_type'];
            //是否已存在 初盘
            $exists_condition = ['is_first'=>1,'utime'=>$ut,'type'=>($k+1),'match_num' => $format_data['match_num'], 'lottery_num' => $format_data['lottery_num'], 'lottery_type' => $format_data['lottery_type']];

            $exists_info = $this->match_dal->existsLottery($exists_condition);//竞彩||北单是否已存在
            if(!$exists_info){
                //大小分参考值
                if($k==2){
                    $format_data['h'] = $item['T'];//总分
                    $format_data['w'] = sprintf("%01.2f",$item['H']?:0);//大分
                    $format_data['l'] = sprintf("%01.2f",$item['L']);//小分
                }else{
                    $format_data['h'] = $item['H'] ?: 0;//让球
                    $format_data['w'] = sprintf("%01.2f",$item['W']?:0);//主胜
                    $format_data['l'] = sprintf("%01.2f",$item['L']);//主负
                }

                $this->insertLottery($format_data);

            }
            //}
        }



    }

    //体彩数据入库
    public function insertLottery($format_data){
        $match_info = $this->match_dal->getMatchInfo($format_data['match_num']);//查询比赛信息
        if($match_info){
            $leagueInfo = $this->match_dal->getLeagueInfo($match_info['league_num']);//获取联赛信息
            $hostInfo = $this->match_dal->getTeamInfo($match_info['host_team']);//主队比赛球队信息
            $guestInfo = $this->match_dal->getTeamInfo($match_info['guest_team']);//客队比赛球队信息
            $format_data['league_short_name'] = $leagueInfo['short_name'] ?: '';
            $format_data['league_name'] = $leagueInfo['name'] ?: '';
            $format_data['host_team_name'] = $hostInfo['name'] ?: '';
            $format_data['guest_team_name'] = $guestInfo['name'] ?: '';
            $format_data['league_num'] = $match_info['league_num'];
            $format_data['host_team'] = $match_info['host_team'];
            $format_data['guest_team'] = $match_info['guest_team'];
            $this->match_dal->addLottery($format_data);
        }
    }







    //request
    public function indexRequest($url){
        $res =  $this->common->httpGetRequest($url, []);
        $result=json_decode($res,true);
        return $result;

    }
    //亚指  指数盘口数据转换
    function conversion($number= 0){
        $num=$number>0?-1*$number:abs($number);
//        if($num>0){
//            $num='+'.$num;
//        }
        return $num;
    }
}
