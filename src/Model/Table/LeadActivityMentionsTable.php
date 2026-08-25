<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;

class LeadActivityMentionsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('lead_activity_mentions');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp', ['events' => ['Model.beforeSave' => ['created' => 'new']]]);
        $this->belongsTo('LeadActivities', ['foreignKey' => 'activity_id']);
        $this->belongsTo('Leads', ['foreignKey' => 'lead_id']);
        $this->belongsTo('MentionedUser', [
            'className' => 'Users', 'foreignKey' => 'mentioned_user_id',
        ]);
        $this->belongsTo('ByUser', [
            'className' => 'Users', 'foreignKey' => 'by_user_id', 'joinType' => 'LEFT',
        ]);
    }

    /**
     * Parsuje text pod katem @mentions (email lub login) i tworzy rekordy.
     * Wywolane przez LeadActivitiesTable::logSystem() lub controller activityAdd.
     *
     * @param string $companyId
     * @param string $activityId
     * @param string $leadId
     * @param string $text tekst do sparsowania (subject + body concat)
     * @param string|null $byUserId
     * @return int liczba utworzonych mentions
     */
    public function parseAndCreate(string $companyId, string $activityId, string $leadId, string $text, ?string $byUserId = null): int
    {
        if (trim($text) === '') return 0;
        // Wykrywa @login lub @email@domain (email z 2 @-ami: pierwszy w mention prefix)
        // Prosty regex: @[a-zA-Z0-9._-]+(@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})?
        if (!preg_match_all('/@([a-zA-Z0-9._-]+(?:@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})?)/', $text, $m)) {
            return 0;
        }
        $handles = array_unique($m[1]);
        if (empty($handles)) return 0;

        try {
            $Users = \Cake\ORM\TableRegistry::getTableLocator()->get('Users');
            // Match po email LUB po pierwszym slowie w email (login) - LIKE prefix
            $conditions = ['OR' => []];
            foreach ($handles as $h) {
                if (strpos($h, '@') !== false) {
                    $conditions['OR'][] = ['email' => $h];
                } else {
                    $conditions['OR'][] = ['email LIKE' => $h . '@%'];
                }
            }
            $users = $Users->find()
                ->where(['company_id' => $companyId, 'active' => true, $conditions])
                ->select(['id', 'email'])
                ->all()->toArray();
        } catch (\Throwable $e) {
            return 0;
        }
        if (empty($users)) return 0;

        $count = 0;
        foreach ($users as $u) {
            try {
                // Dedup - jesli juz jest mention na tym activity dla tego usera, skip
                $exists = $this->find()->where(['activity_id' => $activityId, 'mentioned_user_id' => $u->id])->count();
                if ($exists) continue;
                $mention = $this->newEntity([
                    'id' => \Cake\Utility\Text::uuid(),
                    'activity_id' => $activityId,
                    'lead_id' => $leadId,
                    'company_id' => $companyId,
                    'mentioned_user_id' => $u->id,
                    'by_user_id' => $byUserId,
                ]);
                if ($this->save($mention)) $count++;
            } catch (\Throwable $e) {}
        }
        return $count;
    }

    /**
     * Ilosc nieprzeczytanych mentions dla usera - dla badge w sidebarze.
     */
    public function unreadCountFor(string $userId): int
    {
        try {
            return $this->find()->where(['mentioned_user_id' => $userId, 'seen_at IS' => null])->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }
}
