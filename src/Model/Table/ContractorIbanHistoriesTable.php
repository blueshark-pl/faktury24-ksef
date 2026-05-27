<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;

/**
 * @method \App\Model\Entity\ContractorIbanHistory newEntity(array $data, array $options = [])
 * @method \App\Model\Entity\ContractorIbanHistory|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 */
class ContractorIbanHistoriesTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('contractor_iban_history');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp');
    }

    /**
     * Normalizuje IBAN — strip whitespace/myślników, uppercase.
     */
    public static function normalizeIban(?string $iban): string
    {
        if ($iban === null || $iban === '') return '';
        return strtoupper(preg_replace('/[\s\-]/', '', $iban));
    }

    /**
     * Inkrementuje (lub tworzy) historię dla pary (company, nip, iban).
     * Wywoływane po każdym confirmMatch/addAllocation z bank_tx mającym IBAN.
     */
    public function record(
        string $companyId,
        string $contractorNip,
        string $iban,
        ?string $contractorNameSnapshot = null,
        float $amountPln = 0.0
    ): void {
        $normIban = self::normalizeIban($iban);
        if ($normIban === '' || $contractorNip === '') {
            return;
        }

        // Czy wpis już istnieje?
        $existing = $this->find()
            ->where([
                'company_id'     => $companyId,
                'contractor_nip' => $contractorNip,
                'iban'           => $normIban,
            ])
            ->first();

        $now = date('Y-m-d H:i:s');

        if ($existing !== null) {
            $existing->confirmed_count = (int)$existing->confirmed_count + 1;
            $existing->total_amount_pln = (float)$existing->total_amount_pln + $amountPln;
            $existing->last_used        = $now;
            if ($contractorNameSnapshot) {
                $existing->contractor_name_snapshot = $contractorNameSnapshot;
            }
            $this->save($existing);
        } else {
            $entity = $this->newEntity([
                'id'                       => \Cake\Utility\Text::uuid(),
                'company_id'               => $companyId,
                'contractor_nip'           => $contractorNip,
                'contractor_name_snapshot' => $contractorNameSnapshot,
                'iban'                     => $normIban,
                'confirmed_count'          => 1,
                'total_amount_pln'         => $amountPln,
                'first_used'               => $now,
                'last_used'                => $now,
            ]);
            $this->save($entity);
        }
    }

    /**
     * Zwraca mapę IBAN → liczba potwierdzeń dla danej spółki + listy NIPów.
     * Używana w scoreCandidate do dawania bonusu za znany IBAN.
     *
     * @return array [iban => ['nip' => '...', 'count' => N]]
     */
    public function mapByContractor(string $companyId, array $nips): array
    {
        if (empty($nips)) return [];

        $rows = $this->find()
            ->where([
                'company_id' => $companyId,
                'contractor_nip IN' => $nips,
            ])
            ->select(['iban', 'contractor_nip', 'confirmed_count'])
            ->disableHydration()
            ->all()
            ->toArray();

        $map = [];
        foreach ($rows as $r) {
            $iban = $r['iban'];
            // Gdy ten sam IBAN ma wpis pod 2 NIPami (rzadkie), bierzemy najwyższy count
            if (!isset($map[$iban]) || $map[$iban]['count'] < $r['confirmed_count']) {
                $map[$iban] = [
                    'nip'   => $r['contractor_nip'],
                    'count' => (int)$r['confirmed_count'],
                ];
            }
        }
        return $map;
    }
}
