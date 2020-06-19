<?php

namespace App\Admin\Controllers;

use App\Client_log;
use App\Clients;
use App\Http\Controllers\Controller;
use App\profile;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Layout\Column;
use Encore\Admin\Layout\Content;
use Encore\Admin\Layout\Row;
use Encore\Admin\Widgets\Box;
use Encore\Admin\Widgets\Chart\Bar;
use Encore\Admin\Widgets\Chart\Doughnut;
use Encore\Admin\Widgets\Chart\Line;
use Encore\Admin\Widgets\Chart\Pie;
use Encore\Admin\Widgets\Chart\PolarArea;
use Encore\Admin\Widgets\Chart\Radar;
use Encore\Admin\Widgets\Collapse;
use Encore\Admin\Widgets\InfoBox;
use Encore\Admin\Widgets\Tab;
use Encore\Admin\Widgets\Table;

class HomeController extends Controller
{
    public function index()
    {
        return Admin::content(function (Content $content) {

            $content->header('系统首页');
            $content->description('信息展示');

            $content->row(function ($row) {
                $row->column(3, new InfoBox('新用户', 'users', 'aqua', '/admin/users', '1024'));
                $row->column(3, new InfoBox('新订单', 'shopping-cart', 'green', '/admin/orders', '150%'));
                $row->column(3, new InfoBox('信息', 'book', 'yellow', '/admin/articles', '2786'));
                $row->column(3, new InfoBox('文件', 'file', 'red', '/admin/files', '698726'));
            });

            $content->row(function (Row $row) {

                $row->column(6, function (Column $column) {

                    $tab = new Tab();

                    $pie = new Pie([
                        ['昨日', 250], ['上周', 650], ['上月', 800],
                    ]);

                    $tab->add('买用户', $pie);
                    $tab->add('Table', new Table());
                    $tab->add('Text', 'blablablabla....');

                    $tab->dropDown([['Orders', '/admin/orders'], ['administrators', '/admin/administrators']]);
                    $tab->title('Tabs');

                    $column->append($tab);

                    $collapse = new Collapse();

                    // 新增用户
                    $NewInsert = $this->NewInser();

                    // 用户活跃度  使用人数
                    $actives = $this->active();

                    // 用户留存
                    $keep = $this->keep();

                    // 转换率（昨日发布给料的用户数 / 昨日总登陆人数）


                    $buy = new Bar(
                        ["新增用户", "用户活跃度", "用户留存", "转换率"],
                        [
                            ['日', [$NewInsert['buy']['day'],$actives['buy']['day'],$keep['buy']['day'],2]],
                            ['周', [$NewInsert['buy']['week'],$actives['buy']['week'],$keep['buy']['week'],2]],
                            ['月', [$NewInsert['buy']['month'],$actives['buy']['month'],$keep['buy']['month'],5]],
                        ]
                    );
                    $collapse->add('买家统计', $buy);
                    $column->append($collapse);

                    // 卖家统计
                    $collapse = new Collapse();
                    $sell = new Bar(
                        ["新增用户", "用户活跃度", "用户留存", "转换率"],
                        [
                            ['日', [$NewInsert['sell']['day'],$actives['sell']['day'],$keep['sell']['day'],2]],
                            ['周', [$NewInsert['sell']['week'],$actives['sell']['week'],$keep['sell']['week'],3]],
                            ['月', [$NewInsert['sell']['month'],$actives['sell']['month'],$keep['sell']['month'],1]],
                        ]
                    );
                    $collapse->add('卖家统计', $sell);
                    $column->append($collapse);


                    $doughnut = new Doughnut([
                        ['Chrome', 700],
                        ['IE', 500],
                        ['FireFox', 400],
                        ['Safari', 600],
                        ['Opera', 300],
                        ['Navigator', 100],
                    ]);
                    $column->append((new Box('Doughnut', $doughnut))->removable()->collapsable()->style('info'));
                });

                $row->column(6, function (Column $column) {

                    $column->append(new Box('Radar', new Radar()));

                    $polarArea = new PolarArea([
                        ['Red', 300],
                        ['Blue', 450],
                        ['Green', 700],
                        ['Yellow', 280],
                        ['Black', 425],
                        ['Gray', 1000],
                    ]);
                    $column->append((new Box('Polar Area', $polarArea))->removable()->collapsable());

                    $column->append((new Box('Line', new Line()))->removable()->collapsable()->style('danger'));
                });

            });

            $headers = ['Id', 'Email', 'Name', 'Company', 'Last Login', 'Status'];
            $rows = [
                [1, 'labore21@yahoo.com', 'Ms. Clotilde Gibson', 'Goodwin-Watsica', '1997-08-13 13:59:21', 'open'],
                [2, 'omnis.in@hotmail.com', 'Allie Kuhic', 'Murphy, Koepp and Morar', '1988-07-19 03:19:08', 'blocked'],
                [3, 'quia65@hotmail.com', 'Prof. Drew Heller', 'Kihn LLC', '1978-06-19 11:12:57', 'blocked'],
                [4, 'xet@yahoo.com', 'William Koss', 'Becker-Raynor', '1988-09-07 23:57:45', 'open'],
                [5, 'ipsa.aut@gmail.com', 'Ms. Antonietta Kozey Jr.', 'Braun Ltd', '2013-10-16 10:00:01', 'open'],
            ];

            $content->row((new Box('Table', new Table($headers, $rows)))->style('info')->solid());
        });
    }



