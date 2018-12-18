<?php
/**
 * Created by PhpStorm.
 * User: mikhail
 * Date: 24.09.18
 * Time: 21:43
 */

namespace App\DomainsChecker;

use Throwable;

class DCException extends \Exception
{
    public function __construct($message = "", $code = 0, Throwable $previous = null)
    {
        parent::__construct('DomainChecker: ' . $message, $code, $previous);
    }
}
