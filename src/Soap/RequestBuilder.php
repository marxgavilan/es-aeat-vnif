<?php

declare(strict_types=1);

namespace Iberfacil\AeatVnif\Soap;

use DOMDocument;
use RuntimeException;

/**
 * Monta el sobre SOAP 1.1 de VNifV2Ent con DOM y nodos de texto: nada de interpolar
 * valores en cadenas XML.
 */
final class RequestBuilder
{
    public const NAMESPACE = 'http://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/burt/jdit/ws/VNifV2Ent.xsd';

    public const SOAP_NAMESPACE = 'http://schemas.xmlsoap.org/soap/envelope/';

    /**
     * @param list<array{nif: string, name: string}> $rows Nombres ya normalizados.
     */
    public function build(array $rows): string
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $envelope = $document->createElementNS(self::SOAP_NAMESPACE, 'soapenv:Envelope');
        $document->appendChild($envelope);
        $body = $envelope->appendChild($document->createElementNS(self::SOAP_NAMESPACE, 'soapenv:Body'));
        $request = $body->appendChild($document->createElementNS(self::NAMESPACE, 'VNifV2Ent'));

        foreach ($rows as $row) {
            $taxpayer = $request->appendChild($document->createElementNS(self::NAMESPACE, 'Contribuyente'));
            foreach (['Nif' => $row['nif'], 'Nombre' => $row['name']] as $tag => $value) {
                $element = $taxpayer->appendChild($document->createElementNS(self::NAMESPACE, $tag));
                $element->appendChild($document->createTextNode($value));
            }
        }

        $xml = $document->saveXML();
        if ($xml === false) {
            throw new RuntimeException('No se pudo generar el XML de la peticion VNifV2.');
        }

        return $xml;
    }
}