    /**
     * 用户留存
    */
    public function keep() {
        $today = date("Y-m-d", time());
        $yesterday = date("Y-m-d",strtotime("-1 day"));
        $seven = date("Y-m-d",strtotime("-7 day"));
        $thirty = date("Y-m-d",strtotime("-30 day"));
        $yesterday_uid = Clients::select('id')->where('created_at', 'like', $yesterday.'%')->get();
        $seven_uid = Clients::select('id')->where('created_at', 'like', $seven.'%')->get();
        $thirty_uid = Clients::select('id')->where('created_at', 'like', $thirty.'%')->get();
        $keep_log = Client_log::select('client_log.uid', 'is_buy', 'is_sell')->LeftJoin('profile', 'profile.uid', 'client_log.uid')->where("client_log.created_at", 'like', $today."%")->get();

        $keep['buy']['day'] = 0;
        $keep['sell']['day'] = 0;
        $keep['buy']['week'] = 0;
        $keep['sell']['week'] = 0;
        $keep['buy']['month'] = 0;
        $keep['sell']['month'] = 0;
        foreach($keep_log as $key => $value) {
            foreach($yesterday_uid as $yesterday) {
                if($value['uid'] == $yesterday['id']) {
                    if($value['is_buy'] == 1) {
                        $keep['buy']['day'] += 1;
                    }
                    if($value['is_sell'] == 1) {
                        $keep['sell']['day'] += 1;
                    }
                }
            }
            foreach($seven_uid as $yesterday) {
                if($value['uid'] == $yesterday['id']) {
                    if($value['is_buy'] == 1) {
                        $keep['buy']['day'] += 1;
                    }
                    if($value['is_sell'] == 1) {
                        $keep['sell']['day'] += 1;
                    }
                }
            }
            foreach($thirty_uid as $yesterday) {
                if($value['uid'] == $yesterday['id']) {
                    if($value['is_buy'] == 1) {
                        $keep['buy']['day'] += 1;
                    }
                    if($value['is_sell'] == 1) {
                        $keep['sell']['day'] += 1;
                    }
                }
            }
        }
        return $keep;
    }

