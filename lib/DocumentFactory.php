<?php


namespace Verifai;


class DocumentFactory
{
    /**
     * Factory class for Document
     */

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
