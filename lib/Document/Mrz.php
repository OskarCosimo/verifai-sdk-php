<?php

namespace Verifai\Document;


/**
 * Modern documents have a Machine Readable Zone. This class is the
 * proxy between your code and the Verifai OCR service. You can get
 * an instance of this class from the Document object.
 *
 * You can create one by initializing it with a Zone
 * that contains a MRZ.
 * @package Verifai
 */
class Mrz
{
    /**
     * @var Zone|null
     */
    public $zone;

    /**
     * @var array|null
     */
    protected $mrzResponse;

    /**
     * Mrz constructor.
     * @param $zone
     */
    public function __construct(Zone $zone)
    {
        $this->zone = $zone;
    }

    /**
     * Returns weather the OCR has been successful
     * @return bool
     */
    public function isSuccessful()
    {
        return $this->readMrz()['status'] == 'SUCCESS';
    }

    /**
     * Returns the raw OCR response form the OCR service
     * @return array|null
     */
    public function readMrz()
    {
        if ($this->mrzResponse !== null) {
            $ocrResult = $this->mrzResponse;
        } else {
            $mrz = $this->zone;
            $mrzImage = $this->getDocument()->getPartOfCardImage($mrz->getPositionInImage(), .03);
            $ocrResult = $this->getService()->getOcrData($mrzImage);
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
     * @return integer|null
     */
    public function getRotation()
    {
        if ($this->isSuccessful()) {
            return $this->readMrz()['rotation'];
        }
        return null;
    }

    /**
     * @return \Verifai\Document
     */
    protected function getDocument()
    {
        return $this->zone->getDocument();
    }

    /**
     * @return \Verifai\Service
     */
    protected function getService()
    {
        return $this->getDocument()->getService();
    }


}
