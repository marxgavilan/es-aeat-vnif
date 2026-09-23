<?php

declare(strict_types=1);

namespace Iberfacil\AeatVnif\Tests\Unit;

use Iberfacil\AeatVnif\Cache\InMemoryResultCache;
use Iberfacil\AeatVnif\Contracts\NameNormalizer;
use Iberfacil\AeatVnif\Data\IdentificationResult;
use Iberfacil\AeatVnif\Data\Taxpayer;
use Iberfacil\AeatVnif\Data\TaxpayerName;
use Iberfacil\AeatVnif\Data\VnifRequest;
use Iberfacil\AeatVnif\Data\VnifResponse;
use Iberfacil\AeatVnif\Exceptions\ConfigurationException;
use Iberfacil\AeatVnif\Exceptions\DefinitiveFailure;
use Iberfacil\AeatVnif\Exceptions\InvalidTaxpayer;
use Iberfacil\AeatVnif\Exceptions\TransientFailure;
use Iberfacil\AeatVnif\Observers\CallbackObserver;
use Iberfacil\AeatVnif\Tests\TestCase;
use Iberfacil\AeatVnif\Transport\FakeTransport;
use Iberfacil\AeatVnif\VnifClient;
use Iberfacil\AeatVnif\VnifOptions;
use Throwable;

final class VnifClientTest extends TestCase
{
    /** @var list<int> */
    private array $sleeps = [];

    private function client(FakeTransport $transport, ?VnifOptions $options = null, ?InMemoryResultCache $cache = null, ?NameNormalizer $normalizer = null): VnifClient
    {
        return new VnifClient(
            certificate: self::certificate(),
            options: $options ?? new VnifOptions(retries: 2, retryDelayMs: 10),
            transport: $transport,
            normalizer: $normalizer,
            cache: $cache,
            sleep: function (int $ms): void {
                $this->sleeps[] = $ms;
            },
        );
    }

    public function testIdentifiesAndSendsNormalizedCensusName(): void
    {
        $transport = (new FakeTransport())->queue(self::fixture('identified'));
        $result = $this->client($transport)->check('00000000t', TaxpayerName::naturalPerson('Zéfira', 'Lumina', 'Peralvillo'));

        self::assertSame(IdentificationResult::Identified, $result->result);
        self::assertSame('IDENTIFICADO', $result->aeatResult);
        self::assertTrue($result->isIdentified());
        self::assertSame('LUMINA PERALVILLO ZEFIRA', $result->sentName);
        self::assertSame('LUMINA PERALVILLO ZEFIRA', $result->censusName);
        self::assertFalse($result->nameWasCorrected());

        self::assertCount(1, $transport->sent);
        self::assertStringContainsString('<Nif>00000000T</Nif><Nombre>LUMINA PERALVILLO ZEFIRA</Nombre>', $transport->sent[0]['xml']);
        self::assertSame(VnifOptions::DEFAULT_ENDPOINT, $transport->sent[0]['endpoint']);
        self::assertTrue($transport->sent[0]['keyExisted']);
    }

    public function testApproximateNameIsIdentifiedAndCensusNameExposed(): void
    {
        // Mandamos un nombre incompleto y sin tildes; la AEAT identifica y devuelve el
        // censal con tildes y relleno. Se casa por NIF, no por nombre.
        $transport = (new FakeTransport())->queue(self::fixture('identified-corrected-name'));
        $result = $this->client($transport)->check('00000000T', TaxpayerName::naturalPerson('Zefira', 'Lumina'));

        self::assertSame(IdentificationResult::Identified, $result->result);
        self::assertSame('LUMINA ZEFIRA', $result->sentName);
        self::assertSame('LUMINÁ PERALVILLO ZÉFIRA', $result->censusName);
        self::assertSame('LUMINA PERALVILLO ZEFIRA', $result->normalizedCensusName);
        self::assertTrue($result->nameWasCorrected());
        self::assertTrue($result->censusNameHasAccents());
        self::assertSame('LUMINÁ PERALVILLO ZÉFIRA', $result->toArray()['census_name']);
        self::assertTrue($result->toArray()['name_was_corrected']);
    }

