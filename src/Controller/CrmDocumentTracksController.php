<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Http\Exception\GoneException;
use Cake\Http\Exception\NotFoundException;
use Cake\I18n\DateTime;
use Cake\Utility\Text;

/**
 * Document tracking - publiczny access + admin management.
 *
 * Publiczne (bez auth):
 *   GET  /doc/{hash}          - HTML page ze embed PDF + heartbeat JS
 *   GET  /doc/{hash}.pdf      - Serve PDF file + log open
 *   GET  /doc/{hash}/pixel.png - 1x1 pixel dla email tracking
 *   POST /doc/{hash}/heartbeat - AJAX ping co 10s podczas przegladania
 *
 * Admin:
 *   POST /crm/doc/create       - stworz tracking link dla dokumentu
 *   GET  /crm/doc/{id}/stats   - zobacz statystyki (opens history)
 *   POST /crm/doc/{id}/deactivate - deactivate link
 */
class CrmDocumentTracksController extends AppController
{
    public function beforeFilter(\Cake\Event\EventInterface $event)
    {
        parent::beforeFilter($event);
        if ($this->components()->has('Authentication')) {
            $this->Authentication->allowUnauthenticated(['view', 'download', 'pixel', 'heartbeat']);
        }
    }

    /**
     * PUBLICZNE: GET /doc/{hash} - HTML wrapper z embed PDF + heartbeat.
     */
    public function view(string $hash): void
    {
        $track = $this->loadActiveTrack($hash);
        $this->recordOpen($track, 'view');

        $pdfUrl = $this->request->getAttribute('webroot') . 'doc/' . $hash . '.pdf';
        $this->set(compact('track', 'pdfUrl'));
        $this->viewBuilder()->setLayout('ajax');
    }

    /**
     * PUBLICZNE: GET /doc/{hash}.pdf - serve PDF binary.
     */
    public function download(string $hash): \Cake\Http\Response
    {
        $this->autoRender = false;
        $track = $this->loadActiveTrack($hash);
        $this->recordOpen($track, 'download');

        $path = (string)$track->document_url;
        // Jesli sciezka wzgledna - dopelnij do webroot
        if (!empty($path) && $path[0] !== '/' && !preg_match('#^[a-z]+://#i', $path)) {
            $path = WWW_ROOT . ltrim($path, '/\\');
        }
        if (!file_exists($path)) {
            throw new NotFoundException(__('Plik PDF nie istnieje na serwerze.'));
        }
        return $this->response
            ->withType('application/pdf')
            ->withHeader('Content-Disposition', 'inline; filename="' . ($track->document_name ?: 'document.pdf') . '"')
            ->withFile($path);
    }

    /**
     * PUBLICZNE: GET /doc/{hash}/pixel.png - 1x1 tracking pixel dla email.
     * User widzi PDF w podgladzie email -> pixel sie laduje -> logujemy open.
     */
    public function pixel(string $hash): \Cake\Http\Response
    {
        $this->autoRender = false;
        try {
            $track = $this->loadActiveTrack($hash);
            $this->recordOpen($track, 'pixel');
        } catch (\Throwable $e) {}
        // 1x1 transparent PNG
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');
        return $this->response
            ->withType('image/png')
            ->withHeader('Cache-Control', 'no-store, no-cache, must-revalidate')
            ->withHeader('Pragma', 'no-cache')
            ->withStringBody($png);
    }

    /**
     * PUBLICZNE: POST /doc/{hash}/heartbeat - AJAX ping co 10s.
     * Aktualizuje total_time_seconds w ostatnim otwarciu.
     */
    public function heartbeat(string $hash): \Cake\Http\Response
    {
        $this->request->allowMethod(['post']);
        $this->autoRender = false;
        try {
            $track = $this->loadActiveTrack($hash);
            $track->total_time_seconds = (int)$track->total_time_seconds + 10;
            $this->fetchTable('CrmDocumentTracks')->save($track);
        } catch (\Throwable $e) {}
        return $this->response->withType('application/json')->withStringBody('{"ok":true}');
    }

