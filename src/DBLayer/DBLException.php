<?php
/**
 * Created by PhpStorm.
 * User: mikhail
 * Date: 30.09.18
 * Time: 20:23
 */

namespace App\DBLayer;

use Throwable;

class DBLException extends \Exception
{
    public function __construct($message = "", $code = 0, Throwable $previous = null)
    {
        parent::__construct('DBLayer: ' . $message, $code, $previous);
    }
}
