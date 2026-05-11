<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;

/**
 * Role w systemie.
 *
 * @property \App\Model\Table\PermissionsTable&\Cake\ORM\Association\BelongsToMany $Permissions
 */
class RolesTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('roles');
        $this->setPrimaryKey('id');
        $this->setDisplayField('name');

        $this->addBehavior('Timestamp');

        $this->belongsToMany('Permissions', [
            'through'          => 'RolesPermissions',
            'foreignKey'       => 'role_id',
            'targetForeignKey' => 'permission_id',
            'saveStrategy'     => 'replace',
        ]);
    }
}
