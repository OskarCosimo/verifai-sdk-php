<?php
/**
 * Created by PhpStorm.
 * User: joshua
 * Date: 31/05/2018
 * Time: 14:21
 */

namespace Verifai;


class DocumentMrz
{
    public $zone = null;

    protected $mrzResponse;

    public function __construct($zone)
    {
        $this->zone = $zone;
    }

    public function isSuccessful()
    {
        return $this->readMrz()['status'] == 'SUCCESS';
    }

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

    public function getFields()
    {
        if ($this->isSuccessful()) {
            return $this->readMrz()['result']['fields'];
        }
        return null;
    }

    public function getFieldsRaw()
    {
        if ($this->isSuccessful()) {
            return $this->readMrz()['result']['fields_raw'];
        }
        return null;
    }

    public function getChecksums()
    {
        if ($this->isSuccessful()) {
            return $this->readMrz()['result']['checksums'];
        }
        return null;
    }

    public function getRotation()
    {
        if ($this->isSuccessful()) {
            return $this->readMrz()['rotation'];
        }
        return null;
    }

    protected function getDocument()
    {
        return $this->zone->document;
    }

    protected function getService()
    {
        return $this->zone->document->service;
    }


}