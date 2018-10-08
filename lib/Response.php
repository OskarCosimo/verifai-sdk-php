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
     * @param string $uuid
     * @param string $side
     * @param array $coords
     */
    public function __construct(string $uuid, string $side, array $coords)
    {
        $this->uuid = $uuid;
        $this->side = $side;
        $this->coords = $coords;
    }

    /**
     * @return string
     */
    public function getUuid(): string
    {
        return $this->uuid;
    }

    /**
     * @return string
     */
    public function getSide(): string
    {
        return $this->side;
    }

    /**
     * @return array
     */
    public function getCoords(): array
    {
        return $this->coords;
    }

}
