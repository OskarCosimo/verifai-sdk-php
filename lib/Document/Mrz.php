<?php

namespace Verifai\Document;

use Verifai\Service;
use Verifai\Document;

/**
 * Modern documents have a Machine Readable Zone. This class is the
 * proxy between your code and the Verifai OCR service. You can get
 * an instance of this class from the Document object.
 *
 * You can create one by initializing it with a Zone
 * that contains a MRZ.
 */
class Mrz
{
    /**
     * @var Zone|null
     */
    private $zone;

    /**
     * @var array|null
     */
    private $mrzResponse;

    /**
     * @var Service|null
     */
    private $service;

    /**
     * @param Zone $zone
     * @param Service|null $service
     */
    public function __construct(Zone $zone, Service $service)
    {
        $this->zone = $zone;
        $this->service = $service;
    }

    /**
     * Returns weather the OCR has been successful
     * @return bool
     */
    public function isSuccessful(): bool
    {
        return $this->readMrz()['status'] == 'SUCCESS';
    }

    /**
     * Returns the raw OCR response from the OCR service
     * @return array|null
     */
    public function readMrz()
    {
        if ($this->mrzResponse !== null) {
            $ocrResult = $this->mrzResponse;
        } else {
            $mrz = $this->zone;
            $mrzImage = $this->getDocument()->getPartOfCardImage($mrz->getPositionInImage(), .03);
            $ocrResult = $this->service->getOcrData($mrzImage);
            $this->mrzResponse = $ocrResult;
        }
        if ($ocrResult['status'] == 'NOT_FOUND') {
            return null;
        }
        return $ocrResult;

    }

    /**
     * Returns the fields form the MRZ
     * @return array|null
     */
    public function getFields()
    {
        if ($this->isSuccessful()) {
            return $this->readMrz()['result']['fields'];
        }
        return null;
    }

    /**
     * Returns the raw fields form the MRZ
     * @return array|null
     */
    public function getFieldsRaw()
    {
        if ($this->isSuccessful()) {
            return $this->readMrz()['result']['fields_raw'];
        }
        return null;
    }

    /**
     * Returns the checksum results for the MRZ
     * @return array|null
     */
    public function getChecksums()
    {
        if ($this->isSuccessful()) {
            return $this->readMrz()['result']['checksums'];
        }
        return null;
    }

    /**
     * Returns the rotation that was required to read the MRZ
     * @return int|null
     */
    public function getRotation()
    {
        if ($this->isSuccessful()) {
            return $this->readMrz()['rotation'];
        }
        return null;
    }

    /**
     * @return Document
     */
    private function getDocument(): Document
    {
        return $this->zone->getDocument();
    }

}
