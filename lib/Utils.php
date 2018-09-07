<?php


namespace Verifai;


final class Response
{
    /**
     * @var string
     */
    private $uuid;
    /**
     * @var string
     */
    private $side;
    /**
     * @var array
     */
    private $cords;

    public function __construct($uuid, $side, array $cords)
    {
        $this->uuid = $uuid;
        $this->side = $side;
        $this->cords = $cords;
    }

    /**
     * @return string
     */
    public function getUuid()
    {
        return $this->uuid;
    }

    /**
     * @return string
     */
    public function getSide()
    {
        return $this->side;
    }

    /**
     * @return array
     */
    public function getCords()
    {
        return $this->cords;
    }

}
