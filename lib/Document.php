<?php

namespace Verifai;

require_once 'Document/Zone.php';
require_once 'Document/Mrz.php';
require_once 'Utils.php';

use Verifai\Document\Mrz;
use Verifai\Document\Zone;


/*
 * Once a classification has taken place the {@see Verifai\Service} will
 * return a instance of this class.
 *
 * It represents the data we collected for you, and provides several
 * operations like getting additional information and getting a cropped
 * image of the document.
 *
 * Some operations require communication to external services.
 * Everything is lazy, and will be collected upon request. When that
 * has happened it will be cached in memory as long as the object
 * lives.
 */

class Document
{
    /**
     * Verifai Service to use to communicate
     * @var Service
     */
    private $service;
    /**
     * The document internal Verifai ID
     * @var string|null
     */
    private $idUuid;
    /**
     * The side of the ID, "F"ront or "B"ack
     * @var string|null
     */
    private $idSide;
    /**
     * Array of xmin,ymin,xmax,ymax coordinates
     * @var array|null
     */
    private $coordinates;

    /**
     * Original Image
     * @var resource|null
     */
    private $originalImage;
    /**
     * Cropped image when a crop has been triggered it will be set
     * @var resource|null
     */
    private $croppedImage;

    /**
     * @var array|null
     */
    protected $modelData;
    /**
     * @var array|null
     */
    protected $zones;
    /**
     * @var Mrz|null
     */
    protected $mrz;

    /**
     * @param $response
     * @param $service
     */
    public function __construct(Response $response, Service $service)
    {
        $this->service = $service;
        $this->idUuid = $response->getUuid();
        $this->idSide = $response->getSide();
        $this->coordinates = $response->getCoords();
    }

    /**
     * @return Service
     */
    public function getService()
    {
        return $this->service;
    }

    /**
     * Get the internal Verifai ID
     * @return string
     */
    public function getIdUuid()
    {
        return $this->idUuid;
    }

    /**
     * Get the side of the document
     * @return string
     */
    public function getIdSide()
    {
        return $this->idSide;
    }

    /**
     * Get the gd image
     * @return resource
     */
    public function getOriginalImage()
    {
        return $this->originalImage;
    }

    /**
     * Cuts out the document from the entire image and returns the
     * cropped image
     * @return resource
     */
    public function getCroppedImage()
    {
        if ($this->croppedImage != null) {
            return $this->croppedImage;
        }
        $pxCoordinates = $this->getBoundingBoxPixelCoordinates($this->getPositionInImage());
        $this->croppedImage = imagecrop($this->originalImage, $this->coordinatesArray($pxCoordinates));
        return $this->croppedImage;
    }

    /**
     * Returns the model name
     * @return string
     */
    public function getModel()
    {
        return $this->getModelData()['model'];
    }

    /**
     * Returns the Alpha-2 county code. For example "NL"
     * @return string
     */
    public function getCountry()
    {
        return $this->getModelData()['country'];
    }

    /**
     * Return the coordinates where te document is located
     * @return array|null
     */
    public function getPositionInImage()
    {
        return $this->coordinates;
    }

    /**
     * Load filecontents into the object, and use that as image
     * @param string $binaryJpeg
     */
    public function loadImage(string $binaryJpeg)
    {
        $this->originalImage = imagecreatefromstring($binaryJpeg);
        $this->croppedImage = null;
    }

    /**
     * Every document consists of a lot of parts. You can get some
     * parts of the document by giving the coordinates.
     * It returns a new image resource.
     * @param array $coordinates of xmin,ymin,xmax,ymax
     * @param float $tolerance
     * @return resource
     */
    public function getPartOfCardImage(array $coordinates, $tolerance = 0.0)
    {
        $image = $this->getCroppedImage();
        if ($tolerance > 0.0) {
            $coordinates = $this->inflateCoordinates($coordinates, $tolerance);
        }
        $pxCoordinates = $this->getBoundingBoxPixelCoordinates($coordinates, imagesx($image), imagesy($image));
        return imagecrop($image, $this->coordinatesArray($pxCoordinates));
    }