    /**
     * ADMIN: POST /crm/doc/create - stworz tracking link dla dokumentu.
     * Body: {entity_type, entity_id, lead_id?, sent_to_email?, document_name, document_url, expires_days?}
     */
    public function create(): \Cake\Http\Response
    {
        $this->request->allowMethod(['post']);
        $this->autoRender = false;
        $identity  = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id');

        $data = $this->request->getData();
        $DT = $this->fetchTable('CrmDocumentTracks');
        $hash = bin2hex(random_bytes(24));

        $expiresDays = (int)($data['expires_days'] ?? 90);
        $expiresAt = $expiresDays > 0 ? new DateTime("+{$expiresDays} days") : null;

        $entity = $DT->newEntity([
            'id'            => Text::uuid(),
            'company_id'    => $companyId,
            'hash'          => $hash,
            'entity_type'   => (string)($data['entity_type'] ?? 'lead_document'),
            'entity_id'     => (string)($data['entity_id'] ?? ''),
            'lead_id'       => !empty($data['lead_id']) ? (string)$data['lead_id'] : null,
            'contractor_id' => !empty($data['contractor_id']) ? (string)$data['contractor_id'] : null,
            'sent_to_email' => (string)($data['sent_to_email'] ?? '') ?: null,
            'document_name' => (string)($data['document_name'] ?? 'document.pdf'),
            'document_url'  => (string)($data['document_url'] ?? ''),
            'document_size' => !empty($data['document_size']) ? (int)$data['document_size'] : null,
            'expires_at'    => $expiresAt,
            'is_active'     => true,
        ]);

        if (!$DT->save($entity)) {
            return $this->jsonResp(['ok' => false, 'errors' => $entity->getErrors()], 400);
        }

        $base = rtrim((string)\Cake\Core\Configure::read('App.fullBaseUrl'), '/');
        return $this->jsonResp([
            'ok'          => true,
            'id'          => $entity->id,
            'hash'        => $hash,
            'view_url'    => $base . '/doc/' . $hash,
            'pdf_url'     => $base . '/doc/' . $hash . '.pdf',
            'pixel_url'   => $base . '/doc/' . $hash . '/pixel.png',
            'expires_at'  => $expiresAt ? $expiresAt->format('Y-m-d H:i') : null,
        ]);
    }

    /**
     * ADMIN: GET /crm/doc/stats?entity_type=X&entity_id=Y
     * Zwraca wszystkie tracking-i dla danego dokumentu + statystyki.
     */
    public function stats(): \Cake\Http\Response
    {
        $this->request->allowMethod(['get']);
        $this->autoRender = false;
        $identity  = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id');

        $entityType = trim((string)$this->request->getQuery('entity_type', ''));
        $entityId   = trim((string)$this->request->getQuery('entity_id', ''));
        $leadId     = trim((string)$this->request->getQuery('lead_id', ''));

        $DT = $this->fetchTable('CrmDocumentTracks');
        $q = $DT->find()->where(['company_id' => $companyId]);
        if ($entityType) $q->where(['entity_type' => $entityType]);
        if ($entityId)   $q->where(['entity_id' => $entityId]);
        if ($leadId)     $q->where(['lead_id' => $leadId]);
        $tracks = $q->orderByDesc('created')->limit(50)->all();

        $out = [];
        $base = rtrim((string)\Cake\Core\Configure::read('App.fullBaseUrl'), '/');
        foreach ($tracks as $t) {
            $out[] = [
                'id'             => $t->id,
                'hash'           => $t->hash,
                'document_name'  => $t->document_name,
                'sent_to_email'  => $t->sent_to_email,
                'view_url'       => $base . '/doc/' . $t->hash,
                'total_opens'    => (int)$t->total_opens,
                'unique_ips'     => (int)$t->unique_ips,
                'total_time_s'   => (int)$t->total_time_seconds,
                'first_open_at'  => $t->first_open_at ? $t->first_open_at->format('Y-m-d H:i') : null,
                'last_open_at'   => $t->last_open_at ? $t->last_open_at->format('Y-m-d H:i') : null,
                'is_active'      => (bool)$t->is_active,
                'expires_at'     => $t->expires_at ? $t->expires_at->format('Y-m-d H:i') : null,
                'created'        => $t->created->format('Y-m-d H:i'),
                'opens'          => $t->getOpensLog(),
            ];
        }
        return $this->jsonResp(['ok' => true, 'tracks' => $out]);
    }

