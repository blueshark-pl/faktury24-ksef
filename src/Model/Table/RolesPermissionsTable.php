<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;

/**
 * Pivot many-to-many: role ↔ uprawnienia.
 */
class RolesPermissionsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('roles_permissions');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp', [
            'events' => ['Model.beforeSave' => ['created' => 'new']],
        ]);

        $this->belongsTo('Roles',       ['foreignKey' => 'role_id',       'joinType' => 'INNER']);
        $this->belongsTo('Permissions', ['foreignKey' => 'permission_id', 'joinType' => 'INNER']);
    }
}
