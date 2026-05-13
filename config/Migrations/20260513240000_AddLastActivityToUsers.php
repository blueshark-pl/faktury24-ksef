<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class AddLastActivityToUsers extends AbstractMigration
{
    public function change(): void
    {
        $this->table('users')
            ->addColumn('last_activity_at', 'datetime', [
                'null' => true, 'default' => null,
                'comment' => 'Aktualizowane przy każdym request (debounced 5min) — do widoku "aktywni teraz" / "nieaktywni od X dni"',
                'after' => 'avatar',
            ])
            ->addIndex(['last_activity_at'])
            ->update();
    }
}
