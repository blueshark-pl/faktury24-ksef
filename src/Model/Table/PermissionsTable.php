<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;

/**
 * Katalog kodów uprawnień (np. invoices.add).
 */
class PermissionsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('permissions');
        $this->setPrimaryKey('id');
        $this->setDisplayField('name');

        $this->addBehavior('Timestamp');

        $this->belongsToMany('Roles', [
            'through'          => 'RolesPermissions',
            'foreignKey'       => 'permission_id',
            'targetForeignKey' => 'role_id',
        ]);
    }
}
