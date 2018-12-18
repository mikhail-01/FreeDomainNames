<?php
/**
 * Created by PhpStorm.
 * User: mikhail
 * Date: 25.09.18
 * Time: 13:45
 */

namespace App\DomainsChecker\Method;

abstract class Base implements IBase
{
    private $partLen = 1;    // макс. кол-во имен за один запрос

    public function __construct($partLen = 1)
    {
        $this->partLen = $partLen;
    }

    // Проверяем имена, возвращаем проверенные
    public function checkNames(array $names)
    {
        $checkedNames = [];
        foreach ($this->namesForChecking($names) as $namesSlice) {
            $checkedNames = array_merge($checkedNames, $this->checkNextPart($namesSlice));
        }

        return $checkedNames;
    }

    abstract protected function checkNextPart(array $names);

    // Генератор порций имен для проверки.
    // Каждая порция - массив длиной $partLen
    private function namesForChecking(array $names)
    {
        $namesLen = count($names);
        for ($i = 0; $i < $namesLen; $i += $this->partLen) {
            yield array_slice($names, $i, $this->partLen);
        }
    }
}
