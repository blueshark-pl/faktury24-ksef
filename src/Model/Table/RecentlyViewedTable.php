<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;

/**
 * RecentlyViewed Table
 *
 * Trzyma ostatnio przeglądane elementy (faktury, kontrahenci) per user.
 * Wpisy są deduplikowane przez unique key (user_id, entity_type, entity_id).
 */
class RecentlyViewedTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('recently_viewed');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp');
    }

    /**
     * Dodaje/aktualizuje wpis o przeglądanym elemencie i przycina historię do 5
     * najświeższych dla danego typu i usera.
     */
    public function track(string $userId, string $entityType, string $entityId, string $label, string $url, ?string $sub = null): void
    {
        if ($userId === '' || $entityId === '' || $label === '') {
            return;
        }
        if (!in_array($entityType, ['invoices', 'contractors'], true)) {
            return;
        }

        $existing = $this->find()
            ->where([
                'user_id'     => $userId,
                'entity_type' => $entityType,
                'entity_id'   => $entityId,
            ])
            ->first();

        if ($existing) {
            $existing->set('label', mb_substr($label, 0, 160));
            $existing->set('sub', $sub !== null ? mb_substr($sub, 0, 160) : null);
            $existing->set('url', mb_substr($url, 0, 255));
            $existing->set('modified', new \DateTime());
            $this->save($existing, ['checkRules' => false]);
        } else {
            $entity = $this->newEntity([
                'id'          => \Cake\Utility\Text::uuid(),
                'user_id'     => $userId,
                'entity_type' => $entityType,
                'entity_id'   => $entityId,
                'label'       => mb_substr($label, 0, 160),
                'sub'         => $sub !== null ? mb_substr($sub, 0, 160) : null,
                'url'         => mb_substr($url, 0, 255),
            ]);
            $this->save($entity, ['checkRules' => false]);
        }

        // Przytnij do 5 najświeższych dla pary (user, type)
        $keep = $this->find()
            ->select(['id'])
            ->where(['user_id' => $userId, 'entity_type' => $entityType])
            ->order(['modified' => 'DESC'])
            ->limit(5)
            ->all()
            ->extract('id')
            ->toList();

        if (!empty($keep)) {
            $this->deleteAll([
                'user_id'     => $userId,
                'entity_type' => $entityType,
                'id NOT IN'   => $keep,
            ]);
        }
    }

    /**
     * Pobiera ostatnio przeglądane dla usera w danym typie.
     * Zwraca listę asocjacyjnych tablic: ['id', 'label', 'sub', 'url'].
     */
    public function listForUser(string $userId, string $entityType, int $limit = 5): array
    {
        if ($userId === '') {
            return [];
        }
        return $this->find()
            ->select(['entity_id', 'label', 'sub', 'url'])
            ->where(['user_id' => $userId, 'entity_type' => $entityType])
            ->order(['modified' => 'DESC'])
            ->limit($limit)
            ->all()
            ->map(fn($row) => [
                'id'    => (string)$row->entity_id,
                'label' => (string)$row->label,
                'sub'   => $row->sub !== null ? (string)$row->sub : null,
                'url'   => (string)$row->url,
            ])
            ->toList();
    }
}
