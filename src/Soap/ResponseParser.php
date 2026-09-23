<?php

declare(strict_types=1);

namespace Iberfacil\AeatVnif\Soap;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Iberfacil\AeatVnif\Data\IdentificationResult;
use Iberfacil\AeatVnif\Exceptions\DefinitiveFailure;
use InvalidArgumentException;

/**
 * Lee VNifV2Sal. Sin DTD, sin red, sin entidades externas. Los textos de un Fault no se
 * propagan: pueden llevar datos personales.
 *
 * @phpstan-type ParsedRow array{nif: string, name: ?string, result: IdentificationResult, raw: array<string, string>}
 */
final class ResponseParser
{
    public const NAMESPACE = 'http://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/burt/jdit/ws/VNifV2Sal.xsd';

    /**
     * @return list<ParsedRow>
     *
     * @throws DefinitiveFailure
     */
    public function parse(string $body): array
    {
        $document = $this->load($body);

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('soap', RequestBuilder::SOAP_NAMESPACE);
        $xpath->registerNamespace('v', self::NAMESPACE);

        if ((float) $xpath->evaluate('count(/soap:Envelope/soap:Body/soap:Fault)') > 0) {
            throw DefinitiveFailure::soapFault();
        }

        if ((float) $xpath->evaluate('count(/soap:Envelope/soap:Body/v:VNifV2Sal)') !== 1.0) {
            throw DefinitiveFailure::missingBody();
        }

        $rows = [];
        $nodes = $xpath->query('/soap:Envelope/soap:Body/v:VNifV2Sal/v:Contribuyente');

        foreach ($nodes === false ? [] : $nodes as $node) {
            if (! $node instanceof DOMElement) {
                throw DefinitiveFailure::invalidXml();
            }

            $raw = [];
            foreach (['Nif', 'Nombre', 'Resultado'] as $field) {
                if ((float) $xpath->evaluate('count(v:' . $field . ')', $node) !== 1.0) {
                    throw DefinitiveFailure::missingField($field);
                }
                $raw[$field] = trim((string) $xpath->evaluate('string(v:' . $field . ')', $node));
            }

            try {
                $result = IdentificationResult::fromAeatLabel($raw['Resultado']);
            } catch (InvalidArgumentException) {
                throw DefinitiveFailure::unknownResult();
            }

            $rows[] = [
                'nif' => mb_strtoupper($raw['Nif'], 'UTF-8'),
                'name' => $raw['Nombre'] === '' ? null : $raw['Nombre'],
                'result' => $result,
                'raw' => $raw,
            ];
        }

        return $rows;
    }

    private function load(string $body): DOMDocument
    {
        if (trim($body) === '' || stripos($body, '<!DOCTYPE') !== false) {
            throw DefinitiveFailure::invalidXml();
        }

        $previous = libxml_use_internal_errors(true);
        try {
            $document = new DOMDocument();
            $loaded = $document->loadXML($body, LIBXML_NONET);
            if (! $loaded || $document->doctype !== null) {
                throw DefinitiveFailure::invalidXml();
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        return $document;
    }
}
