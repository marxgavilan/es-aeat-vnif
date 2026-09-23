# es-aeat-vnif

Check from PHP that a Spanish tax ID (NIF) exists in the Tax Agency (AEAT) census and matches the name you were given, using your electronic certificate. Framework-free, with a command-line tool and an optional Laravel adapter.

[Versión en español](README.md)

Created by Marco Gavilán, of IBERFÁCIL.

---

## What it is for (non-technical)

When an accounting firm, a law office, a company or a compliance department receives the details of a client, a supplier or a third party, what arrives is a NIF and a name typed by hand, copied from an email or dictated over the phone. Before invoicing, hiring, onboarding that client, filing a document or including them in an informative return, you want to know two things:

- that the NIF **exists** in the Tax Agency census, and
- that it **matches the name** you were given (or which name it really matches).

The AEAT provides a service for exactly that: the "third-party NIF verification service" (VNifV2). This package talks to it for you. You give it a NIF and a name and get one of these answers:

| AEAT answers | What it means in practice |
| --- | --- |
| **IDENTIFICADO** | The NIF exists and the name matches. If the name you sent was approximate (no accents, a letter short, a missing surname) the AEAT still identifies it and returns the name **as it stands in the census**. |
| **NO IDENTIFICADO-SIMILAR** | The NIF exists but the name is only similar. The census name is returned so you can review it. |
| **NO IDENTIFICADO** | The NIF is not on file or the name does not match. |
| **IDENTIFICADO-BAJA** / **IDENTIFICADO-REVOCADO** | The NIF exists and matches, but is deregistered or revoked. |
| **NO PROCESADO** | The AEAT could not process that query right now. Retry later. |

Real-world examples:

- An accounting firm receives 300 new clients in a file and wants to know which ones have a wrong NIF or an incomplete name before onboarding them.
- A law office is about to file a document and needs the exact name of the interested party as held by the AEAT.
- A billing department wants to avoid issuing invoices to a non-existent NIF that will later cause trouble in tax returns.
- An online shop onboards suppliers and wants to confirm the legal name matches the company tax ID.

**What you need:** an electronic certificate (entity seal, legal representative or personal) in `.p12` or `.pfx` format, plus its password. It is the same certificate used for the AEAT online office. The package never stores the password or the certificate anywhere.

### Which certificate works?

Any of the three issued by the FNMT or another recognised authority: a **personal** certificate (self-employed people and individuals), a **legal-entity representative** certificate, or an **entity seal**. Self-employed users just export their personal certificate from the browser to a password-protected `.p12`/`.pfx` file. The `doctor` command tells you which kind it detected. Queries are made under your identity: use it only for your own clients, suppliers and procedures.

**What it does NOT do:** it does not prove that the person handing you the details is who they say they are. It only confirms that this NIF and this name exist together in the census. Identifying the person is a different matter (ID document, video identification, electronic signature...).

---

## For developers

### Requirements

- PHP 8.2+ with the `openssl`, `curl`, `dom`, `libxml` and `mbstring` extensions (all standard). `intl` is optional but recommended.
- An electronic certificate accepted by the AEAT, as `.p12`/`.pfx` (or PEM).
- HTTPS access to `https://www1.agenciatributaria.gob.es/wlpl/BURT-JDIT/ws/VNifV2SOAP`.

### Installation

```bash
composer require iberfacil/es-aeat-vnif
```

### Up and running in five minutes

1. **Upload your certificate** to the server, outside the repository and outside any public folder. For example `/etc/aeat/certificate.p12` with `0600` permissions.

2. **Put the password in the environment**, never in code or in the repository:

   ```bash
   export AEAT_VNIF_CERT=/etc/aeat/certificate.p12
   export AEAT_VNIF_CERT_PASSWORD='the-password'
   ```

   If `AEAT_VNIF_CERT_PASSWORD` is not set, the executable prompts for it with echo disabled.

