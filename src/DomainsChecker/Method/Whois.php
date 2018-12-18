<?php
/**
 * Created by PhpStorm.
 * User: mikhail
 * Date: 25.09.18
 * Time: 14:32
 */

namespace App\DomainsChecker\Method;

use App\DomainsChecker\DCException;

class Whois extends Base
{
    /**
     * Проверка, является ли 0-й элемент массива $names свободным доменным именем.
     * Используем whois-сервер.
     * Возвращаем массив с одним именем, если оно доступно, иначе пустой массив.
     * @param array $names
     * @return array
     * @throws DCException
     */
    protected function checkNextPart(array $names)
    {
        $name = $names[0];
        $server = 'whois.internic.net';
        if ($conn = fsockopen($server, 43)) {
            fputs($conn, $name . "\r\n");
            $output = fgets($conn, 128)[0];
            fclose($conn);
        } else {
            throw new DCException('Не могу подключиться к ' . $server . '!');
        }

        return ($output == 'N') ? [$name] : [];        // От "No matches"
    }
}
