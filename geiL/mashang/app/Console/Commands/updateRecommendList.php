<?php

namespace App\Console\Commands;


use App\models\client;
use App\models\source;
use Illuminate\Console\Command;

class updateRecommendList extends Command {
  protected $signature = 'updateRecommendList';
  protected $description = 'updateRecommendList';

  public function __construct() {
    parent::__construct();
  }

  public function handle() {
    $sourceList = source::select()->where('pack_type', '<>', 1)->where('is_recommend', 1)->get();
    foreach($sourceList as $source) {
      if (time() - strtotime($source['createtime']) >= 24 * 60 * 60) {
        source::where('sid', $source['sid'])->update(['is_recommend' => 0, 'recommend_sort' => 0]);
      }
    }
  }
}
