<?php
/**
 * Created by PhpStorm.
 * User: mikhail
 * Date: 08.10.18
 * Time: 12:56
 */

namespace App\Model;

use App\DBLayer\DBLayer;
use App\DBLayer\ReqDTO;

class CheckingModel
{
    /** @var \App\DomainsChecker\DomainsChecker */
    private $checker;
    /** @var DBLayer */
    private $dataRepository;
    /** @var ReqDTO */
    private $reqDTO;
    private $domainNamesForChecking = [];
    private $defisBool;
    private $methodsChain;
    private $namesNumberPerRequest;
    private $requestLengthForMethod = [];
    private $freeDomainNames = [];
    private $canWeInitChecking = false;
    private $isRequestDataLoaded = false;
    private $isRequestFinalized = false;

    public function __construct($container)
    {
        $this->checker = $container['domains_checker'];
        $this->dataRepository = $container['database'];
    }

    public function addRequestLengthForMethod($methodName, $namesNumberPerRequest)
    {
        $this->requestLengthForMethod[$methodName] = $namesNumberPerRequest;
    }

    public function prepareNamesForChecking(array $firstList, array $secondList, $defisBool)
    {
        $this->domainNamesForChecking = [];
        foreach ($this->domainNamesWithoutGlobalLevel($firstList, $secondList, $defisBool) as $name) {
            $this->domainNamesForChecking[] = $name . '.com';
        }
        $this->domainNamesForChecking = array_unique($this->domainNamesForChecking);
        sort($this->domainNamesForChecking);
        $this->defisBool = $defisBool;
        $this->canWeInitChecking = true;
        return count($this->domainNamesForChecking);
    }

    /**
     * @param $methodString
     * @return \App\DBLayer\ReqDTO
     * @throws \App\DBLayer\DBLException
     * @throws \Exception
     */
    public function initChecking($methodString)
    {
        if (!$this->canWeInitChecking) {
            throw new \Exception('Попытка инициализировать запрос без установки параметров');
        }
        $this->canWeInitChecking = false;
        return $this->dataRepository->initRequest($this->domainNamesForChecking, $this->defisBool, $methodString);
    }

    /**
     * @param $reqId
     * @param $timeStamp
     * @throws \App\DBLayer\DBLException
     * @throws \Exception
     */
    public function loadCheckingRequest($reqId, $timeStamp)
    {
        $this->reqDTO = $this->dataRepository->loadRequest($reqId, $timeStamp);
        $this->methodsChain = $this->getMethodsChain($this->reqDTO->getMethod());
        $this->namesNumberPerRequest = $this->getNamesNumberPerRequest();
        $this->freeDomainNames = [];
        $this->isRequestDataLoaded = true;
        $this->isRequestFinalized = false;
    }

    /**
     * @throws \App\DBLayer\DBLException
     * @throws \App\DomainsChecker\DCException
     * @throws \Exception
     */
    public function checkNextPart()
    {
        if ($this->isCheckingComplete()) {
            return;
        }
        if (!$this->isRequestDataLoaded) {
            throw new \Exception('Попытка работать с запросом без загрузки его данных');
        }

        $names = $this->dataRepository->readNames($this->reqDTO, $this->namesNumberPerRequest);
        foreach ($this->methodsChain as $methodName) {
            $names = $this->checker->getFreeNames($names, $methodName);
        }
        $this->freeDomainNames = array_merge($this->freeDomainNames, $names);
        sort($this->freeDomainNames);

        $this->reqDTO = $this->dataRepository->shiftPosition($this->reqDTO, $this->namesNumberPerRequest);
    }

    public function getCheckedFreeNames()
    {
        return $this->freeDomainNames;
    }

    /**
     * @throws \App\DBLayer\DBLException
     */
    public function finalizeIfComplete()
    {
        if ($this->isCheckingComplete() && !$this->isRequestFinalized) {
            $this->dataRepository->finalizeRequest($this->reqDTO);
            $this->isRequestFinalized = true;
        }
    }

    public function getNextPos()
    {
        return $this->reqDTO->getPosition();
    }

    public function isCheckingComplete()
    {
        return $this->reqDTO->getPosition() == $this->reqDTO->getLength();
    }

    /**
     * Возвращает минимальное кол-во имен для обработки за запрос для цепочки методов
     * @return integer
     */
    private function getNamesNumberPerRequest()
    {
        return array_reduce(
            $this->methodsChain,
            function ($carry, $item) {
                return min($carry, $this->requestLengthForMethod[$item]);
            },
            1000000000
        );
    }

    // Генератор доменных имен перемножением двух списков слов в прямом и обратном варианте
    // Так же учитывает и "одинарные" имена из каждого списка.
    // Если $defis == true, кроме простого слияния использует и слияние через дефис.
    private function domainNamesWithoutGlobalLevel(array $words1, array $words2, $defis = false)
    {
        foreach ($words1 as $word1) {
            yield $word1;
            foreach ($words2 as $word2) {
                yield $word1 . $word2;
                yield $word2 . $word1;
                if ($defis) {
                    yield $word1 . '-' . $word2;
                    yield $word2 . '-' . $word1;
                }
            }
        }
        foreach ($words2 as $word2) {
            yield $word2;
        }
    }

    /**
     * Разбиваем цепочки методов вида 'methodA-methodB-methodN'
     * @param string $methodString
     * @return array
     * @throws \Exception
     */
    private function getMethodsChain($methodString)
    {
        if (strlen($methodString) == 0) {
            throw new \Exception('Пустое имя метода');
        }
        $result = [];
        $methodsForCheck = explode('-', $methodString);
        foreach ($methodsForCheck as $methodName) {
            if (isset($this->requestLengthForMethod[$methodName])) {
                $result[] = $methodName;
            } else {
                throw new \Exception("Не зарегистрирована длина запроса для метода '$methodName'!");
            }
        }
        return $result;
    }
}
