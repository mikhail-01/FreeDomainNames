<?php

namespace App\Controller;

use App\Model\CheckingModel;
use Slim\Http\Request;
use Slim\Http\Response;

class MainController
{
    /** @var CheckingModel */
    private $checkingModel;

    public function __construct($container)
    {
         $this->checkingModel = $container['checking_model'];
    }

    /**
     * Маршрут для запроса start_request
     * Получаем два списка имен, флаг дефиса, метод для проверки
     * Возвращаем код завершения (не 0 - ошибка, тогда еще сообщение),
     * id запроса, timestamp запроса
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */
    public function startRequest(
        Request $request,
        Response $response,
        /** @noinspection PhpUnusedParameterInspection */ $args
    ) {
        $firstList = $this->wordsToArray($request->getParam('list1'));
        $secondList = $this->wordsToArray($request->getParam('list2'));
        $defisBool = $request->getParam('defis');
        $method = $request->getParam('method');

        $namesLen = $this->checkingModel->prepareNamesForChecking($firstList, $secondList, $defisBool);

        if ($namesLen == 0) {
            return $response->withJson(['code' => 1, 'msg' => 'Нет имен для проверки']);
        }

        try {
            $dto = $this->checkingModel->initChecking($method);
            $res = [
                'req_id' => $dto->getId(),
                'ts' => $dto->getTimeStamp(),
                'len' => $namesLen,
                'code' => 0
            ];
        } catch (\Exception $e) {
            $res = [
                'code' => 11,
                'msg' => 'Не удается создать запрос: ' . $e->getMessage()
            ];
        }
        return $response->withJson($res);
    }

    /**
     * Маршрут для запроса get_next_part
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */
    public function getNextPart(
        Request $request,
        Response $response,
        /** @noinspection PhpUnusedParameterInspection */ $args
    ) {
        $reqId = $request->getParam('req_id');
        $timeStamp = $request->getParam('ts');
        $res = [];

        try {
            $this->checkingModel->loadCheckingRequest($reqId, $timeStamp);
            $this->checkingModel->checkNextPart();
            $this->checkingModel->finalizeIfComplete();
            $freeNames = $this->checkingModel->getCheckedFreeNames();
            $res['code'] = 0;
            $res['nextPos'] = $this->checkingModel->getNextPos();
            $res['freeNames'] = $freeNames;
            $res['complete'] = $this->checkingModel->isCheckingComplete();
        } catch (\Exception $e) {
            $res = $this->convertExceptionToArray($e);
        }
        return $response->withJson($res);
    }

    // Преобразуем строку в массив слов, предварительно переведя в нижний регистр.
    // Все, кроме лат.букв, цифр и дефиса, считаем разделителями.
    // Убираем из массива дубликаты и оставляем не более 50 первых слов.
    private function wordsToArray($s = "")
    {
        $matches = array();
        preg_match_all('!([a-z0-9-]+)!', strtolower($s), $matches);
        return array_slice(array_unique($matches[0]), 0, 50);
    }

    private function convertExceptionToArray(\Exception $e)
    {
        $res['code'] = $e->getCode();
        $res['msg'] = $e->getMessage();
        return $res;
    }
}
