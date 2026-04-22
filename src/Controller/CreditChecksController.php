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

    // Opisy kodów CCAT*
    public const ADVICE_TYPE_DESCRIPTIONS = [
        'CCAT1' => 'Ubezpieczyciel wyraża zgodę na współpracę z danym klientem na warunkach Umowy Ubezpieczenia („Limit automatyczny").',
        'CCAT2' => 'Ubezpieczyciel nie wyraża zgody na współpracę z danym klientem na warunkach Umowy Ubezpieczenia („Limit automatyczny").',
        'CCAT3' => null,
    ];

    // Opisy kodów CCCR* (przyczyny opinii)
    public const ADVICE_REASON_DESCRIPTIONS = [
        'CCCR1'  => 'Brak możliwości wydania opinii z uwagi na sprzeciw wobec przetwarzania danych osobowych (RODO).',
        'CCCR2'  => 'Na ocenę miało wpływ: oddział zagranicznego przedsiębiorcy / firma niepodlegająca ocenie / odbiorca publiczno-prawny.',
        'CCCR3'  => 'Firma znajduje się w początkowej fazie działalności.',
        'CCCR4'  => 'Firma zakończyła lub zawiesiła działalność.',
        'CCCR5'  => 'Opinia wydana w oparciu o informacje dotyczące zachowań płatniczych firmy.',
        'CCCR6'  => 'Opinia wydana w oparciu o informacje z bazy Allianz Trade i sprawozdania finansowe.',
        'CCCR7'  => 'Opinia wydana w oparciu o wcześniejsze raporty. Można złożyć wniosek o limit kredytowy na bazie aktualnych danych.',
        'CCCR9'  => 'Brak aktualnych dokumentów finansowych. Można złożyć wniosek o limit kredytowy z załączonymi sprawozdaniami.',
        'CCCR10' => 'Kraj odbiorcy aktualnie nie jest ubezpieczany w ramach limitu automatycznego.',
        'CCCR11' => 'Brak wystarczających informacji — rekomendowane złożenie wniosku o limit kredytowy.',
        'CCCR12' => 'Firma zakończyła/zawiesiła działalność lub zaszło zdarzenie prawne uniemożliwiające objęcie ochroną.',
        'CCCR13' => 'Zdarzenie prawne mogące doprowadzić do prawnie potwierdzonej niewypłacalności.',
        'CCCR14' => 'Zdarzenie prawne stanowiące prawnie potwierdzoną niewypłacalność.',
        'CCCR15' => 'Postępowanie układowe.',
        'CCCR16' => 'Postanowienie o zawarciu układu.',
        'CCCR17' => 'Ogłoszenie upadłości z możliwością zawarcia układu.',
        'CCCR18' => 'Postanowienie o przedmiocie zatwierdzenia.',
        'CCCR19' => 'Postępowanie sanacyjne.',
        'CCCR20' => 'Postępowanie upadłościowe.',
        'CCCR21' => 'Postępowanie o zatwierdzeniu układu.',
        'CCCR22' => 'Przyspieszone postępowanie układowe.',
        'CCCR23' => 'Postępowanie restrukturyzacyjne.',
        'CCCR24' => 'Upadłość.',
        'CCCR25' => 'Podczas oceny uwzględniono aktualnie dostępne informacje oraz ogólną sytuację gospodarczą i branżową.',
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
        $search   = trim((string)($this->request->getQuery('search')    ?? ''));
        $dateFrom = trim((string)($this->request->getQuery('date_from') ?? ''));
        $dateTo   = trim((string)($this->request->getQuery('date_to')   ?? ''));

        // Buduj zapytanie
        $query = $CreditChecks->find()
            ->where(['list_status' => $status])
            ->order(['advice_created_at' => 'DESC', 'id' => 'DESC']);

        if ($search !== '') {
            $query->where(['identifier LIKE' => '%' . $search . '%']);
        }
        if ($dateFrom !== '') {
            $query->where(['advice_created_at >=' => $dateFrom . ' 00:00:00']);
        }
        if ($dateTo !== '') {
            $query->where(['advice_created_at <=' => $dateTo . ' 23:59:59']);
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

        // Statystyki do kafelków
        $today30 = (new \DateTime('+30 days'))->format('Y-m-d');
        $todayStr = (new \DateTime())->format('Y-m-d');
        $stats = [
            'total'          => array_sum($counts),
            'done'           => $counts['done'],
            'expiringSoon'   => $CreditChecks->find()
                ->where([
                    'list_status'       => 'WITH_OPINION',
                    'advice_valid_to >=' => $todayStr,
                    'advice_valid_to <=' => $today30,
                ])
                ->count(),
            'expired'        => $CreditChecks->find()
                ->where([
                    'list_status'      => 'WITH_OPINION',
                    'advice_valid_to <' => $todayStr,
                    'advice_valid_to IS NOT' => null,
                ])
                ->count(),
            'errors'         => $counts['error'],
        ];

        // Data ostatniej sync
        $lastSync = $CreditChecks->find()
            ->select(['synced_at'])
            ->order(['synced_at' => 'DESC'])
            ->first();

        $this->set(compact('records', 'tab', 'search', 'dateFrom', 'dateTo', 'counts', 'lastSync', 'stats'));
        $this->set('statusLabels',             self::STATUS_LABELS);
        $this->set('adviceTypes',              self::ADVICE_TYPES);
        $this->set('adviceTypeDescriptions',   self::ADVICE_TYPE_DESCRIPTIONS);
        $this->set('adviceReasonDescriptions', self::ADVICE_REASON_DESCRIPTIONS);
        $this->set('errorTypes',               self::ERROR_TYPES);
    }

    // =========================================================================
    // Sync przez Puppeteer (AJAX POST)
    // =========================================================================

    public function sync(): Response
    {
        $this->request->allowMethod('post');

        // Zwiększ limit czasu — Puppeteer może potrzebować do 2 minut
        set_time_limit(150);

        try {
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
        } catch (\Throwable $e) {
            return $this->response
                ->withStatus(500)
                ->withType('application/json')
                ->withStringBody((string)json_encode([
                    'success' => false,
                    'message' => 'Wewnętrzny błąd serwera: ' . $e->getMessage(),
                    'inserted' => 0, 'updated' => 0, 'errors' => 0,
                ]));
        }
    }

    // =========================================================================
    // Sprawdź opinię kredytową dla podanego NIP (AJAX POST)
    // =========================================================================

    public function checkOpinion(): Response
    {
        $this->request->allowMethod('post');

        // Mikroserwis potrzebuje do 90s + narzut — dajemy 150s
        set_time_limit(150);

        try {
            $nip = preg_replace('/\D/', '', (string)($this->request->getData('nip') ?? ''));

            if (strlen($nip) < 9 || strlen($nip) > 15) {
                return $this->response
                    ->withType('application/json')
                    ->withStringBody((string)json_encode([
                        'success' => false,
                        'message' => 'Nieprawidłowy NIP (9–15 cyfr)',
                        'result'  => null,
                    ]));
            }

            $scraper = new SyntesysScraperService();
            $data    = $scraper->checkOpinion($nip);

            // Zapisz wynik natychmiast do bazy (sync pojedynczego rekordu — bez potrzeby full-sync)
            if ($data['success'] && $data['result'] !== null) {
                $this->saveCheckOpinionResult($nip, $data['result']);
            }

            return $this->response
                ->withType('application/json')
                ->withStringBody((string)json_encode([
                    'success' => $data['success'],
                    'message' => $data['message'],
                    'result'  => $data['result'],
                ]));
        } catch (\Throwable $e) {
            return $this->response
                ->withStatus(500)
                ->withType('application/json')
                ->withStringBody((string)json_encode([
                    'success' => false,
                    'message' => 'Wewnętrzny błąd serwera: ' . $e->getMessage(),
                    'result'  => null,
                ]));
        }
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

    // =========================================================================
    // Pomocnicze — zapis wyniku pojedynczego zapytania opinii do bazy
    // =========================================================================

    /**
     * Upsert pojedynczego rekordu z wyniku checkOpinion.
     * Nie wymaga potrzeby wywoływania pełnego sync po sprawdzeniu NIP.
     */
    private function saveCheckOpinionResult(string $nip, array $result): void
    {
        $externalId = (int)($result['id'] ?? 0);
        if ($externalId <= 0) {
            return;
        }

        $CreditChecks = $this->fetchTable('CreditChecks');
        $advice       = is_array($result['advice'] ?? null) ? $result['advice'] : [];
        $status       = (string)($result['status'] ?? 'WITH_OPINION');
        $now          = new \Cake\I18n\DateTime();

        $data = [
            'external_id'        => $externalId,
            'list_status'        => $status,
            'identifier'         => $nip,
            'advice_type_code'   => $advice['typeCode']    ?? null,
            'advice_reason_code' => $advice['reasonCode']  ?? null,
            'advice_valid_to'    => !empty($advice['validTo'])
                ? new \Cake\I18n\Date($advice['validTo']) : null,
            'advice_json'        => json_encode($result),
            'error_type_code'    => $result['errorTypeCode'] ?? null,
            'advice_created_at'  => !empty($advice['created'])
                ? new \Cake\I18n\DateTime($advice['created'])
                : (!empty($result['created']) ? new \Cake\I18n\DateTime($result['created']) : null),
            'client_name'        => $result['companyName'] ?? null,
            'synced_at'          => $now,
        ];

        // Spróbuj powiązać z kontrahentem po NIP
        $nipClean = preg_replace('/\D/', '', $nip);
        if (strlen($nipClean) >= 9) {
            $contractor = $this->fetchTable('Contractors')->find()
                ->where(['REPLACE(Contractors.nip, \'-\', \'\') LIKE' => $nipClean])
                ->select(['id'])
                ->first();
            if ($contractor !== null) {
                $data['contractor_id'] = $contractor->id;
            }
        }

        $existing = $CreditChecks->find()->where(['external_id' => $externalId])->first();
        $entity   = $existing
            ? $CreditChecks->patchEntity($existing, $data)
            : $CreditChecks->newEntity($data);

        $CreditChecks->save($entity);
    }
}
