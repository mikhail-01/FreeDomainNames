<?php
/**
 * Created by PhpStorm.
 * User: mikhail
 * Date: 30.09.18
 * Time: 20:00
 */

namespace App\DBLayer;

use PDO;
use FluentPDO;

class DBLayer
{
    private $fluentPdo;

    public function __construct($host, $db, $user, $pass)
    {
        $charset = 'utf8';
        $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
        $opt = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_PERSISTENT => true
        ];
        $pdo = new PDO($dsn, $user, $pass, $opt);
        $this->fluentPdo = new FluentPDO($pdo);
    }

    /**
     * Сохраняем данные нового запроса для последуещей обработки, возвращаем id и таймстамп
     * @param array $names
     * @param $defisBool
     * @param $method
     * @return ReqDTO
     * @throws DBLException
     */
    public function initRequest(array $names, $defisBool, $method)
    {
        $this->createTables();  // создаем и инициализируем таблицы, если нужно
        $this->cleanTables();   // очищаем таблицы от старых записей, если нужно
        // сохраняем данные о новом запросе, получаем id и таймстамп
        $dto = $this->addNewRequest($defisBool, $method, count($names));
        // сохраняем список доменных имен для проверки по этому запросу
        $this->writeNames($dto->getId(), $names);
        // возвращаем id и таймстамп запроса для последующих обращений
        return $dto;
    }

    /**
     * Получаем инфу о запросе id, проверяем соответствие id и таймстампа
     * @param $id
     * @param $timeStamp
     * @return ReqDTO
     * @throws DBLException
     */
    public function loadRequest($id, $timeStamp)
    {
        $row = $this->fluentPdo->from('dc_req', $id)->fetch();
        if (!$row) {
            throw new DBLException("Запрос $id не существует!");
        }
        $newTimestamp = $row['ts'];
        if ($timeStamp != $newTimestamp) {
            throw new DBLException('Запрос сомнительного авторства!');
        }
        return new ReqDTO($id, $timeStamp, $row['method'], $row['len'], $row['next_pos']);
    }

    /**
     * Получаем для запроса id слайс сохраненных из $namesNumber имен со $startPos
     * @param ReqDTO $request
     * @param $namesNumber
     * @return array
     * @throws DBLException
     */
    public function readNames(ReqDTO $request, $namesNumber)
    {
        $newPos = $request->getPosition() + $namesNumber;
        try {
            $query = $this->fluentPdo
                ->from('dc_names')
                ->select('d_name')
                ->where('req_id', $request->getId())
                ->where('name_num >= ?', $request->getPosition())
                ->where('name_num < ?', $newPos);
            $names = [];
            foreach ($query as $row) {
                $names[] = $row['d_name'];
            }
        } catch (\Exception $e) {
            throw new DBLException(
                "Для запроса {$request->getId()} не могу прочитать имена с {$request->getPosition()}"
                . " по {$newPos}"
            );
        }
        return $names;
    }

    /**
     * @param ReqDTO $request
     * @param $delta
     * @return ReqDTO
     * @throws DBLException
     */
    public function shiftPosition(ReqDTO $request, $delta)
    {
        $newPos = $request->getPosition() + $delta;
        $newPos = $newPos < 0 ? 0 : $newPos;
        $newPos = $newPos > $request->getLength() ? $request->getLength() : $newPos;
        try {
            $this->fluentPdo->update('dc_req', ['next_pos' => $newPos], $request->getId())->execute();
        } catch (\Exception $e) {
            throw new DBLException("Для запроса {$request->getId()} не могу поменять позицию");
        }
        $request->setPosition($newPos);
        return $request;
    }

    /**
     * Удаляем из таблиц связанные с запросом $id данные
     * @param ReqDTO $request
     * @throws DBLException
     */
    public function finalizeRequest(ReqDTO $request)
    {
        try {
            $this->fluentPdo->deleteFrom('dc_names')->where('req_id', $request->getId())->execute();
            $this->fluentPdo->deleteFrom('dc_req', $request->getId())->execute();
        } catch (\Exception $e) {
            throw new DBLException('Проблемы с удалением записей при финализации запроса');
        }
    }

    private function getTimestampNow()
    {
        return date('YmdHis');
    }

    private function createTables()
    {
        $pdo = $this->fluentPdo->getPdo(); // FluentPDO не умеет в создание таблиц, работаем через PDO
        $sql = /** @lang mysql */
            'CREATE TABLE IF NOT EXISTS dc_req (
            id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            ts CHAR(14),
            defis BOOL DEFAULT FALSE,
            method CHAR(20),
            len INT DEFAULT 0,
            next_pos INT DEFAULT 0,
            INDEX (ts)
            );';
        $pdo->query($sql);
        $sql = /** @lang mysql */
            'CREATE TABLE IF NOT EXISTS dc_names (
            req_id INT,
            name_num INT, 
            d_name CHAR(70),
            PRIMARY KEY num_in_req (req_id, name_num)
            );';
        $pdo->query($sql);
        $sql = /** @lang mysql */
            'CREATE TABLE IF NOT EXISTS dc_clean (
            last_ts CHAR(14),
            delta INT
            );';
        $pdo->query($sql);
        return;
    }

    // Подчищаем старые записи
    private function cleanTables()
    {
        $row = $this->fluentPdo->from('dc_clean')->fetch();
        $lastCleaningTimestamp = $row['last_ts'];
        $cleaningInterval = $row['delta'];
        $nowTimestamp = $this->getTimestampNow();
        if (!$cleaningInterval) { // заполняем строку, если пустая таблица
            $this->fluentPdo->insertInto('dc_clean', ['last_ts' => $nowTimestamp, 'delta' => 1000000])->execute();
            return;
        }
        if ($nowTimestamp - $lastCleaningTimestamp < $cleaningInterval) {
            return;
        }
        $query = $this->fluentPdo->from('dc_req')->where('ts <= ?', $lastCleaningTimestamp);
        foreach ($query as $row) {
            $id = $row['id'];
            $this->fluentPdo->deleteFrom('dc_names')->where('req_id', $id)->execute();
        }
        $this->fluentPdo->deleteFrom('dc_req')->where('ts <= ?', $lastCleaningTimestamp)->execute();
        $this->fluentPdo->update('dc_clean', ['last_ts' => $nowTimestamp])->execute();
    }

    /**
     * Добавляем данные о новом запросе в dc_req
     * @param $defisBool
     * @param $method
     * @param $namesNum
     * @return ReqDTO
     * @throws DBLException
     */
    private function addNewRequest($defisBool, $method, $namesNum)
    {
        $timeStamp = $this->getTimestampNow();
        $values = [
            'ts' => $timeStamp,
            'defis' => $defisBool ? 1 : 0,
            'method' => $method,
            'len' => $namesNum
        ];
        $id = $this->fluentPdo->insertInto('dc_req', $values)->execute();
        if (!$id) {
            throw new DBLException('Ошибка вставки в dc_req!');
        }
        return new ReqDTO($id, $timeStamp);
    }

    private function writeNames($id, array $names)
    {
        for ($i = 0; $i < count($names); $i++) {
            $this->fluentPdo->insertInto(
                'dc_names',
                ['req_id' => $id, 'name_num' => $i, 'd_name' => $names[$i]]
            )->execute();
        }
        return;
    }
}