    /**
     * Inflates the coordinates with the factor. It makes sure you
     * can't inflate it more than the document is in size.
     * @param array $coordinates
     * @param float $factor
     * @return array
     */
    public function inflateCoordinates(array $coordinates, $factor)
    {
        $newCoordinates = array(
            'xmin' => $coordinates['xmin'] - $factor,
            'ymin' => $coordinates['ymin'] - $factor,
            'xmax' => $coordinates['xmax'] + $factor,
            'ymax' => $coordinates['ymax'] + $factor
        );
        foreach ($newCoordinates as $key => $value) {
            if ($value < 0) {
                $newCoordinates[$key] = 0;
            }
            if ($value > 1) {
                $newCoordinates[$key] = 1;
            }
        }
        return $newCoordinates;
    }

    /**
     * Get the pixel coordinates based on the image and the inference
     * result
     * @param $floatCoordinates
     * @param null $imWidth
     * @param null $imHeight
     * @return array with the bounding box in pixels
     */
    public function getBoundingBoxPixelCoordinates($floatCoordinates, $imWidth = null, $imHeight = null)
    {
        if ($imWidth == null and $imHeight == null) {
            $imWidth = imagesx($this->getOriginalImage());
            $imHeight = imagesy($this->getOriginalImage());
        }

        $response = array(
            'xmin' => intval($imWidth * $floatCoordinates['xmin']),
            'ymin' => intval($imHeight * $floatCoordinates['ymin']),
            'xmax' => intval($imWidth * $floatCoordinates['xmax']),
            'ymax' => intval($imHeight * $floatCoordinates['ymax'])
        );
        return $response;
    }

    /**
     * Returns a list of Document\Zone objects
     * @return array
     */
    public function getZones()
    {
        if ($this->zones === null) {
            $data = $this->getModelData();
            $this->zones = array();
            if ($data) {
                foreach ($data['zones'] as $zoneData) {
                    $this->zones[] = new Document\Zone($this, $zoneData);
                }
            }
        }
        return $this->zones;
    }

    /**
     * Returns a the width and height in mm of the document
     * @return array(
     *              0 => width,
     *              1 => height,
     *              )
     */
    public function getActualSizeMm()
    {
        $data = $this->getModelData();
        return array(floatval($data['width_mm']), floatval($data['height_mm']));
    }

    /**
     * Returns the raw model data via the Service
     * @return array|null
     */
    public function getModelData()
    {
        if (!$this->modelData) {
            $this->modelData = $this->service->getModelData($this->getIdUuid());
        }
        return $this->modelData;
    }

    /**
     * Function to mask zones and return the masked image.
     *
     * It takes a list of Zone objects, and draws black
     * squares on the coordinates of the zone.
     *
     * By default it filters out the zones that are for the other side.
     * @param Zone[] $zones
     * @param null|resource $image
     * @param bool $filterSides
     * @return resource
     */
    public function maskZones(array $zones, $image = null, bool $filterSides = true)
    {
        if ($image == null) {
            $image = $this->getCroppedImage();
        }
        $color = imagecolorallocate($image, 0, 0, 0);
        foreach ($zones as $zone) {
            if ($filterSides && $zone->getSide() != $this->getIdSide()) {
                continue;
            }
            $pxCoordinates = $this->getBoundingBoxPixelCoordinates($zone->getPositionInImage(), imagesx($image),
                imagesy($image));
            imagefilledrectangle(
                $image, $pxCoordinates['xmin'],
                $pxCoordinates['ymin'],
                $pxCoordinates['xmax'],
                $pxCoordinates['ymax'],
                $color
            );
        }
        return $image;
    }

    /**
     * Returns the zone that hold the MRZ
     * @return bool|null
     */
    public function getMrzZone()
    {
        foreach ($this->getZones() as $zone) {
            if ($zone->isMrz()) {
                return $zone;
            }
        }
        return null;
    }

    /**
     * Returns the Document\Mrz object of the getMrzZone
     * @return null|Document\Mrz
     */
    public function getMrz()
    {
        if ($this->mrz == null) {
            $zone = $this->getMrzZone();
            if ($zone !== null) {
                $this->mrz = new Document\Mrz($zone);
                return $this->mrz;
            }
        }
        if ($this->mrz !== null) {
            return $this->mrz;
        }
        return null;
    }

    /**
     * @param array $pixelCoordinates array of xmin,ymin,xmax,ymax
     * @return array of x, y, width, height
     */
    protected function coordinatesArray(array $pixelCoordinates)
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
