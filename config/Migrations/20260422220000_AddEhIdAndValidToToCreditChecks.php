<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class AddEhIdAndValidToToCreditChecks extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('credit_checks');
        $table
            ->addColumn('client_eh_id', 'string', [
                'limit'   => 30,
                'null'    => true,
                'default' => null,
                'after'   => 'client_json',
                'comment' => 'client.ehId z API syntesys',
            ])
            ->addColumn('advice_valid_to', 'date', [
                'null'    => true,
                'default' => null,
                'after'   => 'advice_json',
                'comment' => 'advice.validTo z API syntesys',
            ])
            ->update();
    }
}