3. **Run the doctor.** It checks extensions, reads the certificate, checks expiry and opens a TLS connection to the endpoint. No NIF is queried.

   ```bash
   vendor/bin/aeat-vnif doctor
   ```

4. **Make your first query.**

   ```bash
   vendor/bin/aeat-vnif check 00000000T --nombre=Zefira --apellido1=Lumina --apellido2=Peralvillo
   ```

   ```
   00000000T  IDENTIFICADO
       El NIF y el nombre coinciden con el censo de la AEAT.
       Enviado:  LUMINA PERALVILLO ZEFIRA
       Censo:    LUMINÁ PERALVILLO ZÉFIRA
   ```

(All NIFs and names in this documentation are made up.)

### Command line

```bash
# Natural person (given name, first surname, second surname)
aeat-vnif check 00000000T --nombre=Zefira --apellido1=Lumina --apellido2=Peralvillo

# Entity
aeat-vnif check B00000000 --razon-social="Brumalia Ficticia SL"

# Batch from CSV (";" separator)
aeat-vnif batch clients.csv

# JSON output
aeat-vnif check 00000000T --razon-social="Brumalia Ficticia SL" --json

# Explicit certificate (password always via AEAT_VNIF_CERT_PASSWORD or prompt)
aeat-vnif doctor --cert=/path/certificate.pfx
```

CSV format, one taxpayer per line:

```
NIF;SURNAME1;SURNAME2;GIVEN_NAME    natural person
NIF;SURNAME1;GIVEN_NAME             natural person with a single surname
NIF;LEGAL_NAME                      entity
```

A header starting with `NIF`, blank lines and `#` comments are allowed.

Exit codes: `0` query performed (whatever the AEAT answered), `1` usage or configuration error, `2` transient failure (network, timeout, NO PROCESADO), `3` definitive failure.

Options: `--endpoint=`, `--timeout=`, `--retries=`, `--batch-size=`, `--json`, `--no-probe` (doctor). Also read from `AEAT_VNIF_ENDPOINT`, `AEAT_VNIF_TIMEOUT`, `AEAT_VNIF_RETRIES` and `AEAT_VNIF_BATCH_SIZE`.

### In code

```php
use Iberfacil\AeatVnif\VnifClient;
use Iberfacil\AeatVnif\Data\Taxpayer;
use Iberfacil\AeatVnif\Data\TaxpayerName;

$client = VnifClient::create('/etc/aeat/certificate.p12', getenv('AEAT_VNIF_CERT_PASSWORD'));

// Natural person: given name and surnames kept apart
$result = $client->check('00000000T', TaxpayerName::naturalPerson('Zefira', 'Lumina', 'Peralvillo'));

$result->result;              // IdentificationResult::Identified
$result->aeatResult;          // 'IDENTIFICADO' (AEAT literal)
$result->isIdentified();      // true
$result->sentName;            // 'LUMINA PERALVILLO ZEFIRA'
$result->censusName;          // 'LUMINÁ PERALVILLO ZÉFIRA' (as held by the census)
$result->nameWasCorrected();  // false: same letters, only accents differ

// Entity
$result = $client->check('B00000000', TaxpayerName::entity('Brumalia Ficticia, S.L.'));

// Batch: results come back in input order
$results = $client->checkBatch([
    Taxpayer::naturalPerson('00000000T', 'Zefira', 'Lumina', 'Peralvillo'),
    Taxpayer::entity('B00000000', 'Brumalia Ficticia SL'),
]);

foreach ($results as $r) {
    if ($r->failure !== null) {
        // NO PROCESADO: retry this taxpayer later
    }
}
```

Options go through `VnifOptions`:

```php
use Iberfacil\AeatVnif\VnifOptions;

$options = new VnifOptions(
    endpoint: VnifOptions::DEFAULT_ENDPOINT,
    batchSize: 500,       // taxpayers per request (1..10000)
    timeoutSeconds: 30,
    retries: 2,           // retries on transient network failure
    retryDelayMs: 500,    // initial wait, doubled on each attempt
);

$client = VnifClient::create($path, $password, $options);
```

