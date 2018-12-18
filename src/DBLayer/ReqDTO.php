<?php
/**
 * Created by PhpStorm.
 * User: mikhail
 * Date: 05.10.18
 * Time: 10:20
 */

namespace App\DBLayer;

class ReqDTO
{
    private $id;
    private $timeStamp;
    private $method;
    private $length;
    private $position;

    public function __construct($id, $timeStamp, $method = '', $length = 0, $position = 0)
    {
        $this->id = $id;
        $this->timeStamp = $timeStamp;
        $this->method = $method;
        $this->length = $length;
        $this->position = $position;
    }

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return mixed
     */
    public function getTimeStamp()
    {
        return $this->timeStamp;
    }

    /**
     * @return mixed
     */
    public function getMethod()
    {
        return $this->method;
    }

    /**
     * @return mixed
     */
    public function getLength()
    {
        return $this->length;
    }

    /**
     * @return mixed
     */
    public function getPosition()
    {
        return $this->position;
    }

    /**
     * @param int $position
     */
    public function setPosition(int $position): void
    {
        $this->position = $position;
    }
}