    public function testOnlyAccentsDifferIsNotACorrection(): void
    {
        $transport = (new FakeTransport())->queue(self::fixture('identified-corrected-name'));
        $result = $this->client($transport)->check('00000000T', TaxpayerName::naturalPerson('Zéfira', 'Luminá', 'Peralvillo'));

        self::assertFalse($result->nameWasCorrected());
        self::assertTrue($result->censusNameHasAccents());
    }

    public function testSimilarExposesCensusNameForReview(): void
    {
        $transport = (new FakeTransport())->queue(self::fixture('similar'));
        $result = $this->client($transport)->check('00000000T', TaxpayerName::naturalPerson('Zefira', 'Cristalina', 'Brumosa'));

        self::assertSame(IdentificationResult::NotIdentifiedSimilar, $result->result);
        self::assertFalse($result->isIdentified());
        self::assertSame('CRISTALINA BRUMOSO ZEFIRA', $result->censusName);
        self::assertTrue($result->nameWasCorrected());
    }

    public function testBatchKeepsInputOrderAndPerTaxpayerNotProcessed(): void
    {
        $transport = (new FakeTransport())->queue(self::fixture('mixed'));
        $results = $this->client($transport)->checkBatch([
            Taxpayer::naturalPerson('00000003A', 'Otra', 'Persona'),
            Taxpayer::entity('B00000000', 'Brumalia Ficticia, S.L.'),
            Taxpayer::naturalPerson('00000000T', 'Zefira', 'Lumina', 'Peralvillo'),
            Taxpayer::composed('00000001R', 'NADIE CONOCIDO'),
        ]);

        self::assertSame(['00000003A', 'B00000000', '00000000T', '00000001R'], array_map(static fn($r) => $r->nif, $results));
        self::assertSame(IdentificationResult::NotProcessed, $results[0]->result);
        self::assertInstanceOf(TransientFailure::class, $results[0]->failure);
        self::assertSame(IdentificationResult::IdentifiedDeregistered, $results[1]->result);
        self::assertTrue($results[1]->isIdentified());
        self::assertSame(IdentificationResult::Identified, $results[2]->result);
        self::assertSame(IdentificationResult::NotIdentified, $results[3]->result);
        self::assertNull($results[3]->censusName);
        self::assertFalse($results[3]->nameWasCorrected());
        self::assertCount(1, $transport->sent);
    }

    public function testSingleCheckThrowsOnNotProcessed(): void
    {
        $transport = (new FakeTransport())->queue(self::fixture('not-processed'));

        $this->expectException(TransientFailure::class);
        $this->client($transport)->check('00000000T', 'PERALVILLO LUMINA ZEFIRA');
    }

    public function testSplitsIntoBatchesAndSeparatesDuplicateNifs(): void
    {
        $transport = (new FakeTransport())
            ->queueRows([['nif' => '00000000T', 'name' => 'A', 'result' => 'IDENTIFICADO'], ['nif' => '00000001R', 'result' => 'NO IDENTIFICADO']])
            ->queueRows([['nif' => 'B00000000', 'name' => 'B', 'result' => 'IDENTIFICADO'], ['nif' => '00000000T', 'result' => 'NO IDENTIFICADO']]);

        $results = $this->client($transport, new VnifOptions(batchSize: 2, retries: 0))->checkBatch([
            Taxpayer::composed('00000000T', 'A'),
            Taxpayer::composed('00000001R', 'X'),
            Taxpayer::composed('B00000000', 'B'),
            Taxpayer::composed('00000000T', 'A'),
            Taxpayer::composed('00000000T', 'OTRO NOMBRE'),
        ]);

        // El NIF repetido con el mismo nombre comparte respuesta; con otro nombre va a la
        // siguiente peticion con hueco, nunca a la misma.
        self::assertCount(2, $transport->sent);
        self::assertStringNotContainsString('OTRO NOMBRE', $transport->sent[0]['xml']);
        self::assertStringContainsString('OTRO NOMBRE', $transport->sent[1]['xml']);
        self::assertCount(5, $results);
        self::assertSame(IdentificationResult::Identified, $results[0]->result);
        self::assertSame(IdentificationResult::Identified, $results[3]->result);
        self::assertSame(IdentificationResult::NotIdentified, $results[4]->result);
        self::assertSame('OTRO NOMBRE', $results[4]->sentName);
    }

