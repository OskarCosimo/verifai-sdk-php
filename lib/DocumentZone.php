<?php
/**
 * Created by PhpStorm.
 * User: joshua
 * Date: 31/05/2018
 * Time: 14:21
 */

namespace Verifai;


class DocumentZone
{
    public $document = null;
    public $title = null;
    public $side = null;
    public $coordinates = null;

    public function __construct($document, $zoneData) {
        $this->document = $document;
        $this->title = $zoneData['title'];
        $this->setSide($zoneData['side']);
        $this->setCoordinates($zoneData['x'], $zoneData['y'], $zoneData['width'], $zoneData['height']);
    }

    public function isMrz() {
        return strtoupper($this->getTitle()) == 'MRZ';
    }

    public function getTitle() {
        return $this->title;
    }

    public function getSide() {
        return $this->side;
    }

    public function setSide($side) {
        $this->side = $side[0];
    }

    public function setCoordinates($xmin, $ymin, $width, $height) {
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

    public function getPositionInImage() {
        return $this->coordinates;
    }
}