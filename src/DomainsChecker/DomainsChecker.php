<?php
/**
 * Created by PhpStorm.
 * User: mikhail
 * Date: 24.09.18
 * Time: 17:49
 */

namespace App\DomainsChecker;

class DomainsChecker
{
    private $methods = [];  // [ 'method_sign' => \App\DomainChecker\Method\IBase method ]

    /**
     * Регистрируем метод проверки доменных имен
     * @param string $methodName
     * @param $method
     */
    public function addMethod(string $methodName, $method)
    {
        $this->methods[$methodName] = $method;
    }

    /**
     * Проверяем доменные имена $names на свободность
     * Используем метод $method
     * Возвращаем массив незанятых доменных имен
     * @param array $names
     * @param string $methodName
     * @return array
     * @throws DCException
     */
    public function getFreeNames(array $names, $methodName = 'godaddy')
    {
        if (strlen($methodName) == 0) {
            throw new DCException('Пустое имя метода');
        }
        if (!isset($this->methods[$methodName])) {
            throw new DCException("Не зарегистрирован обработчик метода '$methodName'");
        }
        if (count($names) == 0) {
            return [];
        }
        /** @noinspection PhpUndefinedMethodInspection */
        return $this->methods[$methodName]->checkNames($names);
    }
}
