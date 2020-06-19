<?php

namespace App\Console\Commands;


use App\models\client;
use App\models\source;
use Illuminate\Console\Command;

class delRecommend extends Command {
  protected $signature = 'delRecommend';
  protected $description = 'delRecommend';

  public function __construct() {
    parent::__construct();
  }

  public function handle() {
    $sourceList = source::select()->where('pack_type', '<>', 1)->where('is_recommend', 1)->get();
    foreach($sourceList as $source) {
      if ($source['pack_type'] == 3) {
        if ($source['play_end'] == 1) {
          if (time() >= $source['play_time'] + 60 * 60) {
            source::where('sid', $source['sid'])->update(['is_recommend' => 0, 'recommend_sort' => 0]);
          }
        }
      }

      if ($source['pack_type'] == 2 || $source['pack_type'] == 3) {
        if ($source['free_watch'] == 1 && time() >= $source['play_time'] + 3 * 3600) {
          source::where('sid', $source['sid'])->update(['is_recommend' => 0, 'recommend_sort' => 0]);
        }
      }

    }
  }
}