Exceptions, all under `Iberfacil\AeatVnif\Exceptions\AeatVnifException`:

| Exception | When | What to do |
| --- | --- | --- |
| `TransientFailure` | Network, timeout, HTTP 408/5xx, or `NO PROCESADO` in `check()` | Retry later |
| `DefinitiveFailure` | HTTP 4xx, SOAP Fault, invalid XML, response that does not match what was sent | Investigate; retrying will not help |
| `ConfigurationException` | Endpoint, batch size, timeout or retries out of range | Fix the configuration |
| `CertificateException` (subclass) | File not found, wrong password, expired, key does not match | Fix the certificate |
| `InvalidTaxpayer` | Empty or malformed NIF, empty name after normalisation | Fix the input |

No exception ever includes the password, certificate contents, the XML or any text returned by the AEAT.

### Reading each result

`CheckResult` exposes `result` (enum `IdentificationResult`), `aeatResult` (the literal), `sentName`, `censusName`, `nameWasCorrected()`, `censusNameHasAccents()`, `raw` and `failure`.

- **IDENTIFICADO with a corrected name.** Verified against the live service: send "LUMINA ZEFIRA" while the census holds "LUMINÁ PERALVILLO ZÉFIRA" and the AEAT answers `IDENTIFICADO` returning the full census name, with accents and sometimes padded with spaces. That is why the package **matches responses by NIF, never by name**, and `nameWasCorrected()` returns `true` when the letters differ (accents and padding alone do not count; see `censusNameHasAccents()`). Keep `censusName` when you need the exact spelling.
- **NO IDENTIFICADO-SIMILAR.** The NIF exists; the name is close but not enough. `censusName` holds the census name and `nameWasCorrected()` is `true`. Usually a misspelt surname or half a compound name.
- **NO IDENTIFICADO.** `censusName` is `null`. Either the NIF does not exist or the name is nothing alike.
- **IDENTIFICADO-BAJA / IDENTIFICADO-REVOCADO.** `isIdentified()` is `true`, but look at `result` before treating the NIF as operational.
- **NO PROCESADO.** In `checkBatch()` it arrives as a result with `failure` (the batch survives); in `check()` a `TransientFailure` is thrown.

### Laravel

The provider is auto-discovered. Publish the config if you need to change it:

```bash
php artisan vendor:publish --tag=aeat-vnif-config
```

`.env`:

```dotenv
AEAT_VNIF_CERT=/etc/aeat/certificate.p12
AEAT_VNIF_CERT_PASSWORD=the-password
# Optional
AEAT_VNIF_ENDPOINT=https://www1.agenciatributaria.gob.es/wlpl/BURT-JDIT/ws/VNifV2SOAP
AEAT_VNIF_TIMEOUT=30
AEAT_VNIF_BATCH_SIZE=10000
AEAT_VNIF_RETRIES=2
AEAT_VNIF_CACHE_STORE=redis      # empty = no cache
AEAT_VNIF_CACHE_TTL=86400
```

```php
use Iberfacil\AeatVnif\Laravel\Facades\AeatVnif;
use Iberfacil\AeatVnif\Data\TaxpayerName;

$result = AeatVnif::check('00000000T', TaxpayerName::naturalPerson('Zefira', 'Lumina', 'Peralvillo'));
```

Or inject `Iberfacil\AeatVnif\VnifClient`. Commands:

```bash
php artisan aeat-vnif:doctor
php artisan aeat-vnif:check 00000000T --nombre=Zefira --apellido1=Lumina --apellido2=Peralvillo
php artisan aeat-vnif:check B00000000 --razon-social="Brumalia Ficticia SL" --json
```

Events (`Iberfacil\AeatVnif\Laravel\Events\*`): `VnifRequestStarting`, `VnifRequestCompleted` and `VnifRequestFailed`, one per HTTP request, carrying the XML hash, the NIFs sent and the result counts. Disable with `AEAT_VNIF_EVENTS=false`.

