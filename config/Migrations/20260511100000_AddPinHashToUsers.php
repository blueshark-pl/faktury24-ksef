<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Dodaje opcjonalny PIN bezpieczeństwa do tabeli users.
 * PIN (4-6 cyfr) służy do odblokowania ekranu po bezczynności
 * — alternatywa dla hasła. Hashowany identycznie jak hasło
 * (DefaultPasswordHasher).
 */
class AddPinHashToUsers extends BaseMigration
{
    public function change(): void
    {
        $this->table('users')
            ->addColumn('pin_hash', 'string', [
                'limit'   => 255,
                'null'    => true,
                'default' => null,
                'after'   => 'password',
                'comment' => 'BCrypt hash PIN-u (4-6 cyfr); null = brak PIN',
            ])
            ->update();
    }
}
