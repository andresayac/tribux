<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Issuer configuration
    |--------------------------------------------------------------------------
    |
    | Path to a mounted JSON file holding the non-secret DIAN configuration of
    | every issuer, keyed by issuer id. It must never be committed and must
    | never contain the software PIN, the technical key or a certificate
    | password. See examples/issuer.habilitation.json for the shape.
    |
    */

    'issuers_file' => env('TRIBUX_ISSUERS_FILE'),

    /*
    |--------------------------------------------------------------------------
    | Issuer secrets
    |--------------------------------------------------------------------------
    |
    | Directory of mounted secret files, one per credential reference. Tribux
    | reads them at the moment they are needed and never stores them in a job
    | payload, a log line or an evidence row.
    |
    */

    'secrets_path' => env('TRIBUX_SECRETS_PATH'),

    /*
    |--------------------------------------------------------------------------
    | Evidence storage
    |--------------------------------------------------------------------------
    |
    | Disk that receives XML, ZIP and SOAP artefacts. The local driver is only
    | acceptable in development: legal documents must not live solely on a
    | container filesystem.
    |
    | store_soap_requests is opt-in because a request envelope carries the whole
    | signed document, and therefore taxpayer data.
    |
    */

    'evidence' => [
        'disk' => env('TRIBUX_EVIDENCE_DISK', 'evidence'),
        'store_soap_requests' => (bool) env('TRIBUX_EVIDENCE_STORE_SOAP_REQUESTS', false),
    ],

];