To replace the transport, the normaliser or the cache, bind `Contracts\Transport`, `Contracts\NameNormalizer` or `Contracts\ResultCache` in your own provider before the client is resolved.

### Customisation

**Before and after every request** (audit it your way):

```php
use Iberfacil\AeatVnif\Observers\CallbackObserver;
use Iberfacil\AeatVnif\Data\VnifRequest;
use Iberfacil\AeatVnif\Data\VnifResponse;

$client = $client->withObserver(
    CallbackObserver::make()
        ->before(fn (VnifRequest $r) => $log->info('AEAT VNIF', ['hash' => $r->xmlSha256, 'n' => $r->size(), 'attempt' => $r->attempt]))
        ->after(fn (VnifRequest $r, VnifResponse $s) => $log->info('AEAT VNIF ok', $s->countsByResult()))
        ->failure(fn (VnifRequest $r, \Throwable $e) => $log->warning('AEAT VNIF failed', ['type' => $e::class]))
);
```

Or implement `Contracts\CheckObserver` in a class. `VnifRequest` exposes the NIFs and names sent in case your audit needs them; what you store is up to you.

**Cache** (`Contracts\ResultCache`): `Cache\InMemoryResultCache` for one run, `Cache\Psr16ResultCache` over any PSR-16 store, or your own. `NO PROCESADO` is never cached.

**Name normaliser** (`Contracts\NameNormalizer`): the default upper-cases, folds accents and diaereses (keeps Ñ), turns hyphens into spaces, strips punctuation and collapses whitespace. Inject your own for different rules.

**Transport** (`Contracts\Transport`): the default is `Transport\CurlTransport` (mTLS, HTTPS only, no redirects, connect and total timeout, optional proxy and CA bundle). Implement the interface to use your PSR-18 or Guzzle client. For tests, `Transport\FakeTransport` queues responses and records what was sent.

**Certificate** (`Contracts\Certificate`): `Certificate\Pkcs12Certificate` and `Certificate\PemCertificate`, or `CertificateLoader::fromFile()` which picks for you. The certificate is converted in memory to temporary PEM files in a `0700` directory with `0600` files, the key is re-exported encrypted with a random passphrase, and everything is deleted in `finally` (and in the destructor, as a safety net).

### Limits and good practice

- The service accepts up to 10,000 taxpayers per request; the package splits automatically. Within a batch, a repeated NIF with a different name goes into a separate request (responses are matched by NIF).
- Do not query from a synchronous web request: use a queue or a background process. The endpoint can be slow.
- **Legal basis.** Querying the census processes the third party's personal data. You need a lawful basis (usually compliance with tax or contractual obligations) and a concrete purpose. Do not use it to browse.
- **Data minimisation.** Query only the NIFs you need and keep only what you need from the result.
- **Evidence.** Keep the date, the NIF, the result, the census name if you use it and the SHA-256 of the XML sent (available in `VnifRequest`). It justifies why you accepted a NIF.
- **Never** store the certificate password in code, in the repository or in logs; never pass it as a command-line argument.
- The package logs nothing by itself. What gets audited is your call, through observers.

### Upgrading

```bash
composer update iberfacil/es-aeat-vnif
```

Semantic versioning: `1.x` releases do not break the public API; changes are listed in [CHANGELOG.md](CHANGELOG.md). Run `aeat-vnif doctor` again after upgrading.

### Development

```bash
composer install
composer check        # formatter + PHPStan + tests
```

Tests never touch the network: they use `FakeTransport`, sample responses with made-up NIFs and names, and a self-signed certificate generated in memory during the run. See [CONTRIBUTING.md](CONTRIBUTING.md), [SECURITY.md](SECURITY.md) and, if you integrate the package with AI agents, [AGENTS.md](AGENTS.md).

## License

MIT. Copyright (c) 2026 Marco Gavilán — IBERFÁCIL. See [LICENSE](LICENSE).