    public function testRetriesTransientFailuresWithBackoff(): void
    {
        $transport = (new FakeTransport())
            ->queue(TransientFailure::timeout(30), TransientFailure::serverUnavailable(503), self::fixture('identified'));

        $result = $this->client($transport)->check('00000000T', 'PERALVILLO LUMINA ZEFIRA');

        self::assertTrue($result->isIdentified());
        self::assertCount(3, $transport->sent);
        self::assertSame([10, 20], $this->sleeps);
    }

    public function testGivesUpAfterConfiguredRetries(): void
    {
        $transport = (new FakeTransport())->queue(TransientFailure::timeout(30), TransientFailure::timeout(30), TransientFailure::timeout(30));

        try {
            $this->client($transport)->check('00000000T', 'PERALVILLO LUMINA ZEFIRA');
            self::fail('Se esperaba TransientFailure');
        } catch (TransientFailure) {
            self::assertCount(3, $transport->sent);
        }
    }

    public function testDefinitiveFailuresAreNotRetried(): void
    {
        $transport = (new FakeTransport())->queue(self::fixture('fault'), self::fixture('identified'));

        try {
            $this->client($transport)->check('00000000T', 'PERALVILLO LUMINA ZEFIRA');
            self::fail('Se esperaba DefinitiveFailure');
        } catch (DefinitiveFailure) {
            self::assertCount(1, $transport->sent);
        }
    }

    public function testResponseWithForeignNifIsRejected(): void
    {
        $transport = (new FakeTransport())->queueRows([['nif' => '99999999R', 'name' => 'X', 'result' => 'IDENTIFICADO']]);

        $this->expectException(DefinitiveFailure::class);
        $this->expectExceptionMessage('no coincide');
        $this->client($transport, new VnifOptions(retries: 0))->check('00000000T', 'PERALVILLO LUMINA ZEFIRA');
    }

    public function testResponseWithDifferentNameIsAcceptedByNif(): void
    {
        $transport = (new FakeTransport())->queueRows([['nif' => '00000000T', 'name' => 'NOMBRE CENSAL DISTINTO', 'result' => 'IDENTIFICADO']]);
        $result = $this->client($transport)->check('00000000T', 'LO QUE SEA');

        self::assertTrue($result->isIdentified());
        self::assertTrue($result->nameWasCorrected());
        self::assertSame('NOMBRE CENSAL DISTINTO', $result->censusName);
    }

    public function testObserversSeeRequestsResponsesAndFailures(): void
    {
        $seen = [];
        $observer = CallbackObserver::make()
            ->before(function (VnifRequest $r) use (&$seen): void {
                $seen[] = 'before:' . $r->attempt . ':' . $r->size();
            })
            ->after(function (VnifRequest $r, VnifResponse $s) use (&$seen): void {
                $seen[] = 'after:' . json_encode($s->countsByResult());
            })
            ->failure(function (VnifRequest $r, Throwable $e) use (&$seen): void {
                $seen[] = 'failure:' . $e::class;
            });

        $transport = (new FakeTransport())->queue(TransientFailure::timeout(1), self::fixture('mixed'));
        $client = $this->client($transport)->withObserver($observer);
        $client->checkBatch([
            Taxpayer::composed('00000000T', 'A'), Taxpayer::composed('00000001R', 'B'),
            Taxpayer::composed('B00000000', 'C'), Taxpayer::composed('00000003A', 'D'),
        ]);

        self::assertSame([
            'before:1:4',
            'failure:' . TransientFailure::class,
            'before:2:4',
            'after:{"IDENTIFICADO":1,"IDENTIFICADO-BAJA":1,"NO IDENTIFICADO":1,"NO PROCESADO":1}',
        ], $seen);
    }

