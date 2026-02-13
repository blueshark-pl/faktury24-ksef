<?php
declare(strict_types=1);

return [
    'Ksef' => [
        // gdzie jest master cert
        'masterCertDir' => ROOT . DS . 'resources' . DS . 'ksef_certs' . DS . 'master',

        // Wymuś używanie certyfikatu master (ignoruj certyfikaty firmowe)
        // Cel: status/opcje KSeF oparte o centralny cert master, bez uploadu certów przez userów.
        'forceMasterCert' => true,

        // Cache statusu InvoiceWrite (sekundy)
        // Server-side: statusAjax() reużywa wynik z sesji, jeśli jest świeży.
        // Client-side: layout reużywa wynik z localStorage, aby ograniczyć ruch.
        'statusCacheSeconds' => 180,
        'statusClientCacheSeconds' => 300,

        // gdzie zapisujemy meta per NIP (encryptionKey)
        'metaDir' => ROOT . DS . 'var' . DS . 'ksef' . DS . 'meta',

        // jeśli chcesz na Windows wymusić CA bundle (opcjonalnie)
        'caBundle' => ROOT . DS . 'resources' . DS . 'cacert.pem',

        // diagnostyka: nigdy na prod
        'skipTlsVerify' => false,

        // opcje buildera
        'validateXml' => true,
        'verifyCertificateChain' => true,
        'asyncMaxConcurrency' => 8,

        // opcjonalnie: nadpisanie API url
        // 'apiUrl' => 'https://ksef.mf.gov.pl/api/v2',
    ],
];
