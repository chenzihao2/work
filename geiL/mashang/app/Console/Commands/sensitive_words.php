<?php
namespace App\Console\Commands;


use App\models\source_sensitives;
use Illuminate\Console\Command;

class sensitive_words extends Command {

    protected $signature = 'sensitive_words';

    protected $description = 'sensitive_words';

    public function __construct() {
        parent::__construct();
    }

    public function handle() {
        $start = time();
        while(true) {
            $sid = source_sensitives::pop_source();
            if ($sid) {
                source_sensitives::filter($sid);
            }
            $exec_time = time() - $start;
            if ($exec_time > 55) {
                exit;
            }
        }
    }
}
