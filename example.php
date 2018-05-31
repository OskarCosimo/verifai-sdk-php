<?php

require_once 'lib/Service.php';

$service = new \Verifai\Service();
$service->apiToken = 'asdasd';
$service->sslVerify = false;
$service->addOcrUrl('https://ocr.verifai.docker.localhost/api/ocr/');

$im = imagecreatefromjpeg('docs/processed_examples/mrz.jpg');
$service->getOcrData($im);