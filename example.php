<?php

require_once 'lib/Service.php';

$service = new \Verifai\Service();
$service->apiToken = 'API_TOKEN_IN_HERE';
$service->addClassifierUrl('http://localhost:5000/api/classify/');
$service->addOcrUrl('http://localhost:5001/api/ocr/');

# Classify gd image
#$id = imagecreatefromjpeg('docs/sample_images/dutch-id-front-sample.jpg');

# With a MRZ
$id = imagecreatefromjpeg('docs/sample_images/dutch-id-back-sample.jpg');
$document = $service->classifyImage($id);

if ($document) {
    # Reading the data about the model
    $data = array(
        'model' => $document->getModel(),
        'country' => $document->getCountry(),
        'uuid' => $document->getIdUuid(),
        'position' => $document->getPositionInImage(),
    );
    print_r($data);

    # Getting the cropped image
    $cImage = $document->getCroppedImage();
    imagejpeg($cImage, 'cropped.jpg', 100);


    # Getting the MRZ part and saving it
    $mrzZone = $document->getMrzZone();
    if ($mrzZone) {
        imagejpeg($document->getPartOfCardImage($mrzZone->getPositionInImage(), .03), 'mrz.jpg');
    }

    # Getting the MRZ object and read the fields
    $mrz = $document->getMrz();
    if ($mrz) {
        print_r($mrz->getFields());
    }

    # Masking the zones on the document
    $maskedImage = $document->maskZones($document->getZones());
    imagejpeg($maskedImage, 'masked.jpg');
}