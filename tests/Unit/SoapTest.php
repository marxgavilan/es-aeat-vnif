<?php

declare(strict_types=1);

namespace Iberfacil\AeatVnif\Tests\Unit;

use Iberfacil\AeatVnif\Data\IdentificationResult;
use Iberfacil\AeatVnif\Exceptions\DefinitiveFailure;
use Iberfacil\AeatVnif\Soap\RequestBuilder;
use Iberfacil\AeatVnif\Soap\ResponseParser;
use Iberfacil\AeatVnif\Tests\TestCase;

final class SoapTest extends TestCase
{
    public function testBuildsEnvelopeWithNamespacesAndEscapes(): void
    {
        $xml = (new RequestBuilder())->build([
            ['nif' => '00000000T', 'name' => 'PERALVILLO LUMINA ZEFIRA'],
            ['nif' => 'B00000000', 'name' => 'BRUMALIA <FICTICIA> SL'],
        ]);

        self::assertStringContainsString('xmlns="' . RequestBuilder::NAMESPACE . '"', $xml);
        self::assertStringContainsString('<Contribuyente><Nif>00000000T</Nif><Nombre>PERALVILLO LUMINA ZEFIRA</Nombre></Contribuyente>', $xml);
        self::assertStringContainsString('BRUMALIA &lt;FICTICIA&gt; SL', $xml);
        self::assertStringNotContainsString('<FICTICIA>', $xml);
        self::assertStringContainsString('soapenv:Body', $xml);
    }

    public function testParsesEveryResult(): void
    {
        $rows = (new ResponseParser())->parse(self::fixture('mixed'));

        self::assertCount(4, $rows);
        self::assertSame('00000000T', $rows[0]['nif']);
        self::assertSame('LUMINA PERALVILLO ZEFIRA', $rows[0]['name']);
        self::assertSame(IdentificationResult::Identified, $rows[0]['result']);
        self::assertNull($rows[1]['name']);
        self::assertSame(IdentificationResult::NotIdentified, $rows[1]['result']);
        self::assertSame(IdentificationResult::IdentifiedDeregistered, $rows[2]['result']);
        self::assertSame(IdentificationResult::NotProcessed, $rows[3]['result']);
        self::assertSame('NO PROCESADO', $rows[3]['raw']['Resultado']);
    }

    public function testSoapFaultIsDefinitiveAndDoesNotLeakText(): void
    {
        try {
            (new ResponseParser())->parse(self::fixture('fault'));
            self::fail('Se esperaba DefinitiveFailure');
        } catch (DefinitiveFailure $e) {
            self::assertStringNotContainsString('ficticia', $e->getMessage());
        }
    }

    public function testRejectsGarbageAndDoctype(): void
    {
        $parser = new ResponseParser();

        foreach (['', 'no es xml', '<!DOCTYPE x [<!ENTITY e "x">]><x>&e;</x>', '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Body/></soap:Envelope>'] as $body) {
            try {
                $parser->parse($body);
                self::fail('Se esperaba DefinitiveFailure');
            } catch (DefinitiveFailure) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testRejectsUnknownResultAndMissingFields(): void
    {
        $parser = new ResponseParser();
        $unknown = str_replace('IDENTIFICADO', 'QUIZAS', self::fixture('identified'));
        $missing = str_replace('<Resultado>IDENTIFICADO</Resultado>', '', self::fixture('identified'));

        foreach ([$unknown, $missing] as $body) {
            try {
                $parser->parse($body);
                self::fail('Se esperaba DefinitiveFailure');
            } catch (DefinitiveFailure) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
