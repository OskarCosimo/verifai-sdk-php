<?php

namespace Verifai;

require_once 'Document.php';


/**
 * The VerifaiService is your main component to use. It communicates
 * with various backend systems and handles all the privacy sensitive
 * data internally.
 *
 * To use the service you need to initialize it first with a API token
 * and the URL to the classifier service, and optional to the OCR
 * service.
 *
 * See https://docs.verifai.com/server_docs/php-sdk-latest.html
 * @package Verifai
 */
class Service
{
    /**
     * Registers the version
     */
    const VERSION = '0.1.0';

    /**
     * API Token to communicate with Verifai Backend
     * @var string
     */
    public $apiToken;
    /**
     * Endpoint where the Verifai Backend is located
     * @var string
     */
    public $baseApiUrl = 'https://dashboard.verifai.com/api/';
    /**
     * Weather or not to check the SSL certificates while communicating
     * @var bool
     */
    public $sslVerify = true;

    /**
     * @var array
     */
    protected $serverUrls = array('classifier' => array(), 'ocr' => array());
    /**
     * @var array
     */
    protected $urlRoundRobbin = array('classifier' => 0, 'ocr' => 0);

    /**
     * @return string|null
     */
    protected function getApiToken()
    {
        return $this->apiToken;
    }

    /**
     * @return string
     */
    protected function getBaseApiUrl()
    {
        return $this->baseApiUrl;
    }

    /**
     * To add the URL to your local running Verifai Classifier service.
     * Please not that you need to provide the full path to the api
     * endpoint.
     *
     * For example: http://localhost:5000/api/classify/
     *
     * You can add multiple servers to scale up operations.
     * @param $url
     * @param bool $skipUnreachable
     * @return bool
     */
    public function addClassifierUrl(string $url, $skipUnreachable = false)
    {
        return $this->addServerUrl($url, $skipUnreachable, 'classifier');
    }

    /**
     * To add the URL to your local running Verifai OCR service.
     * Please not that you need to provide the full path to the api
     * endpoint.
     *
     * For example: http://localhost:5001/api/ocr/
     *
     * You can add multiple servers to scale up operations.
     * @param $url
     * @param bool $skipUnreachable
     * @return bool
     */
    public function addOcrUrl(string $url, $skipUnreachable = false)
    {
        return $this->addServerUrl($url, $skipUnreachable, 'ocr');
    }

    /**
     * Fetch the raw data from the API for further processing.
     *
     * Note: Since it is not a public API it is subject to changes.
     * @param $id_uuid
     * @return array|null
     */
    public function getModelData(string $id_uuid)
    {
        $data = $this->getFromApi('id-models', array(
            'uuid' => $id_uuid
        ));
        if ($data) {
            return $data[0];
        }
        return null;
    }

    /**
     * Sends the mrz_image (Image) to the Verifai OCR service, and
     * returns the raw response.
     * @param $mrzImage
     * @return array
     */
    public function getOcrData($mrzImage)
    {
        $response = $this->sendImage($this->getUrl('ocr'), $mrzImage);
        return $response;
    }

    /**
     * Send a image to the Verifai Classifier and get a VerifaiDocument
     * in return. If it fails to classify it will return null.
     * @param $image
     * @return null|Document
     */
    public function classifyImage($image)
    {
        $json_response = $this->sendImage($this->getUrl('classifier'), $image);

        if ($json_response['status'] == 'SUCCESS') {
            $handle = fopen('php://memory', 'w+');
            imagejpeg($image, $handle);
            fseek($handle, 0);
            $uuid = $json_response['uuid'];
            $side = $json_response['side'];
            $coords = $json_response['coords'];
            $response = new Response($uuid, $side, $coords);
            $document = DocumentFactory::create($response, $this, stream_get_contents($handle));
            fclose($handle);
            return $document;
        }
        return null;
    }

    /**
     * Send a image to the Verifai Classifier and get a VerifaiDocument
     * in return. If it fails to classify it will return None.
     * @param $imagePath
     * @return null|Document
     */
    public function classifyImagePath(string $imagePath)
    {
        $gdImage = imagecreatefromjpeg($imagePath);
        return $this->classifyImage($gdImage);
    }

    /**
     * @param $url
     * @param bool $skipUnreachable
     * @param $type
     * @return bool
     */
    protected function addServerUrl(string $url, $skipUnreachable = false, string $type)
    {
        if ($skipUnreachable or $this->checkServerUrl($url)) {
            $this->serverUrls[$type][] = $url;
        }
        return true;
    }

    /**
     * @param $type
     * @return string|null
     */
    protected function getUrl(string $type)
    {
        return $this->serverUrls[$type][0];
    }

    /**
     * @param $path
     * @param $params
     * @return mixed
     */
    protected function getFromApi(string $path, array $params)
    {
        $GET = http_build_query($params);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization: Token ' . $this->getApiToken()
        ));

        $sslVerify = $this->curlSslVerify();

        curl_setopt($ch, CURLOPT_URL, $this->getBaseApiUrl() . $path . '?' . $GET);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $sslVerify);
        curl_setopt($ch, CURLOPT_SSL_VERIFYSTATUS, $sslVerify);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $sslVerify);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $response = curl_exec($ch);
        return json_decode($response, true);
    }

    /**
     * @param $url
     * @return bool
     */
    protected function checkServerUrl(string $url)
    {
        $ch = curl_init();

        $sslVerify = $this->curlSslVerify();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $sslVerify);
        curl_setopt($ch, CURLOPT_SSL_VERIFYSTATUS, $sslVerify);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $sslVerify);
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

    /**
     * @param $url
     * @param $image
     * @return mixed
     */
    protected function sendImage(string $url, $image)
    {
        $tmp = tempnam('', 'verifai_image');
        imagejpeg($image, $tmp);
        $postfields = array('file' => curl_file_create($tmp));

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postfields);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        $sslVerify = $this->curlSslVerify();

        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $sslVerify);
        curl_setopt($ch, CURLOPT_SSL_VERIFYSTATUS, $sslVerify);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $sslVerify);

        $response = curl_exec($ch);
        curl_close($ch);
        unlink($tmp);
        return json_decode($response, true);
    }

    private function curlSslVerify()
    {
        if ($this->sslVerify) {
            return 2;
        }
        return 0;
    }
}
