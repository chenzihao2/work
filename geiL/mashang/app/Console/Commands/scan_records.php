<?php
namespace App\Console\Commands;


use App\models\tmp_records;
use Illuminate\Console\Command;

class scan_records extends Command {

    protected $signature = 'scan_records';

    protected $description = 'scan_records';

    public function __construct() {
        parent::__construct();
    }

    public function handle() {
        tmp_records::lose_weight();
    }
}
