<?php

namespace Verifai\Document;

use Verifai\Document;

/**
 * Document objects contain zones, and the zones are represented
 * by this class.
 *
 * Every zone has a position in the form of coordinates, a title, and
 * some operations.
 * @package Verifai
 */
class Zone
{
    /**
     * @var Document|null
     */
    private $document;
    /**
     * @var string|null
     */
    private $title;
    /**
     * @var string|null
     */
    private $side;
    /**
     * @var array|null
     */
    private $coordinates;

    /**
     * Zone constructor.
     * @param $document
     * @param $zoneData
     */
    public function __construct(Document $document, array $zoneData)
    {
        $this->document = $document;
        $this->title = $zoneData['title'];
        $this->setSide($zoneData['side']);
        $this->setCoordinates($zoneData['x'], $zoneData['y'], $zoneData['width'], $zoneData['height']);
    }

    /**
     * Return if this zone is the Machine Readable Zone
     * @return bool
     */
    public function isMrz()
    {
        return strtoupper($this->getTitle()) == 'MRZ';
    }

    /**
     * Title of the zone
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * F for front, and B for back
     * @return string
     */
    public function getSide()
    {
        return $this->side;
    }

    /**
     * Change and set the side of the zone
     * @param $side
     */
    public function setSide(string $side)
    {
        $this->side = $side[0];
    }

    /**
     * Since the coordinate system of the zones is different this
     * method converts it to the xmin, ymin, xmax, ymax system.
     * @param $xmin
     * @param $ymin
     * @param $width
     * @param $height
     */
    public function setCoordinates($xmin, $ymin, $width, $height)
    {
        $mm_size = $this->document->getActualSizeMm();
        $width_mm = $mm_size[0];
        $height_mm = $mm_size[1];

        $mm_xmin = $xmin * $width_mm;
        $mm_ymin = $ymin * $height_mm;

        $mm_xmax = $mm_xmin + ($width_mm * $width);
        $mm_ymax = $mm_ymin + ($height_mm * $height);

        $xmax = $mm_xmax / $width_mm;
        $ymax = $mm_ymax / $height_mm;

        $this->coordinates = array(
            'xmin' => $xmin,
            'ymin' => $ymin,
            'xmax' => $xmax,
            'ymax' => $ymax
        );
    }

    /**
     * Returns: xmin, ymin, xmax, ymax coordinates
     * @return array
     */
    public function getPositionInImage()
    {
        return $this->coordinates;
    }

    /**
     * Returns the document
     * @return null|Document
     */
    public function getDocument() {
        return $this->document;
    }
}
