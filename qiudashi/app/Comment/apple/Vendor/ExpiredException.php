<?php

namespace App\Comment\apple\Vendor;

use Throwable;

class ExpiredException extends \UnexpectedValueException
{
        public function __construct($message = "", $code = 0, Throwable $previous = null)
        {

             parent::__construct($message, $code, $previous);
        }
}
