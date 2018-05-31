<?php
/**
 * Created by PhpStorm.
 * User: joshua
 * Date: 31/05/2018
 * Time: 14:21
 */

namespace Verifai;

require_once 'DocumentZone.php';
require_once 'DocumentMrz.php';

class Document
{
    public $service = null;
    public $idUuid = null;
    public $idSide = null;
    public $coordinates = null;

    public $image = null;
    public $croppedImage = null;

    protected $modelData = null;
    protected $zones = null;
    protected $mrz = null;

    public function __construct($response, $binaryJpegImage, $service)
    {
        $this->service = $service;
        $this->idUuid = $response['uuid'];
        $this->idSide = $response['side'];
        $this->coordinates = $response['coords'];
        $this->loadImage($binaryJpegImage);
    }

    public function getService()
    {
        return $this->service;
    }

    public function getIdUuid()
    {
        return $this->idUuid;
    }

    public function getIdSide()
    {
        return $this->idSide;
    }

    public function getImage()
    {
        return $this->image;
    }

    public function getCroppedImage()
    {
        if ($this->croppedImage != null) {
            return $this->croppedImage;
        }
        $pxCoords = $this->getBoundingBoxPixelCoordinates($this->getPositionInImage());
        $this->croppedImage = imagecrop($this->image, $this->coordinatesArray($pxCoords));
        return $this->croppedImage;
    }

    public function getModel()
    {
        return $this->getModelData()['model'];
    }

    public function getCountry()
    {
        return $this->getModelData()['country'];
    }

    public function getPositionInImage()
    {
        return $this->coordinates;
    }

    public function loadImage($binaryJpeg)
    {
        $tmp = tempnam('', 'verifai_image');
        file_put_contents($tmp, $binaryJpeg);
        $this->image = imagecreatefromjpeg($tmp);
        unlink($tmp);
    }

    public function getPartOfCardImage($coordinates, $tolerance = 0)
    {
        $image = $this->getCroppedImage();
        if ($tolerance > 0) {
            $coordinates = $this->inflateCoordinates($coordinates, $tolerance);
        }
        $pxCoords = $this->getBoundingBoxPixelCoordinates($coordinates, imagesx($image), imagesy($image));
        return imagecrop($image, $this->coordinatesArray($pxCoords));
    }

    public function inflateCoordinates($coordinates, $factor)
    {
        $newCoords = array(
            'xmin' => $coordinates['xmin'] - $factor,
            'ymin' => $coordinates['ymin'] - $factor,
            'xmax' => $coordinates['xmax'] + $factor,
            'ymax' => $coordinates['ymax'] + $factor
        );
        foreach ($newCoords as $key => $value) {
            if ($value < 0) {
                $newCoords[$key] = 0;
            }
            if ($value > 1) {
                $newCoords[$key] = 1;
            }
        }
        return $newCoords;
    }

    public function getBoundingBoxPixelCoordinates($floatCoordinates, $imWidth = null, $imHeight = null)
    {
        if ($imWidth == null and $imHeight == null) {
            $imWidth = imagesx($this->getImage());
            $imHeight = imagesy($this->getImage());
        }

        $response = array(
            'xmin' => intval($imWidth * $floatCoordinates['xmin']),
            'ymin' => intval($imHeight * $floatCoordinates['ymin']),
            'xmax' => intval($imWidth * $floatCoordinates['xmax']),
            'ymax' => intval($imHeight * $floatCoordinates['ymax'])
        );
        return $response;
    }

    public function getZones()
    {
        if ($this->zones === null) {
            $data = $this->getModelData();
            $this->zones = array();
            if ($data) {
                foreach ($data['zones'] as $zoneData) {
                    $this->zones[] = new DocumentZone($this, $zoneData);
                }
            }
        }
        return $this->zones;
    }

    public function getActualSizeMm()
    {
        $data = $this->getModelData();
        return array(floatval($data['width_mm']), floatval($data['height_mm']));
    }

    public function getModelData()
    {
        if (!$this->modelData) {
            $this->modelData = $this->getService()->getModelData($this->getIdUuid());
        }
        return $this->modelData;
    }

    public function maskZones($zones, $image = null, $filterSides = true)
    {
        if ($image == null) {
            $image = $this->getCroppedImage();
        }
        $color = imagecolorallocate($image, 0, 0, 0);
        foreach ($zones as $zone) {
            if ($filterSides && $zone->getSide() != $this->getIdSide()) {
                continue;
            }
            $pxCoords = $this->getBoundingBoxPixelCoordinates($zone->getPositionInImage(), imagesx($image), imagesy($image));
            imagefilledrectangle($image, $pxCoords['xmin'], $pxCoords['ymin'], $pxCoords['xmax'], $pxCoords['ymax'], $color);
        }
        return $image;
    }

    public function getMrzZone()
    {
        foreach ($this->getZones() as $zone) {
            if ($zone->isMrz()) {
                return $zone;
            }
        }
        return null;
    }

    public function getMrz()
    {
        if ($this->mrz == null) {
            $zone = $this->getMrzZone();
            if ($zone !== null) {
                $this->mrz = new DocumentMrz($zone);
                return $this->mrz;
            }
        }
        if ($this->mrz !== null) {
            return $this->mrz;
        }
        return null;
    }

    protected function coordinatesArray($pixelCoordinates)
    {
        $response = array(
            'x' => $pixelCoordinates['xmin'],
            'y' => $pixelCoordinates['ymin'],
            'width' => $pixelCoordinates['xmax'] - $pixelCoordinates['xmin'],
            'height' => $pixelCoordinates['ymax'] - $pixelCoordinates['ymin'],
        );
        return $response;
    }
}