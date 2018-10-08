<?php

namespace Verifai;

require_once 'Document.php';
require_once 'DocumentFactory.php';

/**
 * The VerifaiService is your main component to use. It communicates
 * with various backend systems and handles all the privacy sensitive
 * data internally.
 *
 * To use the service you need to initialize it first with an API token
 * and the URL to the classifier service, and optional to the OCR
 * service.
 *
 * See {@link https://docs.verifai.com/server_docs/php-sdk-latest.html}
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
    private $serverUrls = array('classifier' => array(), 'ocr' => array());
    /**
     * @var array
     */
    private $urlRoundRobin = array('classifier' => 0, 'ocr' => 0);

    /**
     * @var DocumentFactory
     */
    private $documentFactory;

    /**
     * @param DocumentFactory $documentFactory
     */
    public function __construct(DocumentFactory $documentFactory)
    {
        $this->documentFactory = $documentFactory;
    }

    /**
     * @return string|null
     */
    private function getApiToken(): ?string
    {
        return $this->apiToken;
    }

    /**
     * @return string
     */
    private function getBaseApiUrl(): string
    {
        return $this->baseApiUrl;
    }

    /**
     * To add the URL to your local running Verifai Classifier service.
     * Please not that you need to provide the full path to the api
     * endpoint.
     *
     * If you set $skipUnreachable to true, then the url will be added,
     * even if we cannot confirm that the url belongs to a valid server
     *
     * For example: http://localhost:5000/api/classify/
     *
     * You can add multiple servers to scale up operations.
     * @param string $url
     * @param bool $skipUnreachable
     * @return bool
     */
    public function addClassifierUrl(string $url, bool $skipUnreachable = false): bool
    {
        return $this->addServerUrl($url,'classifier', $skipUnreachable);
    }

    /**
     * To add the URL to your local running Verifai OCR service.
     * Please not that you need to provide the full path to the api
     * endpoint.
     *
     * If you set $skipUnreachable to true, then the url will be added,
     * even if we cannot confirm that the url belongs to a valid server
     *
     * For example: http://localhost:5001/api/ocr/
     *
     * You can add multiple servers to scale up operations.
     * @param string $url
     * @param bool $skipUnreachable
     * @return bool
     */
    public function addOcrUrl(string $url, bool $skipUnreachable = false): bool
    {
        return $this->addServerUrl($url, 'ocr', $skipUnreachable);
    }

    /**
     * Fetch the raw data from the API for further processing.
     *
     * Note: Since it is not a public API it is subject to changes.
     * @param string $id_uuid
     * @return array|null
     */
    public function getModelData(string $id_uuid): ?array
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
     * @param resource $mrzImage
     * @return null|array
     */
    public function getOcrData($mrzImage): ?array
    {
        $response = $this->sendImage($this->getUrl('ocr'), $mrzImage);
        return $response;
    }

    /**
     * Send an image to the Verifai Classifier and get a VerifaiDocument
     * in return. If it fails to classify it will return null.
     * @param resource $image
     * @return null|Document
     */
    public function classifyImage($image): ?Document
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
            $document = $this->documentFactory->create($response, $this, stream_get_contents($handle));
            fclose($handle);
            return $document;
        }
        return null;
    }

    /**
     * Send a image to the Verifai Classifier and get a VerifaiDocument
     * in return. If it fails to classify it will return None.
     * @param string $imagePath
     * @return null|Document
     */
    public function classifyImagePath(string $imagePath): ?Document
    {
        $gdImage = imagecreatefromjpeg($imagePath);
        return $this->classifyImage($gdImage);
    }

    /**
     * @param string $url
     * @param bool $skipUnreachable
     * @param string $type
     * @return bool
     */
    private function addServerUrl(string $url, string $type, $skipUnreachable = false): bool
    {
        if ($skipUnreachable or $this->checkServerUrl($url)) {
            $this->serverUrls[$type][] = $url;
        }
        return true;
    }

    /**
     * @param string $type
     * @return string|null
     */
    private function getUrl(string $type): ?string
    {
        if($this->urlRoundRobin[$type] == count($this->serverUrls[$type])) {
            $this->urlRoundRobin[$type] = 0;
        }
        return $this->serverUrls[$type][$this->urlRoundRobin[$type]++];
    }

    /**
     * @param string $path
     * @param array $params
     * @return null|array
     */
    private function getFromApi(string $path, array $params): ?array
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
     * @param string $url
     * @return bool
     */
    private function checkServerUrl(string $url): bool
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
        // check whether we have received valid response
        if (count($matches) < 2) return false;
        $options = explode(',', str_replace(' ', '', $matches[1]));
        sort($options);
        curl_close($ch);

        // Return if the options are correct
        return $options == array('OPTIONS', 'POST');
    }

    /**
     * @param string $url
     * @param resource $image
     * @return null|array
     */
    private function sendImage(string $url, $image): ?array
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

    /**
     * curl_setopt doesn't use true anymore, but uses option 2 for ssl verification,
     * this wrapper keeps users from having to deal with those options
     * @return int
     */
    private function curlSslVerify(): int
    {
        if ($this->sslVerify) {
            return 2;
        }
        return 0;
    }
}
