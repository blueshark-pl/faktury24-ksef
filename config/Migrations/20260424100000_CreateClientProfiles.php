<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Profil klienta (rola `client`) — tabela pomocnicza wiążąca usera z NIP-em.
 * Klient widzi w portalu zlecenia transportowe (speed_orders), gdzie
 * buyer_nip = client_profiles.nip.
 */
class CreateClientProfiles extends BaseMigration
{
    public function change(): void
    {
        $table = $this->table('client_profiles', ['id' => false, 'primary_key' => ['id']]);

        $table->addColumn('id', 'uuid', ['null' => false]);
        $table->addColumn('user_id', 'uuid', ['null' => false]);
        $table->addColumn('nip', 'string', ['limit' => 30, 'null' => false]);
        $table->addColumn('company_name', 'string', ['limit' => 255, 'null' => true, 'default' => null]);
        $table->addColumn('contractor_id', 'uuid', ['null' => true, 'default' => null]);
        $table->addColumn('locale', 'string', ['limit' => 5, 'null' => false, 'default' => 'pl']);
        $table->addTimestamps('created', 'modified');

        // 1 user = 1 profil klienta
        $table->addIndex(['user_id'], ['unique' => true, 'name' => 'UNIQ_USER']);
        $table->addIndex(['nip'],     ['name' => 'BY_NIP']);

        $table->create();
    }
}
