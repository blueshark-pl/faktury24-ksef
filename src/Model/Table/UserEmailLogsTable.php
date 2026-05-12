<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Utility\Text;

class UserEmailLogsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('user_email_logs');
        $this->setPrimaryKey('id');

        $this->getSchema()->setColumnType('created', 'datetime');

        // Bez belongsTo('Users') — CakeDC/Users plugin rejestruje swój alias
        // z innym targetTable className, co powoduje konflikt asocjacji.
        // Filtry/joiny po user_id robimy ręcznie.
    }

    /**
     * Generuj UUID dla rekordów (tabela używa UUID jako PK bez auto-increment).
     */
    public function newEmptyEntity(): \Cake\Datasource\EntityInterface
    {
        $entity = parent::newEmptyEntity();
        $entity->set('id', Text::uuid(), ['guard' => false, 'setter' => false]);
        return $entity;
    }
}