    /**
     * 用户活跃
    */
    public function active()
    {

        // 获取时间 上个月第一天， 最后一天 上一周 上一天
        $month = date('Y-m-d H:i:s', strtotime(date('Y-m-01 00:00:00') . ' -1 month'));
        $last_month = date('Y-m-d 23:59:59', strtotime(date('Y-m-t') . ' -1 month'));
        $week = date("Y-m-d 00:00:00",mktime(0, 0 , 0,date("m"),date("d")-date("w")+1-7,date("Y")));
        $last_week = date("Y-m-d 23:59:59",mktime(23,59,59,date("m"),date("d")-date("w")+7-7,date("Y")));
        $day = date("Y-m-d 00:00:00",strtotime("-1 day"));
        $last_day = date("Y-m-d 23:59:59",strtotime("-1 day"));

        $actives['buy']['day'] = 0;
        $actives['sell']['day'] = 0;
        $actives['buy']['week'] = 0;
        $actives['sell']['week'] = 0;
        $actives['buy']['month'] = 0;
        $actives['sell']['month'] = 0;
        $active = Client_log::select('client_log.created_at', 'is_buy', 'is_sell', 'client_log.uid')->LeftJoin('profile', 'profile.uid', 'client_log.uid')->whereBetween("client_log.created_at", [$month, $last_day])->get();
//                    print_r($active->ToArray());die;
//                    $abr = [];
//                    foreach($active as $key => $value) {
//                        if (!in_array($active[$key]['uid'], $abr)) {
//                            $abr[] = $active[$key]->ToArray();
//                        } else {
//                            unset($active[$key]);
//                        }
//                    }


        foreach($active as $key => $value) {
            if($value['created_at'] > $month && $value['created_at'] < $last_month) {
                if($value['is_buy'] == 1) {
                    $actives['buy']['day'] += 1;
                }
                if ($value['is_sell'] == 1) {
                    $actives['sell']['day'] += 1;
                }
            }
            if($value['created_at'] > $week && $value['created_at'] < $last_week) {
                if($value['is_buy'] == 1) {
                    $actives['buy']['week'] += 1;
                }
                if ($value['is_sell'] == 1) {
                    $actives['sell']['week'] += 1;
                }
            }
            if($value['created_at'] > $day && $value['created_at'] < $last_day) {
                if($value['is_buy'] == 1) {
                    $actives['buy']['month'] += 1;
                }
                if ($value['is_sell'] == 1) {
                    $actives['sell']['month'] += 1;
                }
            }
        }
        return $actives;
    }

    /**
     * 新增用户
    */
    public function NewInser()
    {
        // 获取时间 上个月第一天， 最后一天 上一周 上一天
        $month = date('Y-m-d H:i:s', strtotime(date('Y-m-01 00:00:00') . ' -1 month'));
        $last_month = date('Y-m-d 23:59:59', strtotime(date('Y-m-t') . ' -1 month'));
        $week = date("Y-m-d 00:00:00",mktime(0, 0 , 0,date("m"),date("d")-date("w")+1-7,date("Y")));
        $last_week = date("Y-m-d 23:59:59",mktime(23,59,59,date("m"),date("d")-date("w")+7-7,date("Y")));
        $day = date("Y-m-d 00:00:00",strtotime("-1 day"));
        $last_day = date("Y-m-d 23:59:59",strtotime("-1 day"));
        $all = profile::select('profile.id', 'profile.uid', 'is_buy','is_sell', 'clients.created_at')
            ->LeftJoin('clients', 'profile.uid', 'clients.id')
            ->whereBetween("clients.created_at", [$month, $last_day])
            ->get();

        $NewInsert['buy']['month'] = 0;
        $NewInsert['buy']['week'] = 0;
        $NewInsert['buy']['day'] = 0;
        $NewInsert['sell']['month'] = 0;
        $NewInsert['sell']['week'] = 0;
        $NewInsert['sell']['day'] = 0;
        foreach($all as $key => $value) {
            if($value['created_at'] > $month && $value['created_at'] < $last_month) {
                if($value['is_buy'] == 1) {
                    $NewInsert['buy']['month'] += 1;
                }
                if ($value['is_sell'] == 1) {
                    $NewInsert['sell']['month'] += 1;
                }
            }
            if($value['created_at'] > $week && $value['created_at'] < $last_week) {
                if($value['is_buy'] == 1) {
                    $NewInsert['buy']['week'] += 1;
                }
                if ($value['is_sell'] == 1) {
                    $NewInsert['sell']['week'] += 1;
                }
            }
            if($value['created_at'] > $day && $value['created_at'] < $last_day) {
                if($value['is_buy'] == 1) {
                    $NewInsert['buy']['day'] += 1;
                }
                if ($value['is_sell'] == 1) {
                    $NewInsert['sell']['day'] += 1;
                }
            }
        }

        return $NewInsert;
    }

}
