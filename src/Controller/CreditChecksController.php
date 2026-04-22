<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\SyntesysScraperService;
use Cake\Http\Response;

/**
 * Moduł kredytu kupieckiego (Allianz Trade / Syntesys).
 *
 * Akcje:
 *  - index   GET  /kredyt-kupiecki          — główna lista (4 taby)
 *  - sync    POST /kredyt-kupiecki/sync      — synchronizacja przez Puppeteer (AJAX)
 *  - delete  POST /kredyt-kupiecki/usun/:id  — usuń rekord
 */
class CreditChecksController extends AppController
{
    // Mapowanie tabów na statusy API
    private const STATUS_MAP = [
        'done'      => 'WITH_OPINION',
        'waiting'   => 'PROCESSING',
        'no-advice' => 'NO_OPINION',
        'error'     => 'BUSINESS_ERROR',
    ];

    // Mapowanie czytelne etykiet
    private const STATUS_LABELS = [
        'WITH_OPINION'   => 'Opinie wydane',
        'PROCESSING'     => 'Oczekujące',
        'NO_OPINION'     => 'Brak opinii',
        'BUSINESS_ERROR' => 'Błędy',
    ];

    // Mapowanie kodów opinii CCAT*
    public const ADVICE_TYPES = [
        'CCAT1' => ['label' => 'TAK',        'badge' => 'success'],
        'CCAT2' => ['label' => 'NIE',         'badge' => 'danger'],
        'CCAT3' => ['label' => 'Brak opinii', 'badge' => 'secondary'],
    ];

    // Mapowanie błędów CCAN*
    public const ERROR_TYPES = [
        'CCAN1'  => 'Nie odnaleziono klienta o podanym identyfikatorze',
        'CCAN3'  => 'Kraj poza zakresem ubezpieczenia',
        'CCAN5'  => 'Opinia dla wybranego podmiotu już wydana',
        'CCAN9'  => 'Firma zgłosiła sprzeciw na przetwarzanie danych',
        'CCAN15' => 'Problem techniczny',
        'CCAN16' => 'Opinia dla wybranego podmiotu już wydana',
    ];

    // =========================================================================
    // Główna lista — 4 taby
    // =========================================================================

    public function index(): void
    {
        $CreditChecks = $this->fetchTable('CreditChecks');

        // Aktywny tab
        $tab    = (string)($this->request->getQuery('tab') ?? 'done');
        $status = self::STATUS_MAP[$tab] ?? 'WITH_OPINION';

        // Wyszukiwanie
        $search = trim((string)($this->request->getQuery('search') ?? ''));

        // Buduj zapytanie
        $query = $CreditChecks->find()
            ->where(['list_status' => $status])
            ->order(['advice_created_at' => 'DESC', 'id' => 'DESC']);

        if ($search !== '') {
            $query->where(['identifier LIKE' => '%' . $search . '%']);
        }

        // Paginate
        $this->paginate = [
            'limit'     => 50,
            'maxLimit'  => 200,
            'sortableFields' => ['identifier', 'advice_created_at', 'advice_type_code', 'created_by'],
        ];

        $records = $this->paginate($query);

        // Liczniki per-status do odznak w tabach
        $counts = [];
        foreach (self::STATUS_MAP as $key => $st) {
            $counts[$key] = $CreditChecks->find()->where(['list_status' => $st])->count();
        }

        // Data ostatniej sync
        $lastSync = $CreditChecks->find()
            ->select(['synced_at'])
            ->order(['synced_at' => 'DESC'])
            ->first();

        $this->set(compact('records', 'tab', 'search', 'counts', 'lastSync'));
        $this->set('statusLabels', self::STATUS_LABELS);
        $this->set('adviceTypes',  self::ADVICE_TYPES);
        $this->set('errorTypes',   self::ERROR_TYPES);
    }

    // =========================================================================
    // Sync przez Puppeteer (AJAX POST)
    // =========================================================================

    public function sync(): Response
    {
        $this->request->allowMethod('post');

        // Zwiększ limit czasu — Puppeteer może potrzebować do 2 minut
        set_time_limit(150);

        $list    = (string)($this->request->getData('list') ?? 'all');
        $allowed = ['all', 'done', 'waiting', 'no-advice', 'error'];
        if (!in_array($list, $allowed, true)) {
            $list = 'all';
        }

        $scraper = new SyntesysScraperService();
        $result  = $scraper->sync($list);

        return $this->response
            ->withType('application/json')
            ->withStringBody((string)json_encode([
                'success'  => $result['success'],
                'message'  => $result['message'],
                'inserted' => $result['inserted'],
                'updated'  => $result['updated'],
                'errors'   => $result['errors'],
            ]));
    }

    // =========================================================================
    // Usuń rekord
    // =========================================================================

    public function delete(int $id): Response
    {
        $this->request->allowMethod('post');

        $CreditChecks = $this->fetchTable('CreditChecks');
        $record       = $CreditChecks->get($id);

        if ($CreditChecks->delete($record)) {
            $this->Flash->success('Rekord usunięty.');
        } else {
            $this->Flash->error('Nie udało się usunąć rekordu.');
        }

        return $this->redirect(['action' => 'index']);
    }
}
