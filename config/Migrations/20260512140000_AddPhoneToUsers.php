<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * users.phone — numer telefonu kontaktowy (pole opcjonalne).
 * Używane m.in. w widgecie opiekuna klienta i danych kontaktowych.
 */
class AddPhoneToUsers extends BaseMigration
{
    public function change(): void
    {
        $this->table('users')
            ->addColumn('phone', 'string', [
                'limit'   => 32,
                'null'    => true,
                'default' => null,
                'after'   => 'last_name',
                'comment' => 'Numer telefonu (E.164 lub format krajowy)',
            ])
            ->update();
    }
}
