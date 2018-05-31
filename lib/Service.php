<?php
/**
 * Created by PhpStorm.
 * User: joshua
 * Date: 31/05/2018
 * Time: 14:19
 */

namespace Verifai;


class Service
{
    public $apiToken;
    public $serverUrls = array();
    public $baseApiUrl = 'https://dashboard.verifai.com/api/';
    public $sslVerify = true;

    protected $urlRoundRobbin = array('ckassifier' => 0, 'ocr' => 0);

    const VERSION = '0.1.0';

    public function addClassifierUrl($url, $skipUnreachable=false) {
        return $this->addServerUrl($url, $skipUnreachable, 'classifier');
    }

    public function addOcrUrl($url, $skipUnreachable=false) {
        return $this->addServerUrl($url, $skipUnreachable, 'ocr');
    }

    public function getModelData($id_uuid) {
        $data = $this->getFromApi('id-models', array(
            'uuid' => $id_uuid
        ));
        if ($data) {
            return $data[0];
        }
        return null;
    }

    public function getOcrData($mrzImage) {
        $tmp = tempnam('', 'verifai_mrz_image');
        imagejpeg($mrzImage, $tmp);
        $postfields = array('file' => curl_file_create($tmp));

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->getUrl('ocr'));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postfields);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);

        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->sslVerify);
        curl_setopt($ch, CURLOPT_SSL_VERIFYSTATUS, $this->sslVerify);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $this->sslVerify);


        $response = curl_exec($ch);
        curl_close ($ch);
        return $response;
    }

    public function classifyImage($image) {

    }

    public function classifyImagePath($image) {

    }

    protected function addServerUrl($url, $skipUnreachable=false, $type) {

    }

    protected function getUrl($type) {
        return 'https://ocr.verifai.docker.localhost/api/ocr/';
    }

    protected function getFromApi($path, $params) {

    }

    protected function checkServerUrl($url) {

    }
}