    public function testRequestExposesHashButNotXml(): void
    {
        $captured = null;
        $observer = CallbackObserver::make()->before(function (VnifRequest $r) use (&$captured): void {
            $captured = $r;
        });
        $transport = (new FakeTransport())->queue(self::fixture('identified'));
        $this->client($transport)->withObserver($observer)->check('00000000T', 'PERALVILLO LUMINA ZEFIRA');

        self::assertInstanceOf(VnifRequest::class, $captured);
        self::assertSame(hash('sha256', $transport->sent[0]['xml']), $captured->xmlSha256);
        self::assertSame([['nif' => '00000000T', 'name' => 'PERALVILLO LUMINA ZEFIRA']], $captured->rows);
    }

    public function testCacheSkipsRepeatedQueriesButNeverStoresNotProcessed(): void
    {
        $cache = new InMemoryResultCache();
        $transport = (new FakeTransport())->queue(self::fixture('identified'), self::fixture('not-processed'), self::fixture('not-processed'));
        $client = $this->client($transport, null, $cache);

        $first = $client->check('00000000T', 'PERALVILLO LUMINA ZEFIRA');
        $second = $client->check('00000000T', 'PERALVILLO LUMINA ZEFIRA');
        self::assertFalse($first->fromCache);
        self::assertTrue($second->fromCache);
        self::assertCount(1, $transport->sent);

        $batch = $client->checkBatch([Taxpayer::composed('00000000T', 'OTRO')]);
        self::assertSame(IdentificationResult::NotProcessed, $batch[0]->result);
        $batch = $client->checkBatch([Taxpayer::composed('00000000T', 'OTRO')]);
        self::assertFalse($batch[0]->fromCache);
        self::assertCount(3, $transport->sent);
    }

    public function testCustomNormalizerIsUsed(): void
    {
        $normalizer = new class implements NameNormalizer {
            public function normalize(string $name): string
            {
                return strtoupper(str_replace(' ', '_', trim($name)));
            }
        };
        $transport = (new FakeTransport())->queueRows([['nif' => '00000000T', 'name' => 'A_B', 'result' => 'IDENTIFICADO']]);
        $result = $this->client($transport, null, null, $normalizer)->check('00000000T', 'a b');

        self::assertSame('A_B', $result->sentName);
        self::assertFalse($result->nameWasCorrected());
        self::assertStringContainsString('<Nombre>A_B</Nombre>', $transport->sent[0]['xml']);
    }

    public function testEmptyBatchMakesNoRequest(): void
    {
        $transport = new FakeTransport();
        self::assertSame([], $this->client($transport)->checkBatch([]));
        self::assertSame([], $transport->sent);
    }

    public function testEmptyNameIsRejectedBeforeSending(): void
    {
        $transport = new FakeTransport();
        $this->expectException(InvalidTaxpayer::class);
        $this->client($transport)->check('00000000T', '...');
    }

    public function testTemporaryPemFilesAreRemovedEvenOnFailure(): void
    {
        $transport = (new FakeTransport())->queue(self::fixture('fault'));
        try {
            $this->client($transport)->check('00000000T', 'PERALVILLO LUMINA ZEFIRA');
        } catch (DefinitiveFailure) {
        }

        self::assertFileDoesNotExist($transport->sent[0]['certificatePath']);
    }

    public function testOptionsAreValidated(): void
    {
        foreach ([
            static fn() => new VnifOptions(endpoint: 'http://insegura.example'),
            static fn() => new VnifOptions(endpoint: 'https://user:pass@host.example/x'),
            static fn() => new VnifOptions(batchSize: 0),
            static fn() => new VnifOptions(batchSize: 10001),
            static fn() => new VnifOptions(timeoutSeconds: 0),
            static fn() => new VnifOptions(retries: -1),
        ] as $factory) {
            try {
                $factory();
                self::fail('Se esperaba ConfigurationException');
            } catch (ConfigurationException) {
                $this->addToAssertionCount(1);
            }
        }

        $options = VnifOptions::fromArray(['timeout' => '5', 'batch_size' => 50, 'endpoint' => '']);
        self::assertSame(5, $options->timeoutSeconds);
        self::assertSame(50, $options->batchSize);
        self::assertSame(VnifOptions::DEFAULT_ENDPOINT, $options->endpoint);
    }
}
