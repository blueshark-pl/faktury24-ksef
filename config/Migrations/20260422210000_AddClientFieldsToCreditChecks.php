<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class AddClientFieldsToCreditChecks extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('credit_checks');
        $table
            ->addColumn('client_name', 'string', [
                'limit'   => 255,
                'null'    => true,
                'default' => null,
                'after'   => 'client_json',
                'comment' => 'client.name z API syntesys',
            ])
            ->addColumn('client_vat_eu', 'string', [
                'limit'   => 30,
                'null'    => true,
                'default' => null,
                'after'   => 'client_name',
                'comment' => 'client.vatEu z API syntesys',
            ])
            ->addColumn('client_city', 'string', [
                'limit'   => 100,
                'null'    => true,
                'default' => null,
                'after'   => 'client_vat_eu',
                'comment' => 'client.address.city z API syntesys',
            ])
            ->update();
    }
}
