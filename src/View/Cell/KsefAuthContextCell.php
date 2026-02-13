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

        $grantsHintText = null;
        $grantsHintClass = 'text-warning';

        $companyNip = '';
        try {
            /** @var \App\Model\Table\CompaniesTable $Companies */
            $Companies = $this->fetchTable('Companies');
            $company = $Companies->find()
                ->select(['nip'])
                ->where(['Companies.id' => $companyId])
                ->first();
            $companyNip = preg_replace('/\D/', '', (string)($company?->nip ?? ''));
        } catch (\Throwable) {
            $companyNip = '';
        }

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

            // Heuristic: master cert used for a different NIP than the company.
            // This typically means the company must grant permissions (pełnomocnictwo/uprawnienia) in KSeF.
            $idNipNorm = preg_replace('/\D/', '', $identifierNip);
            if ($usingMaster && $idNipNorm !== '' && $companyNip !== '' && $idNipNorm !== $companyNip) {
                $grantsHintText = 'uprawnienia: wymagane';
                $grantsHintClass = 'text-warning';
                $tooltipParts[] = 'Company NIP: ' . $companyNip;
                $tooltipParts[] = 'Hint: wymagane uprawnienia firmy dla NIP identyfikatora.';
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

            $reason = (string)($status['reason'] ?? '');

            $lastError = trim((string)($status['lastError'] ?? ''));
            $looksLikePermissionError = false;
            if (!$active && $lastError !== '') {
                $le = mb_strtolower($lastError);
                $looksLikePermissionError = str_contains($le, '401')
                    || str_contains($le, '403')
                    || str_contains($le, 'unauthorized')
                    || str_contains($le, 'forbidden')
                    || str_contains($le, 'uprawn')
                    || str_contains($le, 'permission')
                    || str_contains($le, 'brak dost');
            }

            $isNoPermissions = (!$active) && ($reason === 'no_permissions' || $looksLikePermissionError);

            $connText = $active
                ? 'połączenie: OK'
                : ($isNoPermissions ? 'Brak uprawnień w KSeF' : 'Brak połączenia z KSeF');
            $connClass = $active ? 'text-success' : 'text-danger';

            $ts = isset($status['ts']) ? (int)$status['ts'] : 0;
            if ($ts > 0) {
                $connTooltip = 'Ostatnia diagnoza: ' . date('Y-m-d H:i:s', $ts);
            }
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
            'grantsHintText' => $grantsHintText,
            'grantsHintClass' => $grantsHintClass,
            'tooltip' => $tooltip,
            'connText' => $connText,
            'connClass' => $connClass,
            'connTooltip' => $connTooltip,
        ]);
    }
}
