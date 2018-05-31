<?php
/**
 * Created by PhpStorm.
 * User: joshua
 * Date: 31/05/2018
 * Time: 14:19
 */

namespace Verifai;

require_once 'Document.php';


class Service
{
    public $apiToken;
    public $serverUrls = array('cassifier' => array(), 'ocr' => array());
    public $baseApiUrl = 'https://dashboard.verifai.com/api/';
    public $sslVerify = true;

    protected $urlRoundRobbin = array('ckassifier' => 0, 'ocr' => 0);

    const VERSION = '0.1.0';

    protected function getApiToken()
    {
        return $this->apiToken;
    }

    protected function getBaseApiUrl()
    {
        return $this->baseApiUrl;
    }

    public function addClassifierUrl($url, $skipUnreachable = false)
    {
        return $this->addServerUrl($url, $skipUnreachable, 'classifier');
    }

    public function addOcrUrl($url, $skipUnreachable = false)
    {
        return $this->addServerUrl($url, $skipUnreachable, 'ocr');
    }

    public function getModelData($id_uuid)
    {
        $data = $this->getFromApi('id-models', array(
            'uuid' => $id_uuid
        ));
        if ($data) {
            return $data[0];
        }
        return null;
    }

    public function getOcrData($mrzImage)
    {
        $response = $this->sendImage($this->getUrl('ocr'), $mrzImage);
        return $response;
    }

    public function classifyImage($image)
    {
        $response = $this->sendImage($this->getUrl('classifier'), $image);

        if ($response['status'] == 'SUCCESS') {
            $handle = fopen('php://memory', 'w+');
            imagejpeg($image, $handle);
            fseek($handle, 0);
            $document = new Document($response, stream_get_contents($handle), $this);
            fclose($handle);
            return $document;
        }
        return null;
    }

    public function classifyImagePath($imagePath)
    {
        $gdImage = imagecreatefromjpeg($imagePath);
        return $this->classifyImage($gdImage);
    }

    protected function addServerUrl($url, $skipUnreachable = false, $type)
    {
        if ($skipUnreachable or $this->checkServerUrl($url)) {
            $this->serverUrls[$type][] = $url;
        }
    }

    protected function getUrl($type)
    {
        return $this->serverUrls[$type][0];
    }

    protected function getFromApi($path, $params)
    {
        $GET = http_build_query($params);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization: Token ' . $this->getApiToken()
        ));
        curl_setopt($ch, CURLOPT_URL, $this->getBaseApiUrl() . $path . '?' . $GET);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->sslVerify);
        curl_setopt($ch, CURLOPT_SSL_VERIFYSTATUS, $this->sslVerify);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $this->sslVerify);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $response = curl_exec($ch);
        return json_decode($response, true);
    }

    protected function checkServerUrl($url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->sslVerify);
        curl_setopt($ch, CURLOPT_SSL_VERIFYSTATUS, $this->sslVerify);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $this->sslVerify);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'OPTIONS');
        curl_setopt($ch, CURLOPT_HEADER, true);

        $response = curl_exec($ch);

        // Find the options
        preg_match("/Allow: ([A-Z, ]*)/", $response, $matches);
        $options = explode(',', str_replace(" ", "", $matches[1]));
        sort($options);
        curl_close($ch);

        // Return if the options are correct
        return $options == array('OPTIONS', 'POST');
    }

    protected function sendImage($url, $image)
    {
        $tmp = tempnam('', 'verifai_image');
        imagejpeg($image, $tmp);
        $postfields = array('file' => curl_file_create($tmp));

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postfields);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->sslVerify);
        curl_setopt($ch, CURLOPT_SSL_VERIFYSTATUS, $this->sslVerify);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $this->sslVerify);

        $response = curl_exec($ch);
        curl_close($ch);
        unlink($tmp);
        return json_decode($response, true);
    }
}