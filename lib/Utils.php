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
    private $coords;

    /**
     * Class constructor.
     * @param $uuid
     * @param $side
     * @param array $coords
     */
    public function __construct($uuid, $side, array $coords)
    {
        $this->uuid = $uuid;
        $this->side = $side;
        $this->coords = $coords;
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
    public function getCoords()
    {
        return $this->coords;
    }

}