    /**
     * ADMIN: POST /crm/doc/{id}/deactivate
     */
    public function deactivate(string $id): void
    {
        $this->request->allowMethod(['post']);
        $identity  = $this->request->getAttribute('identity');
        $companyId = $identity?->get('company_id');

        $DT = $this->fetchTable('CrmDocumentTracks');
        $t = $DT->get($id);
        if ((string)$t->company_id !== (string)$companyId) throw new NotFoundException();
        $t->is_active = false;
        $DT->save($t);
        $this->Flash->success(__('Link tracking dezaktywowany.'));
        $this->redirect($this->referer() ?: ['controller' => 'Leads', 'action' => 'index']);
    }

    // ============= INTERNALS =============

    private function loadActiveTrack(string $hash)
    {
        $DT = $this->fetchTable('CrmDocumentTracks');
        $t = $DT->find()->where(['hash' => $hash])->first();
        if (!$t) throw new NotFoundException(__('Link tracking nie istnieje.'));
        if (!$t->is_active) throw new GoneException(__('Link zostal deaktywowany.'));
        if ($t->expires_at && $t->expires_at->getTimestamp() < time()) {
            throw new GoneException(__('Link wygasl.'));
        }
        return $t;
    }

    private function recordOpen($track, string $source): void
    {
        try {
            $ip = (string)$this->request->clientIp();
            $ua = (string)$this->request->getHeaderLine('User-Agent');
            $now = new DateTime();

            $opens = $track->getOpensLog();
            $isNewIp = true;
            foreach ($opens as $o) {
                if (($o['ip'] ?? '') === $ip) { $isNewIp = false; break; }
            }

            $opens[] = [
                'ip'     => $ip,
                'ua'     => mb_substr($ua, 0, 200),
                'source' => $source, // view | download | pixel | heartbeat
                'at'     => $now->format('Y-m-d H:i:s'),
            ];
            // Keep only last 200 entries
            if (count($opens) > 200) {
                $opens = array_slice($opens, -200);
            }

            $track->opens_json = json_encode($opens, JSON_UNESCAPED_UNICODE);
            $track->total_opens = (int)$track->total_opens + 1;
            if ($isNewIp) $track->unique_ips = (int)$track->unique_ips + 1;
            if (!$track->first_open_at) $track->first_open_at = $now;
            $track->last_open_at = $now;

            $this->fetchTable('CrmDocumentTracks')->save($track);

            // Log activity w leadzie (jesli powiazany)
            if (!empty($track->lead_id)) {
                try {
                    $this->fetchTable('LeadActivities')->logSystem(
                        (string)$track->company_id, (string)$track->lead_id, 'note',
                        sprintf(__('Klient otworzyl dokument: %s'), $track->document_name),
                        sprintf(__('%s (%s) - IP: %s'), $source, $now->format('H:i'), $ip),
                        ['track_id' => $track->id, 'source' => $source, 'ip' => $ip],
                        null
                    );
                } catch (\Throwable $e) {}
            }
        } catch (\Throwable $e) {
            \Cake\Log\Log::warning('DocTrack recordOpen failed: ' . $e->getMessage());
        }
    }

    private function jsonResp(array $body, int $status = 200): \Cake\Http\Response
    {
        $this->autoRender = false;
        return $this->response
            ->withStatus($status)
            ->withType('application/json')
            ->withStringBody(json_encode($body, JSON_UNESCAPED_UNICODE));
    }
}
