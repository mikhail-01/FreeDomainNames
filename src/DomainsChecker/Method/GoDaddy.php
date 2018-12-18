<?php
/**
 * Created by PhpStorm.
 * User: mikhail
 * Date: 25.09.18
 * Time: 14:40
 */

namespace App\DomainsChecker\Method;

use App\DomainsChecker\DCException;

class GoDaddy extends Base
{
    private $ssoKey;

    /**
     * GoDaddy constructor.
     * @param $ssoKey
     * @throws DCException
     */
    public function __construct($ssoKey)
    {
        parent::__construct(500);
        if (!is_string($ssoKey)) {
            throw new DCException('Не передан ключ для API GoDaddy!');
        }
        $this->ssoKey = $ssoKey;
    }

    /**
     * Проверка, являются ли элементы массива $names свободными доменными именами.
     * Используем API GoDaddy. За один запрос можно обработать до 500 имен, разрешено до 60 запросов в минуту.
     * На входе массив с именами для проверки длиной не более 500 элементов.
     * Возвращаем массив с проверенными свободными именами.
     * @param array $names
     * @return array
     * @throws DCException
     */
    protected function checkNextPart(array $names)
    {
        if (count($names) > 500) {
            throw new DCException('Больше 500 имен в запросе к API!');
        }

        $url = "https://api.godaddy.com/v1/domains/available?checkType=FAST";

        // готовим заголовки к http-запросу: ключ и тип содержимого (JSON)
        $header = [
            'Authorization: sso-key ' . $this->ssoKey,
            'Content-Type: application/json'
        ];

        // готовим запрос и прописываем опции
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($names));
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        //curl_setopt($ch, CURLOPT_SSL_VERIFYPEER,false);
        //curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        // выполняем запрос
        $result = curl_exec($ch);

        if (curl_errno($ch) > 0) {
            curl_close($ch);
            throw new DCException('Проблемы с вызовом API!');
        }

        curl_close($ch);

        // декодируем ответ в массив и анализируем результаты запроса
        $response = json_decode($result, true);

        if (!array_key_exists('domains', $response)) {
            throw new DCException('Проблемы с откликом API!');
        }

        // формируем список свободных имен для возврата из функции
        $freeNames = [];
        foreach ($response['domains'] as $domain) {
            if ($domain['available']) {
                $freeNames[] = $domain['domain'];
            }
        }
        sort($freeNames);   // API возвращает имена в хаотическом порядке

        return $freeNames;
    }
}
