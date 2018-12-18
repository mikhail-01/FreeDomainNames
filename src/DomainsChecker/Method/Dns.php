<?php
/**
 * Created by PhpStorm.
 * User: mikhail
 * Date: 25.09.18
 * Time: 14:36
 */

namespace App\DomainsChecker\Method;

class Dns extends Base
{
    // Проверка, является ли 0-й элемент массива $names свободным доменным именем.
    // Используем dns-сервер.
    // Возвращаем массив с одним именем, если оно доступно, иначе пустой массив.
    protected function checkNextPart(array $names)
    {
        $name = $names[0];
        return (gethostbyname($name) == $name) ? [$name] : [];
    }
}
