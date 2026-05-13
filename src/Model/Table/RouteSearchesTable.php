<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;

class RouteSearchesTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('route_searches');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp', [
            'events' => [
                'Model.beforeSave' => [
                    'created'   => 'new',
                    'last_used' => 'always',
                ],
            ],
        ]);
    }
}
