<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * transport_addresses — słownik unikalnych adresów załadunku/rozładunku.
 *
 * Wyciągamy z istniejących speed_orders.place_from_* / place_to_* / load_*
 * / unload_* w osobnej migracji seedowej (CreateTransportAddressesSeed),
 * tutaj tylko schemat.
 */
class CreateTransportAddresses extends BaseMigration
{
    public function change(): void
    {
        $this->table('transport_addresses', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id',          'uuid',    ['null' => false])
            ->addColumn('name',        'string',  ['limit' => 255, 'null' => false,
                'comment' => 'Nazwa miejsca (np. ABC sp.z o.o. Magazyn Gdańsk)'])
            ->addColumn('address',     'string',  ['limit' => 255, 'null' => true, 'default' => null,
                'comment' => 'Ulica + numer'])
            ->addColumn('city',        'string',  ['limit' => 120, 'null' => true, 'default' => null])
            ->addColumn('postal_code', 'string',  ['limit' => 20,  'null' => true, 'default' => null])
            ->addColumn('country',     'string',  ['limit' => 5,   'null' => false, 'default' => 'PL'])
            ->addColumn('address_type','string',  ['limit' => 16,  'null' => false, 'default' => 'both',
                'comment' => 'loading|unloading|both'])
            ->addColumn('times_used',  'integer', ['null' => false, 'default' => 0, 'signed' => false])
            ->addColumn('is_active',   'boolean', ['null' => false, 'default' => true])
            ->addColumn('created',     'datetime',['null' => true])
            ->addColumn('modified',    'datetime',['null' => true])
            ->addIndex(['name', 'city', 'postal_code', 'country'], ['unique' => true, 'name' => 'UNIQ_ADDR'])
            ->addIndex(['city'],         ['name' => 'BY_CITY'])
            ->addIndex(['address_type'], ['name' => 'BY_TYPE'])
            ->addIndex(['is_active'],    ['name' => 'BY_ACTIVE'])
            ->create();
    }
}
