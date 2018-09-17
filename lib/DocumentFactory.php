<?php

namespace Verifai;

/**
 * Factory class for Document
 */
class DocumentFactory
{
    /**
     * Creates document
     * @param Response $response
     * @param Service $service
     * @param string $binaryJpegImage
     * @return Document
     */
    public function create(Response $response, Service $service, string $binaryJpegImage)
    {
        $document = new Document($response, $service);
        $document->loadImage($binaryJpegImage);
        return $document;
    }
}