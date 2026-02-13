<?php
declare(strict_types=1);

namespace App\View\Cell;

use App\Service\Ksef\CertificateStorage;
use App\Service\Ksef\DbKsefTokenStorage;
use App\Service\Ksef\N1KsefService;
use Cake\View\Cell;

final class KsefAuthContextCell extends Cell
{
    public function display(): void
    {
        $identity = $this->request->getAttribute('identity');
        $companyId = (string)($identity?->get('company_id') ?? '');

        if ($companyId === '') {
            $this->set(['enabled' => false]);
            return;
        }

        $envFromSess = (string)($this->request->getSession()->read('Ksef.status.env') ?? '');
        $environment = $envFromSess !== '' ? $envFromSess : 'test';
        $environment = $environment === 'prod' ? 'prod' : 'test';

        $diag = null;
        try {
            $ksef = new N1KsefService(new DbKsefTokenStorage(), new CertificateStorage());
            $diag = $ksef->diagnoseAuthContext($companyId, $environment);
        } catch (\Throwable) {
            $diag = null;
        }

        $certText = 'cert: brak';
        $certClass = 'text-danger';

        $tooltipParts = [];

        if (is_array($diag)) {
            $authMethod = (string)($diag['authMethod'] ?? '');
            $certUsed = (bool)($diag['certUsed'] ?? false);
            $certPresent = (bool)($diag['certPresent'] ?? false);
            $certReadable = $diag['certReadable'] ?? null;

            $usingMaster = false;
            $masterCertCompanyId = (string)($diag['masterCertCompanyId'] ?? '');
            $certCompanyId = (string)($diag['certCompanyId'] ?? '');
            $certSource = (string)($diag['certSource'] ?? '');

            if ($authMethod === 'certificate') {
                if ($masterCertCompanyId !== '' && $certCompanyId !== '' && $certCompanyId !== $companyId) {
                    $usingMaster = true;
                } elseif ($certSource === 'master') {
                    $usingMaster = true;
                }
            }

            if ($certUsed) {
                if ($usingMaster) {
                    $certText = 'cert: master';
                    $certClass = 'text-warning';
                } else {
                    $certText = 'cert: OK';
                    $certClass = 'text-success';
                }
            } elseif ($certPresent) {
                $certText = 'cert: błąd';
                $certClass = 'text-danger';
            } else {
                $certText = $authMethod === 'token' ? 'auth: token' : 'cert: brak';
                $certClass = $authMethod === 'token' ? 'text-muted' : 'text-danger';
            }

            $tooltipParts[] = 'Env: ' . strtoupper($environment);
            if ($authMethod !== '') {
                $tooltipParts[] = 'Auth: ' . $authMethod;
            }
            if ($certPresent) {
                $tooltipParts[] = 'Cert source: ' . ($certSource !== '' ? $certSource : '?');
                $tooltipParts[] = 'Cert readable: ' . (is_bool($certReadable) ? ($certReadable ? 'yes' : 'no') : '?');
            }
            $certFile = (string)($diag['certFile'] ?? '');
            if ($certFile !== '') {
                $tooltipParts[] = 'Cert file: ' . $certFile;
            }
            $identifierNip = (string)($diag['identifierNip'] ?? '');
            if ($identifierNip !== '') {
                $tooltipParts[] = 'Identifier NIP: ' . $identifierNip;
            }
            if ($masterCertCompanyId !== '') {
                $tooltipParts[] = 'Master cert companyId: ' . $masterCertCompanyId;
            }
        }

        // If user used "Status" action recently, show last known connectivity.
        $status = $this->request->getSession()->read('Ksef.status');
        $connText = null;
        $connClass = 'text-muted';
        $connTooltip = null;

        if (is_array($status) && (($status['env'] ?? null) === $environment) && array_key_exists('active', $status)) {
            $active = (bool)$status['active'];
            $connText = $active ? 'połączenie: OK' : 'Brak połączenia z KSeF';
            $connClass = $active ? 'text-success' : 'text-danger';

            $ts = isset($status['ts']) ? (int)$status['ts'] : 0;
            if ($ts > 0) {
                $connTooltip = 'Ostatnia diagnoza: ' . date('Y-m-d H:i:s', $ts);
            }
            $lastError = trim((string)($status['lastError'] ?? ''));
            if (!$active && $lastError !== '') {
                $connTooltip = trim(($connTooltip ? ($connTooltip . "\n") : '') . 'Błąd: ' . $lastError);
            }
        }

        $tooltip = $tooltipParts ? implode("\n", $tooltipParts) : null;

        $this->set([
            'enabled' => true,
            'environment' => $environment,
            'certText' => $certText,
            'certClass' => $certClass,
            'tooltip' => $tooltip,
            'connText' => $connText,
            'connClass' => $connClass,
            'connTooltip' => $connTooltip,
        ]);
    }
}
